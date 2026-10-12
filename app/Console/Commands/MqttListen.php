<?php

namespace App\Console\Commands;

use App\Enums\AccessLogStatus;
use App\Enums\DeviceStatus;
use App\Enums\PortalStatus;
use App\Events\PortalStatusUpdated;
use App\Models\Device;
use App\Mqtt\DeviceCommand;
use App\Mqtt\StaleDeviceCommandException;
use App\Services\AccessDecisionService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\MqttClient as PhpMqttClient;
use PhpMqtt\Client\PublishedMessage;
use PhpMqtt\Client\Repositories\MemoryRepository;
use Throwable;

/**
 * The real, production MQTT listener for Tahap 9 — this is what an actual
 * ESP32 talks to (subscribed to parkir/+/scan and parkir/+/status). It
 * calls the exact same AccessDecisionService::decide() the development-only
 * simulate-scan HTTP endpoint (Tahap 6) calls, so the auto/manual decision
 * logic lives in exactly one place regardless of which trigger fired it.
 * Auto-mode commands go back out on this daemon's own connection; manual
 * decisions are sent by the queued PublishDeviceCommand job instead.
 *
 * Runs as its own long-lived process (`php artisan mqtt:listen`), which
 * `php artisan dev` starts alongside serve, the queue workers, Vite and
 * Reverb (see docs/mqtt-topics.md for the topics it speaks).
 */
class MqttListen extends Command
{
    protected $signature = 'mqtt:listen';

    protected $description = 'Connect to the MQTT broker and process real ESP32 scan/status messages';

    private const SCAN_TOPIC = 'parkir/+/scan';

    private const STATUS_TOPIC = 'parkir/+/status';

    private const RECONNECT_DELAY_SECONDS = 5;

    /**
     * php-mqtt resends a QoS 1 message that got no PUBACK once it was last
     * sent longer ago than its resend timeout. That timeout is set to the
     * command TTL plus this margin, so every command goes stale — and is
     * dropped by dropUnacknowledgedCommands() — well before it could ever
     * qualify for a resend.
     */
    private const RESEND_MARGIN_SECONDS = 10;

    /**
     * Set by the SIGINT/SIGTERM handler; the retry loop checks it at every
     * step, so a shutdown signal is honoured even while the broker is down.
     */
    private bool $stopRequested = false;

    /**
     * @param  (callable(string $clientId, Repository $repository): PhpMqttClient)|null  $clientFactory  Overridable so tests can inject a mock instead of opening a real socket. The client must track its unacknowledged messages in the given repository.
     */
    public function __construct(private readonly mixed $clientFactory = null)
    {
        parent::__construct();
    }

    public function handle(AccessDecisionService $decisions): int
    {
        $clientId = config('mqtt.client_id_prefix').'-listener';
        $mqtt = null;
        $this->stopRequested = false;

        // Ctrl+C (SIGINT) or a kill / process manager (SIGTERM) stops the
        // daemon cleanly from any state: inside loop() via
        // MqttClient::interrupt(), and between reconnect attempts via
        // $stopRequested, which every step of the retry loop below checks
        // (the signal also cuts the reconnect wait short). $mqtt is captured
        // by reference because it's replaced on every reconnect below.
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            $shutdown = function () use (&$mqtt): void {
                // A second signal exits at once. The one state the first
                // can't end is a broker that accepted the socket but never
                // answers the MQTT handshake: php-mqtt waits for its CONNACK
                // without a deadline.
                if ($this->stopRequested) {
                    $this->warn('mqtt:listen: second shutdown signal, exiting immediately.');

                    exit(self::FAILURE);
                }

                $this->stopRequested = true;
                $this->info('mqtt:listen: shutdown signal received, disconnecting...');
                $mqtt?->interrupt();
            };
            pcntl_signal(SIGINT, $shutdown);
            pcntl_signal(SIGTERM, $shutdown);
        }

        try {
            // Outer retry loop: on top of the MQTT client's own
            // setReconnectAutomatically() (which recovers from brief
            // transport hiccups mid-operation), this makes sure that even a
            // broker outage long enough to exhaust that budget just gets
            // logged and retried after a short delay — the daemon process
            // itself never exits because the broker was unreachable, only
            // when it's told to stop.
            while (! $this->stopRequested) {
                // php-mqtt keeps every QoS 1 command this daemon publishes in this
                // repository until its PUBACK arrives, which is how
                // dropUnacknowledgedCommands() finds the ones that never got one.
                $repository = new MemoryRepository;

                try {
                    $mqtt = $this->makeClient($clientId, $repository);

                    // Drop stale unacknowledged commands at the start of every loop
                    // iteration and right after every (re)connect — together with
                    // the end of each message handler below, that always comes
                    // before php-mqtt's resend step (see dropUnacknowledgedCommands).
                    $dropStaleCommands = fn () => $this->dropUnacknowledgedCommands($repository, 'stale');
                    $mqtt->registerLoopEventHandler($dropStaleCommands);
                    $mqtt->registerConnectedEventHandler($dropStaleCommands);

                    $settings = (new ConnectionSettings)
                        ->setUsername(config('mqtt.username') ?: null)
                        ->setPassword(config('mqtt.password') ?: null)
                        ->setKeepAliveInterval(10)
                        ->setReconnectAutomatically(true)
                        ->setMaxReconnectAttempts(3)
                        ->setDelayBetweenReconnectAttempts(1000)
                        // Same short connect timeout as MqttPublisherService instead
                        // of php-mqtt's 60 s default, so an unreachable broker costs
                        // each of the reconnect attempts above seconds, not minutes.
                        ->setConnectTimeout((int) config('mqtt.connect_timeout'))
                        ->setResendTimeout((int) config('mqtt.command_ttl') + self::RESEND_MARGIN_SECONDS);

                    // A persistent (non-clean) session tied to our stable
                    // client ID, so the broker retains our subscriptions
                    // across a brief reconnect without us resubscribing.
                    $mqtt->connect($settings, false);

                    // Told to stop while connecting: the connection is up by
                    // now, so close it properly rather than start listening.
                    if ($this->stopRequested) {
                        $this->disconnectQuietly($mqtt);

                        break;
                    }

                    $this->info('mqtt:listen: connected to '.config('mqtt.host').':'.config('mqtt.port')." as [{$clientId}].");

                    $mqtt->subscribe(
                        self::SCAN_TOPIC,
                        function (string $topic, string $message) use ($decisions, $mqtt, $dropStaleCommands): void {
                            $this->handleScan($topic, $message, $decisions, $mqtt);
                            $dropStaleCommands();
                        },
                        1,
                    );
                    $mqtt->subscribe(
                        self::STATUS_TOPIC,
                        function (string $topic, string $message, bool $retained) use ($dropStaleCommands): void {
                            $this->handleStatus($topic, $message, $retained);
                            $dropStaleCommands();
                        },
                        1,
                    );

                    $this->info('mqtt:listen: subscribed to '.self::SCAN_TOPIC.' and '.self::STATUS_TOPIC.'. Listening...');

                    $mqtt->loop(true);

                    // loop() only returns normally after interrupt(), i.e. a
                    // shutdown signal — any broker failure escapes as an
                    // exception into the catch below. Nothing still waiting
                    // for a PUBACK can be resent after this, so log it, then
                    // say goodbye to the broker.
                    $this->dropUnacknowledgedCommands($repository, 'shutdown');
                    $this->disconnectQuietly($mqtt);

                    break;
                } catch (Throwable $e) {
                    // Whatever failed, it failed while shutting down: stop
                    // instead of reconnecting.
                    if ($this->stopRequested) {
                        $this->dropUnacknowledgedCommands($repository, 'shutdown');
                        $this->disconnectQuietly($mqtt);

                        break;
                    }

                    $exceptionClass = $e::class;
                    $this->error("mqtt:listen: broker connection lost or failed ({$exceptionClass}): {$e->getMessage()}");
                    Log::error('mqtt:listen: broker connection lost or failed, reconnecting shortly.', [
                        'exception' => $exceptionClass,
                        'message' => $e->getMessage(),
                    ]);

                    // This client and its repository are replaced on the next
                    // attempt, so nothing still waiting for a PUBACK can ever be
                    // acknowledged or resent now — log each one instead of losing
                    // it quietly.
                    $this->dropUnacknowledgedCommands($repository, 'connection_lost');
                    $this->disconnectQuietly($mqtt);

                    // A shutdown signal cuts this wait short (sleep() returns
                    // early), and the loop condition then stops the daemon.
                    Sleep::for(self::RECONNECT_DELAY_SECONDS)->seconds();
                }
            }
        } finally {
            // Hand the signals back to PHP's defaults once the daemon is done.
            if (function_exists('pcntl_signal')) {
                pcntl_signal(SIGINT, SIG_DFL);
                pcntl_signal(SIGTERM, SIG_DFL);
            }
        }

        $this->info('mqtt:listen: stopped.');

        return self::SUCCESS;
    }

    private function disconnectQuietly(?PhpMqttClient $mqtt): void
    {
        try {
            $mqtt?->disconnect();
        } catch (Throwable) {
            // The socket is already dead (or never connected); nothing to clean up.
        }
    }

    /**
     * parkir/{device_id}/scan handler: records the scan via the shared
     * AccessDecisionService and, if the decision was immediate (auto
     * mode), publishes the open/deny command back to the device right
     * away, on this daemon's own connection. A pending (manual mode)
     * decision publishes nothing here — the eventual command is sent by
     * the PublishDeviceCommand job once a human approves or rejects it.
     */
    private function handleScan(string $topic, string $message, AccessDecisionService $decisions, PhpMqttClient $mqtt): void
    {
        $deviceId = $this->extractDeviceId($topic, 'scan');

        if ($deviceId === null) {
            return;
        }

        $payload = json_decode($message, true);

        if (! is_array($payload) || ! isset($payload['uid']) || ! is_string($payload['uid']) || $payload['uid'] === '') {
            $this->logIssue("Ignoring malformed scan payload on [{$topic}]: {$message}");

            return;
        }

        $device = Device::find($deviceId);

        if ($device === null) {
            $this->logIssue("Ignoring scan for unknown device_id [{$deviceId}] on topic [{$topic}].");

            return;
        }

        $scannedAt = null;

        if (! empty($payload['scanned_at'])) {
            try {
                $scannedAt = $this->parseScannedAt($payload['scanned_at']);
            } catch (Throwable) {
                $raw = json_encode($payload['scanned_at']);
                $this->logIssue("Could not parse scanned_at [{$raw}] from device [{$deviceId}]; using server receipt time instead.");
            }
        }

        try {
            $log = $decisions->decide($device, $payload['uid'], $scannedAt);
        } catch (Throwable $e) {
            $this->logError("Failed to record scan for device [{$deviceId}]: {$e->getMessage()}");

            return;
        }

        $this->info("mqtt:listen: scan recorded for device [{$deviceId}], uid [{$payload['uid']}] -> status [{$log->status->value}].");

        if (! in_array($log->status, [AccessLogStatus::Approved, AccessLogStatus::Denied], true)) {
            // Pending (manual mode): wait for a human via the
            // approve/reject endpoints, which publish the command
            // themselves once resolved.
            return;
        }

        $command = DeviceCommand::fromAccessLog($log);
        $action = $command->action->value;

        try {
            // Recording the decision may have waited on a database lock, so
            // the same never-send-stale rule as the queued job applies here.
            if ($command->isStale()) {
                throw StaleDeviceCommandException::for($command);
            }

            // Sent on this daemon's own persistent connection: no queue hop
            // (latency) and no fresh connection per scan (no CONNACK wait in
            // the scan path). The QoS 1 PUBACK is handled by the loop() that
            // is already running; see dropUnacknowledgedCommands() for what
            // happens when it never comes.
            $mqtt->publish($command->topic(), $command->payload(), 1);
        } catch (Throwable $e) {
            // Same structured context as AccessLogController::resolve()'s
            // publish failure, so both paths can be traced the same way.
            $this->error("mqtt:listen: Failed to publish [{$action}] command to device [{$deviceId}] for access log [{$log->id}]: {$e->getMessage()}");
            Log::error('mqtt:listen: failed to publish device command after recording scan.', [
                'access_log_id' => $log->id,
                'device_id' => $device->id,
                'action' => $action,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * scanned_at as sent by the device: Unix epoch seconds — an integer or a
     * string of digits, the simplest thing for an ESP32 to send and the same
     * form as a command's issued_at — or any date string Carbon::parse()
     * reads, such as ISO-8601.
     *
     * Converted to the app timezone before it's stored: the datetime cast
     * writes the wall-clock time as-is, so "15:31:04+07:00" would otherwise
     * be saved as 15:31:04 and read back seven hours off.
     */
    private function parseScannedAt(mixed $value): CarbonInterface
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $scannedAt = Carbon::createFromTimestamp((int) $value);
        } elseif (is_string($value)) {
            $scannedAt = Carbon::parse($value);
        } else {
            throw new InvalidArgumentException('scanned_at must be epoch seconds or a date string.');
        }

        return $scannedAt->setTimezone(config('app.timezone'));
    }

    /**
     * Takes unacknowledged commands out of php-mqtt's resend queue and logs
     * each one, so an auto-mode command is either acknowledged within its
     * TTL or visibly given up on — never resent late.
     *
     * Why a stale command can't slip through to a resend: php-mqtt resends
     * only from resendPendingMessages(), near the end of each loop
     * iteration, and only messages last sent longer ago than the resend
     * timeout (TTL + RESEND_MARGIN_SECONDS). A command is first sent after
     * its decision (issued_at), so it goes stale at least that margin
     * before it could qualify. This method runs at the start of every loop
     * iteration, after every (re)connect — including php-mqtt's own
     * auto-reconnect, which can happen mid-iteration — and at the end of
     * every message handler. Between the last of those and the resend step
     * only php-mqtt's own bookkeeping runs (parsing acks, pings), which
     * takes microseconds, not the 10 s margin.
     *
     * @param  'stale'|'connection_lost'|'shutdown'  $reason  only 'stale' keeps commands still within their TTL; the others drop every pending command, because its connection is gone or about to be
     */
    private function dropUnacknowledgedCommands(Repository $repository, string $reason): void
    {
        $staleOnly = $reason === 'stale';

        // The repository has no "all pending" getter; "last sent before a
        // moment in the future" is every pending message.
        foreach ($repository->getPendingOutgoingMessagesLastSentBefore(new DateTime('+1 day')) as $pending) {
            if (! $pending instanceof PublishedMessage) {
                continue;
            }

            $command = DeviceCommand::fromMessage($pending->getTopicName(), $pending->getMessage());

            if ($command === null || ($staleOnly && ! $command->isStale())) {
                continue;
            }

            $repository->removePendingOutgoingMessage($pending->getMessageId());

            $this->error("mqtt:listen: Dropped unacknowledged [{$command->action->value}] command to device [{$command->deviceId}] for access log [{$command->accessLogId}] ({$reason}).");
            Log::error('mqtt:listen: broker never acknowledged a device command; dropped instead of resending.', [
                'access_log_id' => $command->accessLogId,
                'device_id' => $command->deviceId,
                'action' => $command->action->value,
                'issued_at' => $command->issuedAt,
                'reason' => $reason,
            ]);
        }
    }

    private function makeClient(string $clientId, Repository $repository): PhpMqttClient
    {
        if ($this->clientFactory !== null) {
            return ($this->clientFactory)($clientId, $repository);
        }

        return new PhpMqttClient(config('mqtt.host'), (int) config('mqtt.port'), $clientId, repository: $repository);
    }

    /**
     * parkir/{device_id}/status handler: treats a heartbeat and the LWT
     * ({"online": false}, no portal_status) exactly the same way, since
     * they land on the identical topic — see MqttPublisherService and
     * the Tahap 9 design notes for why the LWT is configured this way.
     *
     * Both are published retained, so $retained tells a status the device
     * just sent from the broker replaying its last one when this daemon
     * (re)subscribes — which happens after every reconnect, and can be
     * hours old.
     */
    private function handleStatus(string $topic, string $message, bool $retained): void
    {
        $deviceId = $this->extractDeviceId($topic, 'status');

        if ($deviceId === null) {
            return;
        }

        $payload = json_decode($message, true);

        if (! is_array($payload) || ! array_key_exists('online', $payload)) {
            $this->logIssue("Ignoring malformed status payload on [{$topic}]: {$message}");

            return;
        }

        $device = Device::find($deviceId);

        if ($device === null) {
            $this->logIssue("Ignoring status for unknown device_id [{$deviceId}] on topic [{$topic}].");

            return;
        }

        $attributes = [
            'status' => $payload['online'] ? DeviceStatus::Online : DeviceStatus::Offline,
        ];

        // A replayed status still tells the last state the device reported
        // (a device that died while this daemon was down left its retained
        // LWT behind), but not when it reported it: only a live message
        // proves the device is around right now.
        if (! $retained) {
            $attributes['last_seen_at'] = now();
        }

        // The LWT payload carries no portal_status (a device that just
        // dropped its connection can't report the physical gate state),
        // so only touch this column when the message actually has it —
        // never guess "closed" just because the device went offline.
        if (array_key_exists('portal_status', $payload)) {
            $portalStatus = is_string($payload['portal_status']) ? PortalStatus::tryFrom($payload['portal_status']) : null;

            if ($portalStatus !== null) {
                $attributes['portal_status'] = $portalStatus;
            } else {
                $this->logIssue("Ignoring unknown portal_status [{$payload['portal_status']}] from device [{$deviceId}].");
            }
        }

        $device->update($attributes);

        // Same public-landing broadcast Tahap 8 already fires on every
        // approval/rejection (see BroadcastPublicLandingUpdates) — a
        // real device status change is just another trigger for it.
        PortalStatusUpdated::dispatch();

        $this->info("mqtt:listen: device [{$deviceId}] status updated (online=".($payload['online'] ? 'true' : 'false').(isset($attributes['portal_status']) ? ", portal_status={$attributes['portal_status']->value}" : '').($retained ? ', retained: last_seen_at unchanged' : '').').');
    }

    /**
     * Pulls the numeric {device_id} out of a concrete topic like
     * "parkir/3/scan" (the wildcard subscription still delivers the
     * real, non-wildcard topic to the callback).
     */
    private function extractDeviceId(string $topic, string $suffix): ?int
    {
        if (! preg_match('#^parkir/(\d+)/'.$suffix.'$#', $topic, $matches)) {
            $this->logIssue("Ignoring message on unexpected topic shape [{$topic}].");

            return null;
        }

        return (int) $matches[1];
    }

    private function logIssue(string $message): void
    {
        $this->warn("mqtt:listen: {$message}");
        Log::warning("mqtt:listen: {$message}");
    }

    private function logError(string $message): void
    {
        $this->error("mqtt:listen: {$message}");
        Log::error("mqtt:listen: {$message}");
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\AccessLogStatus;
use App\Enums\DeviceStatus;
use App\Enums\PortalStatus;
use App\Events\PortalStatusUpdated;
use App\Models\Device;
use App\Services\AccessDecisionService;
use App\Services\MqttPublisherService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\MqttClient as PhpMqttClient;
use Throwable;

/**
 * The real, production MQTT listener for Tahap 9 — this is what an actual
 * ESP32 talks to (subscribed to parkir/+/scan and parkir/+/status). It
 * calls the exact same AccessDecisionService::decide() the development-only
 * simulate-scan HTTP endpoint (Tahap 6) calls, so the auto/manual decision
 * logic lives in exactly one place regardless of which trigger fired it.
 *
 * Run as its own long-lived process (`php artisan mqtt:listen`), separate
 * from `php artisan serve`, `npm run dev`, and `php artisan reverb:start`.
 */
class MqttListen extends Command
{
    protected $signature = 'mqtt:listen';

    protected $description = 'Connect to the MQTT broker and process real ESP32 scan/status messages';

    private const SCAN_TOPIC = 'parkir/+/scan';

    private const STATUS_TOPIC = 'parkir/+/status';

    private const RECONNECT_DELAY_SECONDS = 5;

    public function handle(AccessDecisionService $decisions, MqttPublisherService $publisher): int
    {
        $clientId = config('mqtt.client_id_prefix').'-listener';
        $mqtt = null;

        // Lets `Ctrl+C` (or a `kill`, e.g. from a process manager) stop the
        // loop cleanly via MqttClient::interrupt() instead of the process
        // being killed mid-socket-operation. $mqtt is captured by
        // reference because it's replaced on every reconnect below.
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            $shutdown = function () use (&$mqtt): void {
                $this->info('mqtt:listen: shutdown signal received, disconnecting...');
                $mqtt?->interrupt();
            };
            pcntl_signal(SIGINT, $shutdown);
            pcntl_signal(SIGTERM, $shutdown);
        }

        // Outer retry loop: on top of the MQTT client's own
        // setReconnectAutomatically() (which recovers from brief
        // transport hiccups mid-operation), this makes sure that even a
        // broker outage long enough to exhaust that budget just gets
        // logged and retried after a short delay — the daemon process
        // itself never exits because the broker was unreachable.
        while (true) {
            try {
                $mqtt = new PhpMqttClient(config('mqtt.host'), (int) config('mqtt.port'), $clientId);

                $settings = (new ConnectionSettings)
                    ->setUsername(config('mqtt.username') ?: null)
                    ->setPassword(config('mqtt.password') ?: null)
                    ->setKeepAliveInterval(10)
                    ->setReconnectAutomatically(true)
                    ->setMaxReconnectAttempts(3)
                    ->setDelayBetweenReconnectAttempts(1000);

                // A persistent (non-clean) session tied to our stable
                // client ID, so the broker retains our subscriptions
                // across a brief reconnect without us resubscribing.
                $mqtt->connect($settings, false);

                $this->info('mqtt:listen: connected to '.config('mqtt.host').':'.config('mqtt.port')." as [{$clientId}].");

                $mqtt->subscribe(
                    self::SCAN_TOPIC,
                    fn (string $topic, string $message) => $this->handleScan($topic, $message, $decisions, $publisher),
                    1,
                );
                $mqtt->subscribe(
                    self::STATUS_TOPIC,
                    fn (string $topic, string $message) => $this->handleStatus($topic, $message),
                    1,
                );

                $this->info('mqtt:listen: subscribed to '.self::SCAN_TOPIC.' and '.self::STATUS_TOPIC.'. Listening...');

                $mqtt->loop(true);

                // loop() only returns normally after interrupt() (a
                // graceful Ctrl+C/SIGTERM shutdown above) — any broker
                // failure escapes as an exception into the catch below.
                $this->info('mqtt:listen: stopped.');

                return self::SUCCESS;
            } catch (Throwable $e) {
                $exceptionClass = $e::class;
                $this->error("mqtt:listen: broker connection lost or failed ({$exceptionClass}): {$e->getMessage()}");
                Log::error('mqtt:listen: broker connection lost or failed, reconnecting shortly.', [
                    'exception' => $exceptionClass,
                    'message' => $e->getMessage(),
                ]);

                try {
                    $mqtt?->disconnect();
                } catch (Throwable) {
                    // The socket is already dead; nothing to clean up.
                }

                sleep(self::RECONNECT_DELAY_SECONDS);
            }
        }
    }

    /**
     * parkir/{device_id}/scan handler: records the scan via the shared
     * AccessDecisionService and, if the decision was immediate (auto
     * mode), publishes the open/deny command back to the device right
     * away. A pending (manual mode) decision publishes nothing here —
     * the eventual command is sent by the approve/reject endpoint once
     * a human decides.
     */
    private function handleScan(string $topic, string $message, AccessDecisionService $decisions, MqttPublisherService $publisher): void
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
                $scannedAt = Carbon::parse($payload['scanned_at']);
            } catch (Throwable) {
                $this->logIssue("Could not parse scanned_at [{$payload['scanned_at']}] from device [{$deviceId}]; using server receipt time instead.");
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

        $action = $log->status === AccessLogStatus::Approved ? 'open' : 'deny';

        try {
            $publisher->publishCommand($device->id, $action);
        } catch (Throwable $e) {
            $this->logError("Failed to publish [{$action}] command to device [{$deviceId}]: {$e->getMessage()}");
        }
    }

    /**
     * parkir/{device_id}/status handler: treats a heartbeat and the LWT
     * ({"online": false}, no portal_status) exactly the same way, since
     * they land on the identical topic — see MqttPublisherService and
     * the Tahap 9 design notes for why the LWT is configured this way.
     */
    private function handleStatus(string $topic, string $message): void
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
            'last_seen_at' => now(),
            'status' => $payload['online'] ? DeviceStatus::Online : DeviceStatus::Offline,
        ];

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

        $this->info("mqtt:listen: device [{$deviceId}] status updated (online=".($payload['online'] ? 'true' : 'false').(isset($attributes['portal_status']) ? ", portal_status={$attributes['portal_status']->value}" : '').').');
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

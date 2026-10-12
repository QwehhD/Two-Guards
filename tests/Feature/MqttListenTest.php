<?php

namespace Tests\Feature;

use App\Console\Commands\MqttListen;
use App\Enums\DeviceCommandAction;
use App\Enums\DeviceMode;
use App\Enums\DeviceStatus;
use App\Enums\PortalStatus;
use App\Enums\RfidCardStatus;
use App\Models\AccessLog;
use App\Models\Device;
use App\Models\RfidCard;
use App\Mqtt\DeviceCommand;
use App\Services\AccessDecisionService;
use Closure;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\MqttClient as PhpMqttClient;
use PhpMqtt\Client\PendingMessage;
use PhpMqtt\Client\PublishedMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Drives `mqtt:listen` with a mocked php-mqtt client: the mock records the
 * subscriptions and hooks the daemon registers, and its loop() stands in for
 * messages arriving before returning (which the daemon treats as a graceful
 * stop). The repository is php-mqtt's real MemoryRepository, so dropping
 * unacknowledged commands is exercised for real.
 */
class MqttListenTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    private const DROPPED_MESSAGE = 'mqtt:listen: broker never acknowledged a device command; dropped instead of resending.';

    /** @var array<string, Closure> subscription callbacks by topic filter */
    private array $subscriptions = [];

    private ?Closure $loopHandler = null;

    private ?Closure $connectedHandler = null;

    private ?ConnectionSettings $settings = null;

    private ?Repository $repository = null;

    /** @var list<int> */
    private array $pendingAfterHook = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['mqtt.command_ttl' => 10, 'mqtt.client_id_prefix' => 'test-prefix']);

        // Staleness is decided on whole epoch seconds, so the clock must not
        // tick between building a pending command and asserting on it.
        $this->freezeTime();
        Sleep::fake();
    }

    public function test_an_auto_mode_scan_publishes_its_command_on_the_daemons_own_connection(): void
    {
        $device = Device::factory()->create(['mode' => DeviceMode::Auto]);
        $card = RfidCard::factory()->create(['status' => RfidCardStatus::Active]);

        $client = $this->client(function () use ($device, $card) {
            $this->subscriptions['parkir/+/scan']("parkir/{$device->id}/scan", json_encode(['uid' => $card->uid]));
        });
        $client->shouldReceive('publish')->once()->withArgs(function (string $topic, string $payload, int $qos) use ($device) {
            $command = DeviceCommand::fromAccessLog(AccessLog::sole());

            return $topic === "parkir/{$device->id}/command"
                && $payload === $command->payload()
                && $command->action === DeviceCommandAction::Open
                && $qos === 1;
        });

        $this->runDaemon($client);
    }

    public function test_a_manual_mode_scan_publishes_nothing(): void
    {
        $device = Device::factory()->create(['mode' => DeviceMode::Manual]);

        $client = $this->client(function () use ($device) {
            $this->subscriptions['parkir/+/scan']("parkir/{$device->id}/scan", json_encode(['uid' => 'DEADBEEF']));
        });
        $client->shouldReceive('publish')->never();

        $this->runDaemon($client);

        $this->assertDatabaseCount('access_logs', 1);
    }

    public function test_a_live_status_marks_the_device_seen_now(): void
    {
        $device = Device::factory()->create([
            'status' => DeviceStatus::Offline,
            'portal_status' => PortalStatus::Closed,
            'last_seen_at' => now()->subHour(),
        ]);

        $client = $this->client(function () use ($device) {
            $this->subscriptions['parkir/+/status']("parkir/{$device->id}/status", '{"online":true,"portal_status":"open"}', false);
        });

        $this->runDaemon($client);

        $device->refresh();
        $this->assertSame(DeviceStatus::Online, $device->status);
        $this->assertSame(PortalStatus::Open, $device->portal_status);
        $this->assertSame(now()->toDateTimeString(), $device->last_seen_at->toDateTimeString());
    }

    public function test_a_retained_heartbeat_updates_the_state_but_not_last_seen_at(): void
    {
        $lastSeen = now()->subHour();
        $device = Device::factory()->create([
            'status' => DeviceStatus::Offline,
            'portal_status' => PortalStatus::Closed,
            'last_seen_at' => $lastSeen,
        ]);

        // The broker replays the last retained status on every
        // (re)subscription, however long ago the device sent it.
        $client = $this->client(function () use ($device) {
            $this->subscriptions['parkir/+/status']("parkir/{$device->id}/status", '{"online":true,"portal_status":"open"}', true);
        });

        $this->runDaemon($client);

        $device->refresh();
        $this->assertSame(DeviceStatus::Online, $device->status);
        $this->assertSame(PortalStatus::Open, $device->portal_status);
        $this->assertSame($lastSeen->toDateTimeString(), $device->last_seen_at->toDateTimeString());
    }

    public function test_a_retained_lwt_marks_the_device_offline_but_not_seen(): void
    {
        $lastSeen = now()->subHour();
        $device = Device::factory()->create([
            'status' => DeviceStatus::Online,
            'portal_status' => PortalStatus::Closed,
            'last_seen_at' => $lastSeen,
        ]);

        // The device died while the daemon was down: what the daemon finds
        // on subscribing is the LWT the broker sent on its behalf.
        $client = $this->client(function () use ($device) {
            $this->subscriptions['parkir/+/status']("parkir/{$device->id}/status", '{"online":false}', true);
        });

        $this->runDaemon($client);

        $device->refresh();
        $this->assertSame(DeviceStatus::Offline, $device->status);
        $this->assertSame(PortalStatus::Closed, $device->portal_status);
        $this->assertSame($lastSeen->toDateTimeString(), $device->last_seen_at->toDateTimeString());
    }

    public function test_the_resend_timeout_leaves_a_margin_after_the_command_ttl(): void
    {
        $this->runDaemon($this->client());

        // TTL (10) + RESEND_MARGIN_SECONDS (10): a command is stale, and so
        // dropped, ten seconds before php-mqtt would consider resending it.
        $this->assertSame(20, $this->settings->getResendTimeout());
    }

    public function test_a_stale_unacknowledged_command_is_dropped_and_logged_at_the_start_of_a_loop_iteration(): void
    {
        Log::spy();

        $client = $this->client(function () {
            $this->addPendingCommands();
            ($this->loopHandler)();
            $this->snapshotPending();
        });

        $this->runDaemon($client);

        $this->assertOnlyTheStaleCommandWasDropped();
    }

    public function test_a_stale_unacknowledged_command_is_dropped_right_after_a_reconnect(): void
    {
        Log::spy();

        $client = $this->client(function () {
            $this->addPendingCommands();
            // php-mqtt calls this at the end of every connect, including its
            // own auto-reconnect in the middle of a loop iteration.
            ($this->connectedHandler)();
            $this->snapshotPending();
        });

        $this->runDaemon($client);

        $this->assertOnlyTheStaleCommandWasDropped();
    }

    public function test_a_stale_unacknowledged_command_is_dropped_before_a_message_handler_returns(): void
    {
        Log::spy();
        $device = Device::factory()->create();

        $client = $this->client(function () use ($device) {
            $this->addPendingCommands();
            $this->subscriptions['parkir/+/status']("parkir/{$device->id}/status", json_encode(['online' => true]), false);
            $this->snapshotPending();
        });

        $this->runDaemon($client);

        $this->assertOnlyTheStaleCommandWasDropped();
    }

    public function test_every_pending_command_is_dropped_and_logged_when_the_connection_is_lost(): void
    {
        Log::spy();

        $lostConnection = $this->client(function () {
            $this->repository->addPendingOutgoingMessage($this->pendingCommand(1, accessLogId: 51, issuedAt: now()->getTimestamp()));

            throw new RuntimeException('socket closed');
        });
        $lostConnection->shouldReceive('disconnect')->once();

        $clients = [$lostConnection, $this->client()];

        $this->runDaemon(function () use (&$clients) {
            return array_shift($clients);
        });

        // Even a fresh command: its client and repository are thrown away,
        // so it could never be acknowledged or resent any more.
        Log::shouldHaveReceived('error')->with(self::DROPPED_MESSAGE, [
            'access_log_id' => 51,
            'device_id' => 7,
            'action' => 'open',
            'issued_at' => now()->getTimestamp(),
            'reason' => 'connection_lost',
        ])->once();
        Sleep::assertSleptTimes(1);
    }

    public function test_the_daemon_connects_with_the_configured_connect_timeout(): void
    {
        // A string, because that's how an env value always arrives.
        config(['mqtt.connect_timeout' => '4']);

        $this->runDaemon($this->client());

        // php-mqtt's own default is 60, so 4 can only come from config.
        $this->assertSame(4, $this->settings->getConnectTimeout());
    }

    #[DataProvider('readableScannedAtValues')]
    public function test_scanned_at_is_read_as_epoch_seconds_or_a_date_string(int|string $scannedAt): void
    {
        $device = Device::factory()->create(['mode' => DeviceMode::Manual]);

        $client = $this->client(function () use ($device, $scannedAt) {
            $this->subscriptions['parkir/+/scan']("parkir/{$device->id}/scan", json_encode(['uid' => 'DEADBEEF', 'scanned_at' => $scannedAt]));
        });
        $client->shouldReceive('publish')->never();

        $this->runDaemon($client);

        $this->assertSame('2026-10-11 08:31:04', AccessLog::sole()->scanned_at->utc()->toDateTimeString());
    }

    public static function readableScannedAtValues(): array
    {
        return [
            'epoch seconds as an integer' => [1791707464],
            'epoch seconds as a string of digits' => ['1791707464'],
            'ISO-8601' => ['2026-10-11T08:31:04Z'],
            'ISO-8601 with an offset' => ['2026-10-11T15:31:04+07:00'],
        ];
    }

    public function test_an_unreadable_scanned_at_falls_back_to_the_receipt_time(): void
    {
        $device = Device::factory()->create(['mode' => DeviceMode::Manual]);

        $client = $this->client(function () use ($device) {
            // A float epoch isn't accepted: only whole seconds are.
            $this->subscriptions['parkir/+/scan']("parkir/{$device->id}/scan", json_encode(['uid' => 'DEADBEEF', 'scanned_at' => 1791707464.5]));
        });
        $client->shouldReceive('publish')->never();

        $this->runDaemon($client);

        $this->assertSame(now()->toDateTimeString(), AccessLog::sole()->scanned_at->toDateTimeString());
    }

    #[DataProvider('shutdownSignals')]
    public function test_a_shutdown_signal_while_the_broker_is_unreachable_stops_instead_of_retrying(int $signal): void
    {
        // A real signal to this very process: the daemon's handler catches it.
        $client = $this->unreachableClient(fn () => posix_kill(getmypid(), $signal));

        $connects = $this->runDaemon($client);

        $this->assertSame(1, $connects, 'The daemon reconnected after being told to stop.');
        Sleep::assertNeverSlept();
        // And the signal goes back to PHP's default once the daemon is done.
        $this->assertSame(SIG_DFL, pcntl_signal_get_handler($signal));
    }

    public static function shutdownSignals(): array
    {
        return [
            'SIGTERM (kill, process managers)' => [SIGTERM],
            'SIGINT (Ctrl+C)' => [SIGINT],
        ];
    }

    public function test_a_shutdown_signal_during_the_reconnect_wait_stops_the_daemon(): void
    {
        // The signal lands while the daemon waits to retry an unreachable broker.
        Sleep::whenFakingSleep(fn () => posix_kill(getmypid(), SIGTERM));

        $connects = $this->runDaemon($this->unreachableClient());

        $this->assertSame(1, $connects, 'The daemon reconnected after being told to stop.');
        Sleep::assertSleptTimes(1);
    }

    public function test_a_shutdown_signal_while_listening_interrupts_the_loop_and_disconnects(): void
    {
        Log::spy();

        $client = $this->client(function () {
            $this->repository->addPendingOutgoingMessage($this->pendingCommand(1, accessLogId: 61, issuedAt: now()->getTimestamp()));
            posix_kill(getmypid(), SIGTERM);
        });
        $client->shouldReceive('interrupt')->once();
        $client->shouldReceive('disconnect')->once();

        $this->assertSame(1, $this->runDaemon($client));

        // Nothing can resend a command still waiting for its PUBACK once the
        // daemon is gone, so it's logged like any other dropped command.
        Log::shouldHaveReceived('error')->with(self::DROPPED_MESSAGE, [
            'access_log_id' => 61,
            'device_id' => 7,
            'action' => 'open',
            'issued_at' => now()->getTimestamp(),
            'reason' => 'shutdown',
        ])->once();
    }

    /**
     * A mocked php-mqtt client that records what the daemon registers on it,
     * and whose loop() runs $whileLooping before returning.
     */
    private function client(?Closure $whileLooping = null): MockInterface
    {
        $client = Mockery::mock(PhpMqttClient::class);

        $client->shouldReceive('registerLoopEventHandler')->once()->andReturnUsing(function (Closure $handler) use ($client) {
            $this->loopHandler = $handler;

            return $client;
        });
        $client->shouldReceive('registerConnectedEventHandler')->once()->andReturnUsing(function (Closure $handler) use ($client) {
            $this->connectedHandler = $handler;

            return $client;
        });
        $client->shouldReceive('connect')->once()->andReturnUsing(function (ConnectionSettings $settings) {
            $this->settings = $settings;
        });
        $client->shouldReceive('subscribe')->twice()->andReturnUsing(function (string $topicFilter, Closure $callback) {
            $this->subscriptions[$topicFilter] = $callback;
        });
        $client->shouldReceive('loop')->once()->andReturnUsing(fn () => $whileLooping?->__invoke());
        // On its way out the daemon interrupts (on a signal) and disconnects;
        // tests that care set stricter expectations, which replace these.
        $client->shouldReceive('interrupt')->byDefault();
        $client->shouldReceive('disconnect')->byDefault();

        return $client;
    }

    /**
     * A mocked client whose connect() fails like an unreachable broker, after
     * running $duringConnect (e.g. a shutdown signal arriving meanwhile).
     */
    private function unreachableClient(?Closure $duringConnect = null): MockInterface
    {
        $client = Mockery::mock(PhpMqttClient::class);

        $client->shouldReceive('registerLoopEventHandler')->once()->andReturnSelf();
        $client->shouldReceive('registerConnectedEventHandler')->once()->andReturnSelf();
        $client->shouldReceive('connect')->once()->andReturnUsing(function () use ($duringConnect) {
            $duringConnect?->__invoke();

            throw new RuntimeException('Socket error [111]: Connection refused');
        });
        $client->shouldReceive('subscribe')->never();
        $client->shouldReceive('loop')->never();
        $client->shouldReceive('interrupt')->byDefault();
        $client->shouldReceive('disconnect')->byDefault();

        return $client;
    }

    /**
     * Runs the daemon until it stops. Accepts one client, or a closure
     * returning the next client for each (re)connect attempt; returns how
     * many connections it made.
     */
    private function runDaemon(MockInterface|Closure $clients): int
    {
        $nextClient = $clients instanceof Closure ? $clients : fn () => $clients;
        $connects = 0;

        $command = new MqttListen(function (string $clientId, Repository $repository) use ($nextClient, &$connects) {
            // The daemon swallows every exception and reconnects forever, so a
            // mock that fails unexpectedly would spin here instead of failing
            // the test: past a few attempts, hand it a client that just stops.
            if (++$connects > 3) {
                return $this->client();
            }

            $this->repository = $repository;

            return $nextClient();
        });
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

        $this->assertSame(Command::SUCCESS, $command->handle($this->app->make(AccessDecisionService::class)));
        $this->assertLessThanOrEqual(3, $connects, 'The daemon kept reconnecting, so a mocked client must have failed unexpectedly.');

        return $connects;
    }

    /**
     * One stale command, one fresh command, and one pending message that is
     * not a command at all.
     */
    private function addPendingCommands(): void
    {
        $now = now()->getTimestamp();

        $this->repository->addPendingOutgoingMessage($this->pendingCommand(1, accessLogId: 41, issuedAt: $now - 10));
        $this->repository->addPendingOutgoingMessage($this->pendingCommand(2, accessLogId: 42, issuedAt: $now));
        $this->repository->addPendingOutgoingMessage(new PublishedMessage(3, 'parkir/7/status', '{"online":true}', 1, false));
    }

    /**
     * Records which messages are still pending right after the hook under
     * test ran — before the daemon's own shutdown, which drops the rest.
     */
    private function snapshotPending(): void
    {
        $this->pendingAfterHook = array_map(
            fn (PendingMessage $message) => $message->getMessageId(),
            $this->repository->getPendingOutgoingMessagesLastSentBefore(new DateTime('+1 day')),
        );
    }

    private function assertOnlyTheStaleCommandWasDropped(): void
    {
        $this->assertNotContains(1, $this->pendingAfterHook, 'The stale command should have been dropped.');
        $this->assertContains(2, $this->pendingAfterHook, 'A command within its TTL must keep waiting for its PUBACK.');
        $this->assertContains(3, $this->pendingAfterHook, 'Only our own commands may be touched.');

        Log::shouldHaveReceived('error')->with(self::DROPPED_MESSAGE, [
            'access_log_id' => 41,
            'device_id' => 7,
            'action' => 'open',
            'issued_at' => now()->getTimestamp() - 10,
            'reason' => 'stale',
        ])->once();
    }

    private function pendingCommand(int $messageId, int $accessLogId, int $issuedAt): PublishedMessage
    {
        $command = new DeviceCommand(7, DeviceCommandAction::Open, $accessLogId, $issuedAt);

        return new PublishedMessage($messageId, $command->topic(), $command->payload(), 1, false);
    }
}

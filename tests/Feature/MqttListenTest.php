<?php

namespace Tests\Feature;

use App\Console\Commands\MqttListen;
use App\Enums\DeviceCommandAction;
use App\Enums\DeviceMode;
use App\Enums\RfidCardStatus;
use App\Models\AccessLog;
use App\Models\Device;
use App\Models\RfidCard;
use App\Mqtt\DeviceCommand;
use App\Services\AccessDecisionService;
use Closure;
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
use PhpMqtt\Client\PublishedMessage;
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
            $this->subscriptions['parkir/+/status']("parkir/{$device->id}/status", json_encode(['online' => true]));
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

        return $client;
    }

    /**
     * Runs the daemon until a loop() returns normally. Accepts one client, or
     * a closure returning the next client for each (re)connect attempt.
     */
    private function runDaemon(MockInterface|Closure $clients): void
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

    private function assertOnlyTheStaleCommandWasDropped(): void
    {
        $this->assertNull($this->repository->getPendingOutgoingMessage(1), 'The stale command should have been dropped.');
        $this->assertNotNull($this->repository->getPendingOutgoingMessage(2), 'A command within its TTL must keep waiting for its PUBACK.');
        $this->assertNotNull($this->repository->getPendingOutgoingMessage(3), 'Only our own commands may be touched.');

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

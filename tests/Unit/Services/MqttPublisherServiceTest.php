<?php

namespace Tests\Unit\Services;

use App\Enums\DeviceCommandAction;
use App\Mqtt\DeviceCommand;
use App\Mqtt\DeviceCommandNotAcknowledgedException;
use App\Mqtt\StaleDeviceCommandException;
use App\Services\MqttPublisherService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\PublishedMessage;
use Tests\TestCase;

/**
 * Unit tests for MqttPublisherService. The underlying MQTT client is always
 * a Mockery mock of PhpMqtt\Client\Contracts\MqttClient injected via the
 * constructor's $clientFactory — no test here ever opens a real socket or
 * requires a broker to be running.
 */
class MqttPublisherServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mqtt.client_id_prefix' => 'test-prefix',
            'mqtt.username' => '',
            'mqtt.password' => '',
            'mqtt.command_ttl' => 10,
            'mqtt.puback_timeout' => 2,
        ]);
    }

    public function test_it_publishes_the_command_payload_to_its_device_topic_with_qos_1(): void
    {
        $command = $this->freshCommand(deviceId: 42);

        $client = Mockery::mock(MqttClient::class);
        $client->shouldReceive('connect')->once()->withArgs(fn ($settings, $clean) => $clean === true);
        $client->shouldReceive('publish')->once()->withArgs(
            fn (string $topic, string $message, int $qos, bool $retain = false) => $topic === 'parkir/42/command'
                && $qos === 1
                && $retain === false
                && $message === $command->payload()
        );
        $client->shouldReceive('loop')->once();
        $client->shouldReceive('disconnect')->once();

        $service = new MqttPublisherService(fn (string $clientId) => $client);

        $service->publish($command);
    }

    public function test_it_waits_for_the_puback_for_the_configured_number_of_seconds(): void
    {
        config(['mqtt.puback_timeout' => '4']);

        $client = Mockery::mock(MqttClient::class);
        $client->shouldReceive('connect')->once();
        $client->shouldReceive('publish')->once();
        // allowSleep, exitWhenQueuesEmpty, queueWaitLimit: return as soon as
        // the PUBACK is in, or after 4 seconds at the latest.
        $client->shouldReceive('loop')->once()->with(true, true, 4);
        $client->shouldReceive('disconnect')->once();

        $service = new MqttPublisherService(fn (string $clientId) => $client);

        $service->publish($this->freshCommand());
    }

    public function test_it_throws_when_the_broker_never_acknowledges_the_command(): void
    {
        $command = $this->freshCommand();
        $repository = null;

        $client = Mockery::mock(MqttClient::class);
        $client->shouldReceive('connect')->once();
        // What the real client does on a QoS 1 publish(): remember the message
        // as pending until its PUBACK arrives. This mock's loop() never
        // delivers one, just like a broker that stays silent.
        $client->shouldReceive('publish')->once()->andReturnUsing(function () use (&$repository, $command) {
            $repository->addPendingOutgoingMessage(new PublishedMessage(1, $command->topic(), $command->payload(), 1, false));
        });
        $client->shouldReceive('loop')->once();
        $client->shouldReceive('disconnect')->once();

        $service = new MqttPublisherService(function (string $clientId, Repository $given) use (&$repository, $client) {
            $repository = $given;

            return $client;
        });

        $this->expectException(DeviceCommandNotAcknowledgedException::class);

        $service->publish($command);
    }

    public function test_a_command_that_is_already_stale_is_refused_without_connecting(): void
    {
        $command = $this->freshCommand();
        $this->travel(10)->seconds();

        // A factory that fails the test if it's ever called proves the
        // staleness check happens before any connection is attempted.
        $service = new MqttPublisherService(function () {
            $this->fail('The client factory should not be called for a stale command.');
        });

        $this->expectException(StaleDeviceCommandException::class);

        $service->publish($command);
    }

    public function test_a_command_that_goes_stale_while_connecting_is_never_published(): void
    {
        $command = $this->freshCommand();

        $client = Mockery::mock(MqttClient::class);
        // A connect that eats the rest of the TTL, like a slow broker would.
        $client->shouldReceive('connect')->once()->andReturnUsing(fn () => $this->travel(10)->seconds());
        $client->shouldReceive('publish')->never();
        $client->shouldReceive('disconnect')->once();

        $service = new MqttPublisherService(fn (string $clientId) => $client);

        $this->expectException(StaleDeviceCommandException::class);

        $service->publish($command);
    }

    public function test_each_call_gets_a_unique_client_id_built_from_the_configured_prefix(): void
    {
        $seenClientIds = [];

        $client = Mockery::mock(MqttClient::class);
        $client->shouldReceive('connect')->twice();
        $client->shouldReceive('publish')->twice();
        $client->shouldReceive('loop')->twice();
        $client->shouldReceive('disconnect')->twice();

        $service = new MqttPublisherService(function (string $clientId) use (&$seenClientIds, $client) {
            $seenClientIds[] = $clientId;

            return $client;
        });

        $service->publish($this->freshCommand());
        $service->publish($this->freshCommand());

        $this->assertCount(2, $seenClientIds);
        $this->assertNotSame($seenClientIds[0], $seenClientIds[1]);

        foreach ($seenClientIds as $clientId) {
            $this->assertStringStartsWith('test-prefix-publisher-', $clientId);
        }
    }

    public function test_it_still_disconnects_when_publish_throws(): void
    {
        $client = Mockery::mock(MqttClient::class);
        $client->shouldReceive('connect')->once();
        $client->shouldReceive('publish')->once()->andThrow(new \RuntimeException('broker rejected the message'));
        $client->shouldReceive('loop')->never();
        $client->shouldReceive('disconnect')->once();

        $service = new MqttPublisherService(fn (string $clientId) => $client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('broker rejected the message');

        $service->publish($this->freshCommand());
    }

    public function test_blank_configured_credentials_are_passed_as_null(): void
    {
        $settings = $this->publishAndCaptureConnectionSettings();

        $this->assertNull($settings->getUsername());
        $this->assertNull($settings->getPassword());
    }

    public function test_connect_timeout_is_read_from_config_instead_of_the_library_default(): void
    {
        // A string, because that's how an env value always arrives —
        // this also proves the service casts it for ConnectionSettings.
        config(['mqtt.connect_timeout' => '7']);

        $settings = $this->publishAndCaptureConnectionSettings();

        // php-mqtt's own default is 60, so 7 can only come from config.
        $this->assertSame(7, $settings->getConnectTimeout());
    }

    /**
     * Publishes one command through a mocked client and returns the
     * ConnectionSettings the service passed to connect().
     */
    private function publishAndCaptureConnectionSettings(): ConnectionSettings
    {
        $capturedSettings = null;

        $client = Mockery::mock(MqttClient::class);
        $client->shouldReceive('connect')->once()->withArgs(function (?ConnectionSettings $settings) use (&$capturedSettings) {
            $capturedSettings = $settings;

            return true;
        });
        $client->shouldReceive('publish')->once();
        $client->shouldReceive('loop')->once();
        $client->shouldReceive('disconnect')->once();

        $service = new MqttPublisherService(fn (string $clientId) => $client);

        $service->publish($this->freshCommand());

        $this->assertInstanceOf(ConnectionSettings::class, $capturedSettings);

        return $capturedSettings;
    }

    /**
     * A command issued just now, so it's well within its TTL.
     */
    private function freshCommand(int $deviceId = 1): DeviceCommand
    {
        return new DeviceCommand($deviceId, DeviceCommandAction::Open, accessLogId: 99, issuedAt: now()->getTimestamp());
    }
}

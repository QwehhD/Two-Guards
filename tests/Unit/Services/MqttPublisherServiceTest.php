<?php

namespace Tests\Unit\Services;

use App\Services\MqttPublisherService;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient;
use Tests\TestCase;

/**
 * Unit tests for MqttPublisherService (Tahap 9). The underlying MQTT
 * client is always a Mockery mock of PhpMqtt\Client\Contracts\MqttClient
 * injected via the constructor's $clientFactory — no test here ever
 * opens a real socket or requires a broker to be running.
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
        ]);
    }

    public function test_it_publishes_an_open_command_to_the_correct_topic(): void
    {
        $client = Mockery::mock(MqttClient::class);
        $client->shouldReceive('connect')->once()->withArgs(fn ($settings, $clean) => $clean === true);
        $client->shouldReceive('publish')->once()->withArgs(
            fn (string $topic, string $message, int $qos, bool $retain = false) => $topic === 'parkir/42/command'
                && $qos === 1
                && $retain === false
                && json_decode($message, true) === ['action' => 'open']
        );
        $client->shouldReceive('disconnect')->once();

        $service = new MqttPublisherService(fn (string $clientId) => $client);

        $service->publishCommand(42, 'open');
    }

    public function test_it_publishes_a_deny_command(): void
    {
        $client = Mockery::mock(MqttClient::class);
        $client->shouldReceive('connect')->once();
        $client->shouldReceive('publish')->once()->withArgs(
            fn (string $topic, string $message) => $topic === 'parkir/7/command'
                && json_decode($message, true) === ['action' => 'deny']
        );
        $client->shouldReceive('disconnect')->once();

        $service = new MqttPublisherService(fn (string $clientId) => $client);

        $service->publishCommand(7, 'deny');
    }

    public function test_it_rejects_an_unknown_action_without_touching_the_broker(): void
    {
        // A factory that fails the test if it's ever called proves the
        // validation happens before any connection is attempted.
        $service = new MqttPublisherService(function () {
            $this->fail('The client factory should not be called for an invalid action.');
        });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown MQTT command action [bogus]');

        $service->publishCommand(1, 'bogus');
    }

    public function test_each_call_gets_a_unique_client_id_built_from_the_configured_prefix(): void
    {
        $seenClientIds = [];

        $client = Mockery::mock(MqttClient::class);
        $client->shouldReceive('connect')->twice();
        $client->shouldReceive('publish')->twice();
        $client->shouldReceive('disconnect')->twice();

        $service = new MqttPublisherService(function (string $clientId) use (&$seenClientIds, $client) {
            $seenClientIds[] = $clientId;

            return $client;
        });

        $service->publishCommand(1, 'open');
        $service->publishCommand(1, 'open');

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
        $client->shouldReceive('disconnect')->once();

        $service = new MqttPublisherService(fn (string $clientId) => $client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('broker rejected the message');

        $service->publishCommand(1, 'open');
    }

    public function test_blank_configured_credentials_are_passed_as_null(): void
    {
        $capturedSettings = null;

        $client = Mockery::mock(MqttClient::class);
        $client->shouldReceive('connect')->once()->withArgs(function (?ConnectionSettings $settings) use (&$capturedSettings) {
            $capturedSettings = $settings;

            return true;
        });
        $client->shouldReceive('publish')->once();
        $client->shouldReceive('disconnect')->once();

        $service = new MqttPublisherService(fn (string $clientId) => $client);

        $service->publishCommand(1, 'open');

        $this->assertNull($capturedSettings->getUsername());
        $this->assertNull($capturedSettings->getPassword());
    }
}

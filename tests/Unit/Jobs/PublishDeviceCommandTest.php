<?php

namespace Tests\Unit\Jobs;

use App\Enums\DeviceCommandAction;
use App\Jobs\PublishDeviceCommand;
use App\Mqtt\DeviceCommand;
use App\Mqtt\DeviceCommandNotAcknowledgedException;
use App\Mqtt\StaleDeviceCommandException;
use App\Services\MqttPublisherService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use RuntimeException;
use Tests\TestCase;

class PublishDeviceCommandTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mqtt.connect_timeout' => 3,
            'mqtt.puback_timeout' => 2,
            'mqtt.command_ttl' => 10,
        ]);
    }

    public function test_it_runs_on_its_own_queue_with_a_timeout_that_fits_one_attempt(): void
    {
        $job = new PublishDeviceCommand($this->command());

        $this->assertSame('device-commands', $job->queue);
        // connect_timeout (3) + puback_timeout (2) + 3 seconds of margin.
        $this->assertSame(8, $job->timeout);
        $this->assertTrue($job->failOnTimeout);
        $this->assertSame(1, $job->backoff);
    }

    public function test_it_is_only_retried_until_the_command_goes_stale(): void
    {
        $command = $this->command();

        $job = new PublishDeviceCommand($command);

        $this->assertSame($command->staleAt(), $job->retryUntil()->getTimestamp());
    }

    public function test_it_hands_the_command_to_the_publisher(): void
    {
        $command = $this->command();

        $publisher = Mockery::mock(MqttPublisherService::class);
        $publisher->shouldReceive('publish')->once()->with($command);

        $job = (new PublishDeviceCommand($command))->withFakeQueueInteractions();
        $job->handle($publisher);

        $job->assertNotFailed();
        $job->assertNotReleased();
    }

    public function test_a_stale_command_fails_the_job_instead_of_being_retried(): void
    {
        $command = $this->command();

        $publisher = Mockery::mock(MqttPublisherService::class);
        $publisher->shouldReceive('publish')->once()->andThrow(StaleDeviceCommandException::for($command));

        $job = (new PublishDeviceCommand($command))->withFakeQueueInteractions();
        $job->handle($publisher);

        $job->assertFailedWith(StaleDeviceCommandException::class);
    }

    public function test_any_other_publish_failure_is_rethrown_so_the_worker_retries_it(): void
    {
        $command = $this->command();

        $publisher = Mockery::mock(MqttPublisherService::class);
        $publisher->shouldReceive('publish')->once()->andThrow(DeviceCommandNotAcknowledgedException::for($command, 2));

        $job = (new PublishDeviceCommand($command))->withFakeQueueInteractions();

        $this->expectException(DeviceCommandNotAcknowledgedException::class);

        $job->handle($publisher);
    }

    public function test_failing_for_good_logs_which_decision_never_reached_which_device(): void
    {
        Log::spy();

        $job = new PublishDeviceCommand($this->command());
        $job->failed(new RuntimeException('broker went away'));

        Log::shouldHaveReceived('error')->once()->with('PublishDeviceCommand: device command was never delivered.', [
            'access_log_id' => 99,
            'device_id' => 7,
            'action' => 'open',
            'exception' => RuntimeException::class,
            'message' => 'broker went away',
        ]);
    }

    private function command(): DeviceCommand
    {
        return new DeviceCommand(7, DeviceCommandAction::Open, accessLogId: 99, issuedAt: now()->getTimestamp());
    }
}

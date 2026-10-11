<?php

namespace Tests\Feature;

use App\Enums\AccessLogStatus;
use App\Enums\DeviceCommandAction;
use App\Enums\UserRole;
use App\Jobs\PublishDeviceCommand;
use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * The wiring between approve/reject and the device command: which decisions
 * queue a PublishDeviceCommand job, with which command, and what happens
 * when even queueing it fails. Publishing itself is covered by the job's
 * and MqttPublisherService's own tests.
 */
class AccessLogDeviceCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Only the device command job is faked; everything else queued (the
        // broadcast events) still runs on the sync queue as usual.
        Queue::fake([PublishDeviceCommand::class]);
    }

    public function test_approving_a_pending_scan_queues_exactly_one_open_command_for_its_device(): void
    {
        $log = AccessLog::factory()->pending()->create();

        $this->actingAs($this->admin())->postJson("/api/access-logs/{$log->id}/approve")->assertOk();

        Queue::assertPushed(PublishDeviceCommand::class, 1);
        Queue::assertPushedOn('device-commands', PublishDeviceCommand::class, function (PublishDeviceCommand $job) use ($log) {
            return $job->command->deviceId === $log->device_id
                && $job->command->action === DeviceCommandAction::Open
                && $job->command->accessLogId === $log->id
                && $job->command->issuedAt === $log->fresh()->processed_at->getTimestamp();
        });
    }

    public function test_rejecting_a_pending_scan_queues_exactly_one_deny_command_for_its_device(): void
    {
        $log = AccessLog::factory()->pending()->create();

        $this->actingAs($this->admin())->postJson("/api/access-logs/{$log->id}/reject")->assertOk();

        Queue::assertPushed(PublishDeviceCommand::class, 1);
        Queue::assertPushedOn('device-commands', PublishDeviceCommand::class, function (PublishDeviceCommand $job) use ($log) {
            return $job->command->deviceId === $log->device_id
                && $job->command->action === DeviceCommandAction::Deny
                && $job->command->accessLogId === $log->id;
        });
    }

    public function test_approving_a_scan_past_the_pending_timeout_queues_no_command(): void
    {
        $log = AccessLog::factory()->pending()->create([
            'scanned_at' => now()->subSeconds(AccessLog::PENDING_TIMEOUT_SECONDS + 5),
        ]);

        $this->actingAs($this->admin())->postJson("/api/access-logs/{$log->id}/approve")->assertStatus(422);

        // Self-healed to expired: nobody decided in time, so there is no
        // fresh decision to tell the device about.
        $this->assertDatabaseHas('access_logs', ['id' => $log->id, 'status' => AccessLogStatus::Expired->value]);
        Queue::assertNotPushed(PublishDeviceCommand::class);
    }

    public function test_approving_a_scan_that_is_no_longer_pending_queues_no_command(): void
    {
        $log = AccessLog::factory()->manual()->create(['status' => AccessLogStatus::Denied]);

        $this->actingAs($this->admin())->postJson("/api/access-logs/{$log->id}/approve")->assertStatus(422);

        $this->assertDatabaseHas('access_logs', ['id' => $log->id, 'status' => AccessLogStatus::Denied->value]);
        Queue::assertNotPushed(PublishDeviceCommand::class);
    }

    public function test_failing_to_queue_the_command_still_answers_success_and_keeps_the_decision(): void
    {
        Log::spy();

        // A real bus dispatcher whose dispatch() alone blows up, standing in
        // for e.g. the jobs table being unwritable. Everything else it does
        // (running the sync-queued broadcasts) keeps working.
        $dispatcher = Mockery::mock(BusDispatcher::class, [$this->app])->makePartial();
        $dispatcher->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('jobs table is not writable'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        $admin = $this->admin();
        $log = AccessLog::factory()->pending()->create();

        $response = $this->actingAs($admin)->postJson("/api/access-logs/{$log->id}/approve");

        $response->assertOk();
        $response->assertJsonFragment(['id' => $log->id, 'status' => AccessLogStatus::Approved->value]);
        $this->assertDatabaseHas('access_logs', [
            'id' => $log->id,
            'status' => AccessLogStatus::Approved->value,
            'processed_by' => $admin->id,
        ]);
        Log::shouldHaveReceived('error')->with('AccessLogController: failed to queue device command after resolving access log.', [
            'access_log_id' => $log->id,
            'device_id' => $log->device_id,
            'action' => 'open',
            'exception' => RuntimeException::class,
            'message' => 'jobs table is not writable',
        ])->once();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}

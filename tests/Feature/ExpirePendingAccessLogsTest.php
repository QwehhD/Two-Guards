<?php

namespace Tests\Feature;

use App\Enums\AccessLogStatus;
use App\Models\AccessLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpirePendingAccessLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_expires_pending_logs_older_than_the_timeout(): void
    {
        $stale = AccessLog::factory()->pending()->create([
            'scanned_at' => now()->subSeconds(AccessLog::PENDING_TIMEOUT_SECONDS + 5),
        ]);

        $this->artisan('access-logs:expire-pending')->assertSuccessful();

        $this->assertDatabaseHas('access_logs', [
            'id' => $stale->id,
            'status' => AccessLogStatus::Expired->value,
        ]);
    }

    public function test_it_does_not_touch_pending_logs_still_within_the_timeout(): void
    {
        $fresh = AccessLog::factory()->pending()->create([
            'scanned_at' => now()->subSeconds(AccessLog::PENDING_TIMEOUT_SECONDS - 5),
        ]);

        $this->artisan('access-logs:expire-pending')->assertSuccessful();

        $this->assertDatabaseHas('access_logs', [
            'id' => $fresh->id,
            'status' => AccessLogStatus::Pending->value,
        ]);
    }

    public function test_it_does_not_touch_logs_that_are_not_pending(): void
    {
        $approved = AccessLog::factory()->create([
            'status' => AccessLogStatus::Approved,
            'scanned_at' => now()->subSeconds(AccessLog::PENDING_TIMEOUT_SECONDS + 5),
        ]);

        $this->artisan('access-logs:expire-pending')->assertSuccessful();

        $this->assertDatabaseHas('access_logs', [
            'id' => $approved->id,
            'status' => AccessLogStatus::Approved->value,
        ]);
    }
}

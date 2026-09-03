<?php

namespace Tests\Feature;

use App\Enums\AccessLogStatus;
use App\Enums\UserRole;
use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_history(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        AccessLog::factory()->count(2)->create();

        $response = $this->actingAs($admin)->getJson('/api/access-logs');

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    public function test_karyawan_can_view_history(): void
    {
        $karyawan = User::factory()->create(['role' => UserRole::Karyawan]);
        AccessLog::factory()->count(2)->create();

        $response = $this->actingAs($karyawan)->getJson('/api/access-logs');

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    public function test_admin_can_approve_a_pending_manual_scan(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $log = AccessLog::factory()->pending()->create();

        $response = $this->actingAs($admin)->postJson("/api/access-logs/{$log->id}/approve");

        $response->assertOk();
        $this->assertDatabaseHas('access_logs', [
            'id' => $log->id,
            'status' => AccessLogStatus::Approved->value,
            'processed_by' => $admin->id,
        ]);
    }

    public function test_karyawan_can_reject_a_pending_manual_scan(): void
    {
        $karyawan = User::factory()->create(['role' => UserRole::Karyawan]);
        $log = AccessLog::factory()->pending()->create();

        $response = $this->actingAs($karyawan)->postJson("/api/access-logs/{$log->id}/reject");

        $response->assertOk();
        $this->assertDatabaseHas('access_logs', [
            'id' => $log->id,
            'status' => AccessLogStatus::Denied->value,
            'processed_by' => $karyawan->id,
        ]);
    }

    public function test_approving_a_non_manual_scan_is_rejected_with_422(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $log = AccessLog::factory()->create();

        $response = $this->actingAs($admin)->postJson("/api/access-logs/{$log->id}/approve");

        $response->assertStatus(422);
        $this->assertDatabaseHas('access_logs', ['id' => $log->id, 'status' => $log->status->value]);
    }

    public function test_approving_an_already_processed_scan_is_rejected_with_422(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $log = AccessLog::factory()->pending()->create();
        $log->update(['status' => AccessLogStatus::Approved, 'processed_by' => $admin->id]);

        $response = $this->actingAs($admin)->postJson("/api/access-logs/{$log->id}/approve");

        $response->assertStatus(422);
    }

    public function test_unauthenticated_user_cannot_view_history_or_process_scans(): void
    {
        $log = AccessLog::factory()->pending()->create();

        $this->getJson('/api/access-logs')->assertUnauthorized();
        $this->postJson("/api/access-logs/{$log->id}/approve")->assertUnauthorized();
        $this->postJson("/api/access-logs/{$log->id}/reject")->assertUnauthorized();
    }
}

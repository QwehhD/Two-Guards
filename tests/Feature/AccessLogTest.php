<?php

namespace Tests\Feature;

use App\Enums\AccessLogMode;
use App\Enums\AccessLogStatus;
use App\Enums\UserRole;
use App\Models\AccessLog;
use App\Models\Device;
use App\Models\RfidCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $response->assertJsonCount(2, 'data');
    }

    public function test_karyawan_can_view_history(): void
    {
        $karyawan = User::factory()->create(['role' => UserRole::Karyawan]);
        AccessLog::factory()->count(2)->create();

        $response = $this->actingAs($karyawan)->getJson('/api/access-logs');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_history_eager_loads_relations_without_n_plus_1_queries(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        AccessLog::factory()->count(3)->manual()->create();

        $this->assertDatabaseCount('access_logs', 3);

        DB::enableQueryLog();
        $response = $this->actingAs($admin)->getJson('/api/access-logs');
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        // One query for the page of logs, one count query for pagination, and
        // one each for the eager-loaded rfidCard/device/processor relations.
        $this->assertLessThanOrEqual(5, $queryCount);
    }

    public function test_response_shape_is_null_safe_for_unknown_cards(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $log = AccessLog::factory()->unknownCard()->create(['scanned_uid' => 'DEADBEEF']);

        $response = $this->actingAs($admin)->getJson('/api/access-logs');

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $log->id,
            'scanned_uid' => 'DEADBEEF',
            'is_known_card' => false,
            'owner_name' => 'Tidak Dikenal',
        ]);
    }

    public function test_filter_by_status(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        AccessLog::factory()->create(['status' => AccessLogStatus::Approved]);
        $denied = AccessLog::factory()->denied()->create();

        $response = $this->actingAs($admin)->getJson('/api/access-logs?status=denied');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $denied->id]);
    }

    public function test_filter_by_mode(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        AccessLog::factory()->create(['mode' => AccessLogMode::Auto]);
        $manual = AccessLog::factory()->manual()->create();

        $response = $this->actingAs($admin)->getJson('/api/access-logs?mode=manual');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $manual->id]);
    }

    public function test_filter_by_device_id(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $deviceA = Device::factory()->create();
        $deviceB = Device::factory()->create();
        AccessLog::factory()->create(['device_id' => $deviceA->id]);
        $log = AccessLog::factory()->create(['device_id' => $deviceB->id]);

        $response = $this->actingAs($admin)->getJson("/api/access-logs?device_id={$deviceB->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $log->id]);
    }

    public function test_filter_by_date_range(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $inRange = AccessLog::factory()->create(['scanned_at' => '2026-01-15 08:00:00']);
        AccessLog::factory()->create(['scanned_at' => '2026-02-01 08:00:00']);

        $response = $this->actingAs($admin)->getJson('/api/access-logs?date_from=2026-01-01&date_to=2026-01-31');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $inRange->id]);
    }

    public function test_search_matches_owner_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $card = RfidCard::factory()->create(['owner_name' => 'Budi Santoso']);
        $match = AccessLog::factory()->create(['rfid_card_id' => $card->id, 'scanned_uid' => $card->uid]);
        AccessLog::factory()->create();

        $response = $this->actingAs($admin)->getJson('/api/access-logs?search=Budi');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $match->id]);
    }

    public function test_search_matches_scanned_uid(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $match = AccessLog::factory()->unknownCard()->create(['scanned_uid' => 'ABC12345']);
        AccessLog::factory()->unknownCard()->create(['scanned_uid' => 'ZZZ99999']);

        $response = $this->actingAs($admin)->getJson('/api/access-logs?search=ABC123');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $match->id]);
    }

    public function test_pagination_limits_results_per_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        AccessLog::factory()->count(20)->create();

        $response = $this->actingAs($admin)->getJson('/api/access-logs?per_page=5');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
        $response->assertJsonPath('meta.per_page', 5);
        $response->assertJsonPath('meta.total', 20);
    }

    public function test_results_are_ordered_newest_first_by_default(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $older = AccessLog::factory()->create(['scanned_at' => now()->subDay()]);
        $newer = AccessLog::factory()->create(['scanned_at' => now()]);

        $response = $this->actingAs($admin)->getJson('/api/access-logs');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertSame([$newer->id, $older->id], $ids->all());
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

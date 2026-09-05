<?php

namespace Tests\Feature;

use App\Enums\AccessLogMode;
use App\Enums\AccessLogStatus;
use App\Enums\DeviceMode;
use App\Enums\UserRole;
use App\Models\Device;
use App\Models\RfidCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the DEVELOPMENT-ONLY simulate-scan endpoint (Tahap 6).
 */
class SimulateScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_mode_with_valid_card_is_approved(): void
    {
        $user = User::factory()->create(['role' => UserRole::Karyawan]);
        $device = Device::factory()->create(['mode' => DeviceMode::Auto]);
        $card = RfidCard::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/access-logs/simulate-scan', [
            'device_id' => $device->id,
            'uid' => $card->uid,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('access_logs', [
            'device_id' => $device->id,
            'rfid_card_id' => $card->id,
            'scanned_uid' => $card->uid,
            'mode' => AccessLogMode::Auto->value,
            'status' => AccessLogStatus::Approved->value,
        ]);
    }

    public function test_auto_mode_with_unknown_card_is_denied(): void
    {
        $user = User::factory()->create(['role' => UserRole::Karyawan]);
        $device = Device::factory()->create(['mode' => DeviceMode::Auto]);

        $response = $this->actingAs($user)->postJson('/api/access-logs/simulate-scan', [
            'device_id' => $device->id,
            'uid' => 'UNKNOWN99',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('access_logs', [
            'device_id' => $device->id,
            'rfid_card_id' => null,
            'scanned_uid' => 'UNKNOWN99',
            'mode' => AccessLogMode::Auto->value,
            'status' => AccessLogStatus::Denied->value,
        ]);
    }

    public function test_auto_mode_with_inactive_card_is_denied(): void
    {
        $user = User::factory()->create(['role' => UserRole::Karyawan]);
        $device = Device::factory()->create(['mode' => DeviceMode::Auto]);
        $card = RfidCard::factory()->inactive()->create();

        $response = $this->actingAs($user)->postJson('/api/access-logs/simulate-scan', [
            'device_id' => $device->id,
            'uid' => $card->uid,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('access_logs', [
            'rfid_card_id' => $card->id,
            'status' => AccessLogStatus::Denied->value,
        ]);
    }

    public function test_manual_mode_with_valid_card_is_pending(): void
    {
        $user = User::factory()->create(['role' => UserRole::Karyawan]);
        $device = Device::factory()->create(['mode' => DeviceMode::Manual]);
        $card = RfidCard::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/access-logs/simulate-scan', [
            'device_id' => $device->id,
            'uid' => $card->uid,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('access_logs', [
            'rfid_card_id' => $card->id,
            'mode' => AccessLogMode::Manual->value,
            'status' => AccessLogStatus::Pending->value,
            'processed_by' => null,
        ]);
    }

    public function test_manual_mode_with_unknown_card_is_still_pending(): void
    {
        $user = User::factory()->create(['role' => UserRole::Karyawan]);
        $device = Device::factory()->create(['mode' => DeviceMode::Manual]);

        $response = $this->actingAs($user)->postJson('/api/access-logs/simulate-scan', [
            'device_id' => $device->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('access_logs', [
            'device_id' => $device->id,
            'rfid_card_id' => null,
            'mode' => AccessLogMode::Manual->value,
            'status' => AccessLogStatus::Pending->value,
        ]);
    }

    public function test_omitting_uid_generates_a_random_uid(): void
    {
        $user = User::factory()->create(['role' => UserRole::Karyawan]);
        $device = Device::factory()->create(['mode' => DeviceMode::Auto]);

        $response = $this->actingAs($user)->postJson('/api/access-logs/simulate-scan', [
            'device_id' => $device->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('access_logs', 1);
        $generatedUid = $response->json('scanned_uid');
        $this->assertNotEmpty($generatedUid);
    }

    public function test_unauthenticated_user_cannot_simulate_a_scan(): void
    {
        $device = Device::factory()->create();

        $this->postJson('/api/access-logs/simulate-scan', [
            'device_id' => $device->id,
        ])->assertUnauthorized();
    }

    public function test_unknown_device_id_is_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::Karyawan]);

        $response = $this->actingAs($user)->postJson('/api/access-logs/simulate-scan', [
            'device_id' => 999999,
        ]);

        $response->assertStatus(422);
    }

    public function test_endpoint_is_unavailable_outside_local_and_testing_environments(): void
    {
        $user = User::factory()->create(['role' => UserRole::Karyawan]);
        $device = Device::factory()->create();

        app()->instance('env', 'production');

        try {
            $response = $this->actingAs($user)->postJson('/api/access-logs/simulate-scan', [
                'device_id' => $device->id,
            ]);

            $response->assertNotFound();
        } finally {
            app()->instance('env', 'testing');
        }
    }
}

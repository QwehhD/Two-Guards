<?php

namespace Tests\Feature;

use App\Enums\AccessLogMode;
use App\Enums\AccessLogStatus;
use App\Enums\DeviceMode;
use App\Enums\DeviceStatus;
use App\Enums\PortalStatus;
use App\Models\AccessLog;
use App\Models\Device;
use App\Models\RfidCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public landing page's two endpoints (Tahap 7) are the only routes in
 * the whole app meant to work without authentication. These tests exist
 * specifically to guard that "public means public" in two directions:
 * the routes must be reachable with no session at all, AND their
 * responses must never carry anything that could identify a person or a
 * staff member's decision — regardless of what AccessLogResource (the
 * admin/karyawan-facing resource) looks like now or in the future.
 */
class PublicPortalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_status_is_reachable_without_authentication(): void
    {
        Device::factory()->create([
            'name' => 'Portal Utama',
            'status' => DeviceStatus::Online,
            'portal_status' => PortalStatus::Closed,
            'mode' => DeviceMode::Auto,
        ]);

        $response = $this->getJson('/api/public/portal-status');

        $response->assertOk();
        $response->assertJson([
            'data' => [
                [
                    'name' => 'Portal Utama',
                    'status' => 'online',
                    'portal_status' => 'closed',
                    'mode' => 'auto',
                ],
            ],
        ]);
    }

    public function test_portal_status_response_never_contains_access_log_data(): void
    {
        $device = Device::factory()->create();
        $card = RfidCard::factory()->create(['owner_name' => 'Budi Sensitif Rahasia', 'uid' => 'SECRETUID99']);
        $staff = User::factory()->create(['name' => 'Petugas Rahasia']);

        AccessLog::factory()->create([
            'device_id' => $device->id,
            'rfid_card_id' => $card->id,
            'scanned_uid' => 'SECRETUID99',
            'processed_by' => $staff->id,
        ]);

        $response = $this->getJson('/api/public/portal-status');

        $response->assertOk();
        $this->assertResponseHasNoSensitiveFields($response->getContent());
    }

    public function test_recent_activity_is_reachable_without_authentication(): void
    {
        AccessLog::factory()->create([
            'status' => AccessLogStatus::Approved,
            'mode' => AccessLogMode::Auto,
            'scanned_at' => now()->subMinutes(2),
        ]);

        $response = $this->getJson('/api/public/recent-activity');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonStructure([
            'data' => [
                ['status', 'mode', 'scanned_at'],
            ],
        ]);
    }

    public function test_recent_activity_returns_only_the_five_most_recent_entries(): void
    {
        AccessLog::factory()->count(7)->sequence(
            fn ($sequence) => ['scanned_at' => now()->subMinutes(10 - $sequence->index)],
        )->create();

        $response = $this->getJson('/api/public/recent-activity');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    public function test_recent_activity_response_never_contains_sensitive_fields(): void
    {
        $device = Device::factory()->create();
        $card = RfidCard::factory()->create(['owner_name' => 'Siti Pemilik Kartu', 'uid' => 'ABCD1234']);
        $staff = User::factory()->create(['name' => 'Andi Petugas']);

        AccessLog::factory()->create([
            'device_id' => $device->id,
            'rfid_card_id' => $card->id,
            'scanned_uid' => 'ABCD1234',
            'processed_by' => $staff->id,
            'status' => AccessLogStatus::Approved,
            'mode' => AccessLogMode::Manual,
        ]);

        $response = $this->getJson('/api/public/recent-activity');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertResponseHasNoSensitiveFields($content);
        $this->assertStringNotContainsString('Siti Pemilik Kartu', $content);
        $this->assertStringNotContainsString('Andi Petugas', $content);
        $this->assertStringNotContainsString('ABCD1234', $content);
    }

    /**
     * Shared guard used by both endpoints' leak tests: none of these keys
     * (in any casing/quoting JSON would produce them) may ever appear in
     * a public response body. This is intentionally a blunt substring
     * check rather than a structure assertion — the point is to fail
     * loudly if these fields sneak back in through any path, including
     * one nobody thought to write a more specific test for.
     */
    private function assertResponseHasNoSensitiveFields(string $jsonContent): void
    {
        foreach (['owner_name', 'uid', 'scanned_uid', 'processed_by'] as $sensitiveField) {
            $this->assertStringNotContainsString(
                $sensitiveField,
                $jsonContent,
                "Public API response leaked the sensitive field [{$sensitiveField}]."
            );
        }
    }
}

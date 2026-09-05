<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_devices(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Device::factory()->count(2)->create();

        $response = $this->actingAs($admin)->getJson('/api/devices');

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    public function test_karyawan_can_list_devices(): void
    {
        $karyawan = User::factory()->create(['role' => UserRole::Karyawan]);
        Device::factory()->create(['name' => 'Portal Depan']);

        $response = $this->actingAs($karyawan)->getJson('/api/devices');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Portal Depan']);
    }

    public function test_unauthenticated_user_cannot_list_devices(): void
    {
        $this->getJson('/api/devices')->assertUnauthorized();
    }
}

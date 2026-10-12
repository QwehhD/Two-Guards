<?php

namespace Tests\Feature;

use App\Enums\DeviceMode;
use App\Enums\DeviceStatus;
use App\Enums\UserRole;
use App\Events\PortalStatusUpdated;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeviceModeTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('modeSwitches')]
    public function test_admin_can_switch_a_device_mode(DeviceMode $from, DeviceMode $to): void
    {
        Event::fake([PortalStatusUpdated::class]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $device = Device::factory()->create(['mode' => $from]);

        $this->actingAs($admin)->patchJson("/api/devices/{$device->id}", ['mode' => $to->value])
            ->assertOk()
            ->assertJson(['id' => $device->id, 'mode' => $to->value]);

        $this->assertSame($to, $device->fresh()->mode);
        // The public landing page shows each device's mode too.
        Event::assertDispatched(PortalStatusUpdated::class);
    }

    public static function modeSwitches(): array
    {
        return [
            'auto to manual' => [DeviceMode::Auto, DeviceMode::Manual],
            'manual to auto' => [DeviceMode::Manual, DeviceMode::Auto],
        ];
    }

    public function test_karyawan_cannot_switch_a_device_mode(): void
    {
        Event::fake([PortalStatusUpdated::class]);

        $karyawan = User::factory()->create(['role' => UserRole::Karyawan]);
        $device = Device::factory()->create(['mode' => DeviceMode::Manual]);

        $this->actingAs($karyawan)->patchJson("/api/devices/{$device->id}", ['mode' => 'auto'])
            ->assertForbidden();

        $this->assertSame(DeviceMode::Manual, $device->fresh()->mode);
        Event::assertNotDispatched(PortalStatusUpdated::class);
    }

    public function test_unauthenticated_user_cannot_switch_a_device_mode(): void
    {
        $device = Device::factory()->create();

        $this->patchJson("/api/devices/{$device->id}", ['mode' => 'manual'])->assertUnauthorized();
    }

    public function test_an_unknown_mode_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $device = Device::factory()->create(['mode' => DeviceMode::Auto]);

        $this->actingAs($admin)->patchJson("/api/devices/{$device->id}", ['mode' => 'semi-auto'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mode');

        $this->assertSame(DeviceMode::Auto, $device->fresh()->mode);
    }

    public function test_only_the_mode_can_be_changed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $device = Device::factory()->create([
            'name' => 'Portal Gerbang Utama',
            'mode' => DeviceMode::Auto,
            'status' => DeviceStatus::Online,
        ]);

        // Name and status belong to the physical device and its heartbeat.
        $this->actingAs($admin)->patchJson("/api/devices/{$device->id}", [
            'mode' => 'manual',
            'name' => 'Diganti',
            'status' => 'offline',
        ])->assertOk();

        $device->refresh();
        $this->assertSame(DeviceMode::Manual, $device->mode);
        $this->assertSame('Portal Gerbang Utama', $device->name);
        $this->assertSame(DeviceStatus::Online, $device->status);
    }
}

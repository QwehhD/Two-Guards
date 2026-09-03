<?php

namespace Tests\Feature;

use App\Enums\RfidCardStatus;
use App\Enums\UserRole;
use App\Models\RfidCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfidCardManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_rfid_cards(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        RfidCard::factory()->count(2)->create();

        $response = $this->actingAs($admin)->getJson('/api/rfid-cards');

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    public function test_karyawan_cannot_list_rfid_cards(): void
    {
        $karyawan = User::factory()->create(['role' => UserRole::Karyawan]);

        $response = $this->actingAs($karyawan)->getJson('/api/rfid-cards');

        $response->assertForbidden();
    }

    public function test_admin_can_create_an_rfid_card(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->postJson('/api/rfid-cards', [
            'uid' => 'ABC12345',
            'owner_name' => 'Budi Karyawan',
            'status' => RfidCardStatus::Active->value,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('rfid_cards', [
            'uid' => 'ABC12345',
            'created_by' => $admin->id,
        ]);
    }

    public function test_karyawan_cannot_create_an_rfid_card(): void
    {
        $karyawan = User::factory()->create(['role' => UserRole::Karyawan]);

        $response = $this->actingAs($karyawan)->postJson('/api/rfid-cards', [
            'uid' => 'ABC12345',
            'owner_name' => 'Budi Karyawan',
            'status' => RfidCardStatus::Active->value,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('rfid_cards', ['uid' => 'ABC12345']);
    }

    public function test_admin_can_update_an_rfid_card(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $card = RfidCard::factory()->create();

        $response = $this->actingAs($admin)->putJson("/api/rfid-cards/{$card->id}", [
            'uid' => $card->uid,
            'owner_name' => 'Updated Owner',
            'status' => RfidCardStatus::Inactive->value,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('rfid_cards', [
            'id' => $card->id,
            'owner_name' => 'Updated Owner',
            'status' => RfidCardStatus::Inactive->value,
        ]);
    }

    public function test_karyawan_cannot_update_an_rfid_card(): void
    {
        $karyawan = User::factory()->create(['role' => UserRole::Karyawan]);
        $card = RfidCard::factory()->create();

        $response = $this->actingAs($karyawan)->putJson("/api/rfid-cards/{$card->id}", [
            'uid' => $card->uid,
            'owner_name' => 'Updated Owner',
            'status' => RfidCardStatus::Inactive->value,
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_delete_an_rfid_card(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $card = RfidCard::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/rfid-cards/{$card->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('rfid_cards', ['id' => $card->id]);
    }

    public function test_karyawan_cannot_delete_an_rfid_card(): void
    {
        $karyawan = User::factory()->create(['role' => UserRole::Karyawan]);
        $card = RfidCard::factory()->create();

        $response = $this->actingAs($karyawan)->deleteJson("/api/rfid-cards/{$card->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('rfid_cards', ['id' => $card->id]);
    }
}

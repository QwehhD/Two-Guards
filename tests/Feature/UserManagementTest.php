<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        User::factory()->count(2)->create(['role' => UserRole::Karyawan]);

        $response = $this->actingAs($admin)->getJson('/api/users');

        $response->assertOk();
        $response->assertJsonCount(3);
    }

    public function test_karyawan_cannot_list_users(): void
    {
        $karyawan = User::factory()->create(['role' => UserRole::Karyawan]);

        $response = $this->actingAs($karyawan)->getJson('/api/users');

        $response->assertForbidden();
    }

    public function test_admin_can_create_a_karyawan_account(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Budi Karyawan',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UserRole::Karyawan->value,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'budi@example.com',
            'role' => UserRole::Karyawan->value,
        ]);
    }

    public function test_karyawan_cannot_create_a_user(): void
    {
        $karyawan = User::factory()->create(['role' => UserRole::Karyawan]);

        $response = $this->actingAs($karyawan)->postJson('/api/users', [
            'name' => 'Budi Karyawan',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UserRole::Karyawan->value,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'budi@example.com']);
    }

    public function test_admin_can_update_a_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $target = User::factory()->create(['role' => UserRole::Karyawan]);

        $response = $this->actingAs($admin)->putJson("/api/users/{$target->id}", [
            'name' => 'Updated Name',
            'email' => $target->email,
            'role' => UserRole::Karyawan->value,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_karyawan_cannot_update_a_user(): void
    {
        $karyawan = User::factory()->create(['role' => UserRole::Karyawan]);
        $target = User::factory()->create(['role' => UserRole::Karyawan]);

        $response = $this->actingAs($karyawan)->putJson("/api/users/{$target->id}", [
            'name' => 'Updated Name',
            'email' => $target->email,
            'role' => UserRole::Karyawan->value,
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_delete_a_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $target = User::factory()->create(['role' => UserRole::Karyawan]);

        $response = $this->actingAs($admin)->deleteJson("/api/users/{$target->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_karyawan_cannot_delete_a_user(): void
    {
        $karyawan = User::factory()->create(['role' => UserRole::Karyawan]);
        $target = User::factory()->create(['role' => UserRole::Karyawan]);

        $response = $this->actingAs($karyawan)->deleteJson("/api/users/{$target->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }
}

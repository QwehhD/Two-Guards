<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Exceptions\LastAdminException;
use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_admin_can_change_a_karyawan_password(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $target = User::factory()->create(['role' => UserRole::Karyawan]);

        // What the Manajemen Akun page sends: the account's current name,
        // email and role, plus the new password and its confirmation.
        $response = $this->actingAs($admin)->putJson("/api/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'role' => UserRole::Karyawan->value,
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ]);

        $response->assertOk();
        $response->assertJsonMissingPath('password');
        $this->assertTrue(Hash::check('password-baru-123', $target->fresh()->password));
        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => UserRole::Karyawan->value]);
    }

    public function test_a_new_password_must_be_confirmed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $target = User::factory()->create(['role' => UserRole::Karyawan]);
        $before = $target->password;

        $response = $this->actingAs($admin)->putJson("/api/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'role' => UserRole::Karyawan->value,
            'password' => 'password-baru-123',
            'password_confirmation' => 'salah-ketik-123',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('password');
        $this->assertSame($before, $target->fresh()->password);
    }

    public function test_updating_without_a_password_keeps_the_current_one(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $target = User::factory()->create(['role' => UserRole::Karyawan]);
        $before = $target->password;

        $this->actingAs($admin)->putJson("/api/users/{$target->id}", [
            'name' => 'Nama Baru',
            'email' => $target->email,
            'role' => UserRole::Karyawan->value,
        ])->assertOk();

        $this->assertSame($before, $target->fresh()->password);
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

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        // A second admin, so it's the self-delete rule doing the refusing.
        User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->deleteJson("/api/users/{$admin->id}");

        $response->assertForbidden();
        $response->assertJsonFragment(['message' => 'Anda tidak bisa menghapus akun Anda sendiri.']);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_delete_another_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $otherAdmin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->deleteJson("/api/users/{$otherAdmin->id}")->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $otherAdmin->id]);
    }

    public function test_deleting_an_account_keeps_the_access_logs_it_processed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $karyawan = User::factory()->create(['role' => UserRole::Karyawan]);
        $log = AccessLog::factory()->pending()->create();
        $log->update(['status' => 'approved', 'processed_by' => $karyawan->id, 'processed_at' => now()]);

        $this->actingAs($admin)->deleteJson("/api/users/{$karyawan->id}")->assertNoContent();

        // The history stays; only "processed by" is forgotten.
        $this->assertDatabaseHas('access_logs', ['id' => $log->id, 'status' => 'approved', 'processed_by' => null]);
    }

    public function test_the_last_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->putJson("/api/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => UserRole::Karyawan->value,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['role' => 'Admin terakhir tidak bisa diturunkan menjadi karyawan.']);
        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    public function test_an_admin_can_be_demoted_while_another_admin_remains(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $otherAdmin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->putJson("/api/users/{$otherAdmin->id}", [
            'name' => $otherAdmin->name,
            'email' => $otherAdmin->email,
            'role' => UserRole::Karyawan->value,
        ])->assertOk();

        $this->assertSame(UserRole::Karyawan, $otherAdmin->fresh()->role);
    }

    public function test_the_last_admin_cannot_be_deleted_even_outside_the_api(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->expectException(LastAdminException::class);

        try {
            $admin->delete();
        } finally {
            $this->assertDatabaseHas('users', ['id' => $admin->id]);
        }
    }

    public function test_the_last_admin_cannot_be_demoted_even_outside_the_api(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->expectException(LastAdminException::class);

        try {
            $admin->update(['role' => UserRole::Karyawan]);
        } finally {
            $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => UserRole::Admin->value]);
        }
    }
}

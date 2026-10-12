<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Exceptions\LastAdminException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Deleting an account only soft deletes it: it can't be used any more, but
 * the row stays so the audit trail keeps its name.
 */
class DeletedAccountTest extends TestCase
{
    use RefreshDatabase;

    private const DELETED_EMAIL_MESSAGE = 'Email ini masih tercatat untuk akun yang sudah dihapus. Gunakan email lain.';

    public function test_a_deleted_account_cannot_log_in(): void
    {
        User::factory()->create(['email' => 'pergi@example.test'])->delete();

        $response = $this->postJson('/login', ['email' => 'pergi@example.test', 'password' => 'password']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_an_active_session_ends_when_the_account_is_deleted(): void
    {
        // Sent the way the SPA sends them, so Sanctum authenticates from the session.
        config(['sanctum.stateful' => ['localhost']]);
        $karyawan = User::factory()->create();
        $session = [Auth::guard('web')->getName() => $karyawan->id];

        $this->withSession($session)->withHeader('Referer', 'http://localhost/dashboard')
            ->getJson('/api/me')
            ->assertOk();

        $karyawan->delete();
        // Every real request starts with fresh guards; only the session carries over.
        $this->app['auth']->forgetGuards();

        $this->withSession($session)->withHeader('Referer', 'http://localhost/dashboard')
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_the_account_page_lists_only_active_accounts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'email' => 'admin@example.test']);
        User::factory()->create(['email' => 'aktif@example.test']);
        User::factory()->create(['email' => 'dihapus@example.test'])->delete();

        $response = $this->actingAs($admin)->getJson('/api/users');

        $response->assertOk();
        $this->assertEqualsCanonicalizing(['admin@example.test', 'aktif@example.test'], array_column($response->json(), 'email'));
    }

    public function test_a_deleted_account_cannot_be_deleted_again(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $gone = User::factory()->create();
        $gone->delete();

        $this->actingAs($admin)->deleteJson("/api/users/{$gone->id}")->assertNotFound();
    }

    public function test_deleted_admins_do_not_count_towards_keeping_one_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        // Allowed: there were two admins when this one was deleted.
        User::factory()->create(['role' => UserRole::Admin])->delete();

        // The only admin who can still log in is the last admin.
        $this->assertTrue($admin->isLastAdmin());

        $this->actingAs($admin)->putJson("/api/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => UserRole::Karyawan->value,
        ])->assertUnprocessable()->assertJsonValidationErrors(['role' => 'Admin terakhir tidak bisa diturunkan menjadi karyawan.']);

        $this->expectException(LastAdminException::class);

        $admin->delete();
    }

    public function test_a_new_account_cannot_take_the_email_of_a_deleted_account(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        User::factory()->create(['email' => 'lama@example.test'])->delete();

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Pegawai Baru',
            'email' => 'lama@example.test',
            'role' => UserRole::Karyawan->value,
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ]);

        // A clear validation error instead of a 500 from the unique index.
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email' => self::DELETED_EMAIL_MESSAGE]);
        $this->assertSame(1, User::withTrashed()->where('email', 'lama@example.test')->count());
    }

    public function test_an_account_cannot_switch_to_the_email_of_a_deleted_account(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $karyawan = User::factory()->create();
        User::factory()->create(['email' => 'lama@example.test'])->delete();

        $this->actingAs($admin)->putJson("/api/users/{$karyawan->id}", [
            'name' => $karyawan->name,
            'email' => 'lama@example.test',
            'role' => UserRole::Karyawan->value,
        ])->assertUnprocessable()->assertJsonValidationErrors(['email' => self::DELETED_EMAIL_MESSAGE]);

        $this->actingAs($karyawan)->patchJson('/api/profile', [
            'name' => $karyawan->name,
            'email' => 'lama@example.test',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email' => self::DELETED_EMAIL_MESSAGE]);
    }

    public function test_an_active_accounts_email_still_gets_the_usual_message(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        User::factory()->create(['email' => 'aktif@example.test']);

        $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Pegawai Baru',
            'email' => 'aktif@example.test',
            'role' => UserRole::Karyawan->value,
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
    }
}

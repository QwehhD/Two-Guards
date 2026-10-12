<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MakeAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    private const QUESTION = 'Make [karyawan@example.test] an admin? Admins can create, delete and reset the password of every account.';

    public function test_it_promotes_an_account_after_confirmation_when_no_admin_is_left(): void
    {
        $karyawan = User::factory()->create(['email' => 'karyawan@example.test']);

        $this->artisan('user:make-admin', ['email' => 'karyawan@example.test'])
            ->expectsOutputToContain('Active admins right now: 0.')
            ->expectsConfirmation(self::QUESTION, 'yes')
            ->expectsOutputToContain('[karyawan@example.test] is now an admin.')
            ->assertSuccessful();

        $this->assertSame(UserRole::Admin, $karyawan->fresh()->role);
    }

    public function test_declining_the_confirmation_changes_nothing(): void
    {
        $karyawan = User::factory()->create(['email' => 'karyawan@example.test']);

        $this->artisan('user:make-admin', ['email' => 'karyawan@example.test'])
            ->expectsConfirmation(self::QUESTION, 'no')
            ->expectsOutputToContain('Nothing changed.')
            ->assertFailed();

        $this->assertSame(UserRole::Karyawan, $karyawan->fresh()->role);
    }

    public function test_an_account_that_is_already_an_admin_is_left_alone(): void
    {
        User::factory()->create(['email' => 'admin@example.test', 'role' => UserRole::Admin]);

        $this->artisan('user:make-admin', ['email' => 'admin@example.test'])
            ->expectsOutputToContain('[admin@example.test] is already an admin; nothing changed.')
            ->assertSuccessful();
    }

    public function test_an_unknown_email_fails_without_asking_anything(): void
    {
        $this->artisan('user:make-admin', ['email' => 'nobody@example.test'])
            ->expectsOutputToContain('No account with the email [nobody@example.test].')
            ->assertFailed();
    }

    public function test_a_deleted_account_cannot_be_made_an_admin(): void
    {
        User::factory()->create(['email' => 'karyawan@example.test'])->delete();

        $this->artisan('user:make-admin', ['email' => 'karyawan@example.test'])
            ->expectsOutputToContain("has been deleted; a deleted account can't be made an admin.")
            ->assertFailed();

        $this->assertSame(UserRole::Karyawan, User::withTrashed()->sole()->role);
    }

    public function test_it_refuses_to_run_without_interaction(): void
    {
        $karyawan = User::factory()->create(['email' => 'karyawan@example.test']);

        $this->artisan('user:make-admin', ['email' => 'karyawan@example.test', '--no-interaction' => true])
            ->expectsOutputToContain('needs an interactive confirmation')
            ->assertFailed();

        $this->assertSame(UserRole::Karyawan, $karyawan->fresh()->role);
    }
}

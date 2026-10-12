<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetUserPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_a_new_password_asked_for_interactively(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.test', 'role' => UserRole::Admin]);

        $this->artisan('user:set-password', ['email' => 'admin@example.test'])
            ->expectsQuestion('New password', 'Darurat-Baru-123')
            ->expectsQuestion('Confirm the new password', 'Darurat-Baru-123')
            ->expectsOutputToContain('Password for [admin@example.test] (admin) has been changed.')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('Darurat-Baru-123', $admin->fresh()->password));
        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    public function test_an_unknown_email_fails_without_asking_anything(): void
    {
        $this->artisan('user:set-password', ['email' => 'nobody@example.test'])
            ->expectsOutputToContain('No account with the email [nobody@example.test].')
            ->assertFailed();
    }

    public function test_a_deleted_account_gets_a_clear_message(): void
    {
        User::factory()->create(['email' => 'pergi@example.test'])->delete();

        $this->artisan('user:set-password', ['email' => 'pergi@example.test'])
            ->expectsOutputToContain("The account [pergi@example.test] has been deleted; a deleted account can't log in, so its password isn't set.")
            ->assertFailed();
    }

    public function test_a_mismatched_confirmation_changes_nothing(): void
    {
        $user = User::factory()->create(['email' => 'karyawan@example.test']);

        $this->artisan('user:set-password', ['email' => 'karyawan@example.test'])
            ->expectsQuestion('New password', 'Darurat-Baru-123')
            ->expectsQuestion('Confirm the new password', 'salah-ketik-123')
            ->expectsOutputToContain('confirmation does not match')
            ->assertFailed();

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_the_api_password_rules_apply(): void
    {
        $user = User::factory()->create(['email' => 'karyawan@example.test']);

        $this->artisan('user:set-password', ['email' => 'karyawan@example.test'])
            ->expectsQuestion('New password', 'pendek')
            ->expectsQuestion('Confirm the new password', 'pendek')
            ->expectsOutputToContain('at least 8 characters')
            ->assertFailed();

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_it_refuses_to_run_without_interaction(): void
    {
        $user = User::factory()->create(['email' => 'karyawan@example.test']);

        $this->artisan('user:set-password', ['email' => 'karyawan@example.test', '--no-interaction' => true])
            ->expectsOutputToContain('asked for interactively')
            ->assertFailed();

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_the_password_cannot_be_given_on_the_command_line(): void
    {
        $definition = $this->app->make(Kernel::class)->all()['user:set-password']->getDefinition();

        // Only the email: a password argument or option would land in the
        // shell history and the process list.
        $this->assertSame(['email'], array_keys($definition->getArguments()));
        $this->assertFalse($definition->hasOption('password'));
    }
}

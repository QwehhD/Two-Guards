<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * The way back when no admin is left to manage accounts (normally impossible:
 * the last admin can't be deleted or demoted, but two admins demoting each
 * other at the same moment, or a direct database edit, could still get
 * there). Promotes an existing, not deleted account after an interactive
 * confirmation; pair it with user:set-password if the password is unknown.
 */
class MakeAdmin extends Command
{
    protected $signature = 'user:make-admin {email : Email of the account to promote}';

    protected $description = 'Give an existing account the admin role (asks for confirmation)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error(User::onlyTrashed()->where('email', $email)->exists()
                ? "The account [{$email}] has been deleted; a deleted account can't be made an admin."
                : "No account with the email [{$email}].");

            return self::FAILURE;
        }

        if ($user->role === UserRole::Admin) {
            $this->info("[{$user->email}] is already an admin; nothing changed.");

            return self::SUCCESS;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Promoting an account needs an interactive confirmation; run this without --no-interaction.');

            return self::FAILURE;
        }

        $admins = User::query()->where('role', UserRole::Admin)->count();
        $this->line("Account: {$user->name} <{$user->email}>, currently {$user->role->value}. Active admins right now: {$admins}.");

        if (! $this->confirm("Make [{$user->email}] an admin? Admins can create, delete and reset the password of every account.")) {
            $this->warn('Nothing changed.');

            return self::FAILURE;
        }

        $user->update(['role' => UserRole::Admin]);

        $this->info("[{$user->email}] is now an admin.");

        return self::SUCCESS;
    }
}

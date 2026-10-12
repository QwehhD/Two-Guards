<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * The way back in when an admin has forgotten their password: there is no
 * public password reset, and only an admin can reset passwords on the Akun
 * page. The new password is asked for interactively and never taken as an
 * argument, so it ends up in neither the shell history nor the process list.
 */
class SetUserPassword extends Command
{
    use PasswordValidationRules;

    protected $signature = 'user:set-password {email : Email of the account whose password to set}';

    protected $description = 'Set a new password for an account (asked for interactively)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error(User::onlyTrashed()->where('email', $email)->exists()
                ? "The account [{$email}] has been deleted; a deleted account can't log in, so its password isn't set."
                : "No account with the email [{$email}].");

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->error('The new password is asked for interactively; run this without --no-interaction.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('New password');
        $confirmation = (string) $this->secret('Confirm the new password');

        // The same rules as the API applies to every other password.
        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => $this->passwordRules()],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user->update(['password' => $password]);

        $this->info("Password for [{$user->email}] ({$user->role->value}) has been changed.");

        return self::SUCCESS;
    }
}

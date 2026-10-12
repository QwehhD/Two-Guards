<?php

namespace App\Concerns;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, Closure|ValidationRule|Unique|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, Closure|ValidationRule|Unique|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            // Taken by an active account: the usual "already taken" message.
            $userId === null
                ? Rule::unique(User::class)->withoutTrashed()
                : Rule::unique(User::class)->ignore($userId)->withoutTrashed(),
            // A deleted account keeps its row, and so its email, for the
            // audit trail; without this the unique index on users.email
            // would turn the insert into a 500. No name in the message: the
            // same rule guards a user editing their own profile.
            function (string $attribute, mixed $value, Closure $fail) use ($userId): void {
                if (! is_string($value)) {
                    return;
                }

                $takenByDeletedAccount = User::onlyTrashed()
                    ->where('email', $value)
                    ->when($userId !== null, fn ($query) => $query->whereKeyNot($userId))
                    ->exists();

                if ($takenByDeletedAccount) {
                    $fail('Email ini masih tercatat untuk akun yang sudah dihapus. Gunakan email lain.');
                }
            },
        ];
    }
}

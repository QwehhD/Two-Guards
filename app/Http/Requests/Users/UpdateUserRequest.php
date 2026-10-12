<?php

namespace App\Http\Requests\Users;

use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Only admins may update employee accounts (also enforced by the
     * 'role:admin' route middleware and UserPolicy::update).
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($target->id),
            'password' => ['sometimes', 'string', Password::default(), 'confirmed'],
            'role' => [
                'required',
                new Enum(UserRole::class),
                // Demoting the only admin would leave nobody able to manage
                // accounts; User::booted() refuses it too, this just turns
                // it into a field error the Akun page can show.
                function (string $attribute, mixed $value, Closure $fail) use ($target): void {
                    if ($value !== UserRole::Admin->value && $target->isLastAdmin()) {
                        $fail('Admin terakhir tidak bisa diturunkan menjadi karyawan.');
                    }
                },
            ],
        ];
    }
}

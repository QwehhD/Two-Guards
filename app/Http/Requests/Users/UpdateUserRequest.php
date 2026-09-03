<?php

namespace App\Http\Requests\Users;

use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
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
            'role' => ['required', new Enum(UserRole::class)],
        ];
    }
}

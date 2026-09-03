<?php

namespace App\Http\Requests\RfidCards;

use App\Enums\RfidCardStatus;
use App\Models\RfidCard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreRfidCardRequest extends FormRequest
{
    /**
     * Only admins may create RFID cards (also enforced by the
     * 'role:admin' route middleware and RfidCardPolicy::create).
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', RfidCard::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'uid' => ['required', 'string', 'max:255', Rule::unique(RfidCard::class)],
            'owner_name' => ['required', 'string', 'max:255'],
            'status' => ['required', new Enum(RfidCardStatus::class)],
        ];
    }
}

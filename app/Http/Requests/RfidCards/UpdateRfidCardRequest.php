<?php

namespace App\Http\Requests\RfidCards;

use App\Enums\RfidCardStatus;
use App\Models\RfidCard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateRfidCardRequest extends FormRequest
{
    /**
     * Only admins may update RFID cards (also enforced by the
     * 'role:admin' route middleware and RfidCardPolicy::update).
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('rfid_card'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var RfidCard $target */
        $target = $this->route('rfid_card');

        return [
            'uid' => ['required', 'string', 'max:255', Rule::unique(RfidCard::class)->ignore($target->id)],
            'owner_name' => ['required', 'string', 'max:255'],
            'status' => ['required', new Enum(RfidCardStatus::class)],
        ];
    }
}

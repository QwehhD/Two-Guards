<?php

namespace App\Http\Requests\Devices;

use App\Enums\DeviceMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateDeviceModeRequest extends FormRequest
{
    /**
     * Only admins may switch a device's mode (also enforced by the
     * 'role:admin' route middleware and DevicePolicy::update).
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('device'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', new Enum(DeviceMode::class)],
        ];
    }
}

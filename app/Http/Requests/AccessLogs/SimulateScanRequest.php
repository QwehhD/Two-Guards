<?php

namespace App\Http\Requests\AccessLogs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DEVELOPMENT-ONLY request for the simulate-scan endpoint (Tahap 6).
 * Any authenticated user may trigger a simulated scan; the auth:sanctum
 * middleware on the route already guarantees that.
 */
class SimulateScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'integer', 'exists:devices,id'],
            // Left empty to simulate an unknown/unregistered card; the
            // controller generates a random UID in that case.
            'uid' => ['nullable', 'string', 'max:255'],
        ];
    }
}

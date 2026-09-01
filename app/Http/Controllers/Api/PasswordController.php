<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\JsonResponse;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        return response()->json(['message' => __('Password updated.')]);
    }
}

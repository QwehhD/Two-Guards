<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

// Login, registration, and logout are handled by Laravel Fortify's
// built-in session routes: POST /login, POST /register, POST /logout.
// The React SPA hits /sanctum/csrf-cookie first, then those endpoints.

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);

    Route::get('profile', [ProfileController::class, 'show']);
    Route::patch('profile', [ProfileController::class, 'update']);
    Route::delete('profile', [ProfileController::class, 'destroy']);

    Route::put('password', [PasswordController::class, 'update']);
});

// ---------------------------------------------------------------------
// TEMPORARY: dummy routes for manually/automatically verifying that the
// `role` middleware and the auth layer behave correctly. No real
// controllers exist yet for employee/RFID/history management (that's
// Tahap 3 & 4). Delete or replace these once those endpoints exist.
// ---------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/test/admin-only', fn () => response()->json(['message' => 'Halo admin']));
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/test/any-role', fn () => response()->json(['message' => 'Halo semua role']));
});

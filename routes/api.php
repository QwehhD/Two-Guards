<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserController;
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

// Employee account management (Tahap 3). Admin-only, enforced by both
// the `role:admin` route middleware and UserController's policy checks
// (authorizeResource against UserPolicy) as a second, data-aware layer.
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::apiResource('users', UserController::class)->except(['show']);
});

// ---------------------------------------------------------------------
// TEMPORARY: dummy route for manually/automatically verifying that the
// auth layer accepts any authenticated role. No real controller exists
// yet for portal control/history (that's Tahap 4). Delete or replace
// once that endpoint exists.
// ---------------------------------------------------------------------
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/test/any-role', fn () => response()->json(['message' => 'Halo semua role']));
});

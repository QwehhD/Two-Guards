<?php

use App\Http\Controllers\Api\AccessLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RfidCardController;
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

// Employee account & RFID card management (Tahap 3). Admin-only,
// enforced by both the `role:admin` route middleware and each
// controller's explicit policy checks as a second, data-aware layer.
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::apiResource('users', UserController::class)->except(['show']);
    Route::apiResource('rfid-cards', RfidCardController::class)->except(['show']);
});

// Portal control & history (Tahap 4). Open to any authenticated role
// (admin and karyawan); AccessLogPolicy enforces this per-action.
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('access-logs', [AccessLogController::class, 'index']);
    Route::post('access-logs/{access_log}/approve', [AccessLogController::class, 'approve']);
    Route::post('access-logs/{access_log}/reject', [AccessLogController::class, 'reject']);
});

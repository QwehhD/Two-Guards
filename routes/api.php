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

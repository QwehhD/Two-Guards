<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Return the currently authenticated user.
     *
     * Login, registration, and logout are handled by Laravel Fortify's
     * built-in session routes (POST /login, /register, /logout) — Fortify
     * already returns the right response for XHR/SPA requests, so there's
     * no need to duplicate that logic here.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}

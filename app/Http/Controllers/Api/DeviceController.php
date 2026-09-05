<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    /**
     * Minimal device listing (Tahap 6): lets any authenticated user pick a
     * device when simulating a scan. Full device management/CRUD is a
     * separate, later concern — this only exposes what a picker needs.
     */
    public function index(): JsonResponse
    {
        return response()->json(
            Device::query()->orderBy('name')->get(['id', 'name', 'mode', 'status'])
        );
    }
}

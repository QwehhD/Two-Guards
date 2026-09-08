<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class PublicPortalStatusController extends Controller
{
    /**
     * Public, unauthenticated snapshot of each device's general status for
     * the public landing page (Tahap 7). Deliberately touches ONLY the
     * `devices` table — never access_logs — so there is no code path here
     * that could accidentally leak a scan's personal details (owner name,
     * UID, who approved it) even by future mistake.
     *
     * Polled by the frontend for now; a real-time WebSocket push replaces
     * this polling in Tahap 8, but the response contract here is expected
     * to stay the same.
     */
    public function index(): JsonResponse
    {
        $devices = Device::query()
            ->orderBy('name')
            ->get(['name', 'status', 'portal_status', 'mode'])
            ->map(fn (Device $device) => [
                'name' => $device->name,
                'status' => $device->status->value,
                'portal_status' => $device->portal_status->value,
                'mode' => $device->mode->value,
            ]);

        return response()->json(['data' => $devices]);
    }
}

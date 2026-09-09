<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class PublicPortalStatusController extends Controller
{
    /**
     * Public, unauthenticated snapshot of each device's general status for
     * the public landing page (Tahap 7). Deliberately touches ONLY the
     * `devices` table — never access_logs — so there is no code path here
     * that could accidentally leak a scan's personal details (owner name,
     * UID, who approved it) even by future mistake.
     *
     * Replaced by a WebSocket push (App\Events\PortalStatusUpdated, Tahap
     * 8) as the primary update path; this endpoint stays only as the
     * initial page-load snapshot before the socket subscription is live.
     * The response contract must stay identical to snapshot() below,
     * since both feed the exact same frontend state shape.
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => self::snapshot()]);
    }

    /**
     * The public device-status shape, shared by this endpoint and
     * App\Events\PortalStatusUpdated so the initial HTTP snapshot and
     * every subsequent WebSocket push carry exactly the same contract.
     *
     * @return Collection<int, array<string, string>>
     */
    public static function snapshot(): Collection
    {
        return Device::query()
            ->orderBy('name')
            ->get(['name', 'status', 'portal_status', 'mode'])
            ->map(fn (Device $device) => [
                'name' => $device->name,
                'status' => $device->status->value,
                'portal_status' => $device->portal_status->value,
                'mode' => $device->mode->value,
            ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Events\PortalStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Devices\UpdateDeviceModeRequest;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    /**
     * What the dashboard's device cards show; the simulate-scan picker
     * (Tahap 6) only needs id and name.
     */
    private const COLUMNS = ['id', 'name', 'mode', 'status', 'portal_status', 'last_seen_at'];

    /**
     * Device listing for any authenticated user. Devices are not created or
     * renamed through the API: a row stands for a physical portal.
     */
    public function index(): JsonResponse
    {
        return response()->json(
            Device::query()->orderBy('name')->get(self::COLUMNS)
        );
    }

    /**
     * Switch a device between auto and manual mode; nothing else about a
     * device can be changed here. Only scans recorded from now on follow
     * the new mode: a scan already pending keeps waiting for a decision (or
     * expires) as before.
     */
    public function update(UpdateDeviceModeRequest $request, Device $device): JsonResponse
    {
        $device->update($request->validated());

        // The public landing page shows each device's mode as well.
        PortalStatusUpdated::dispatch();

        return response()->json($device->only(self::COLUMNS));
    }
}

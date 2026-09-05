<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccessLogMode;
use App\Enums\AccessLogStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccessLogs\SimulateScanRequest;
use App\Http\Resources\AccessLogResource;
use App\Models\AccessLog;
use App\Models\Device;
use App\Services\AccessDecisionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccessLogController extends Controller
{
    use AuthorizesRequests;

    /**
     * List access history. Both admin and karyawan may view this.
     *
     * Supports filtering via query params: status, mode, device_id,
     * date_from, date_to (both applied against scanned_at), and search
     * (matches the card's owner_name or the raw scanned_uid).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AccessLog::class);

        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(AccessLogStatus::class)],
            'mode' => ['nullable', Rule::enum(AccessLogMode::class)],
            'device_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $logs = AccessLog::query()
            ->with(['rfidCard', 'device', 'processor'])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['mode'] ?? null, fn ($query, $mode) => $query->where('mode', $mode))
            ->when($validated['device_id'] ?? null, fn ($query, $deviceId) => $query->where('device_id', $deviceId))
            ->when($validated['date_from'] ?? null, fn ($query, $date) => $query->whereDate('scanned_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($query, $date) => $query->whereDate('scanned_at', '<=', $date))
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($query) => $query
                    ->where('scanned_uid', 'like', "%{$search}%")
                    ->orWhereHas('rfidCard', fn ($query) => $query->where('owner_name', 'like', "%{$search}%"))
            ))
            ->latest('scanned_at')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return AccessLogResource::collection($logs);
    }

    /**
     * ============================================================
     *  DEVELOPMENT-ONLY ENDPOINT — NOT PART OF THE PRODUCTION FLOW
     * ============================================================
     * Simulates a hardware card scan so the manual-approval flow (Tahap 6)
     * can be tested end-to-end before the real ESP32/MQTT listener exists
     * (Tahap 9). It calls the exact same AccessDecisionService the MQTT
     * listener will call, so there is no separate/duplicated decision
     * logic to keep in sync — only the trigger differs (HTTP vs. MQTT).
     *
     * Remove or hide this endpoint (and the button that calls it on the
     * frontend) once real hardware is wired up in Tahap 9.
     */
    public function simulateScan(SimulateScanRequest $request, AccessDecisionService $decisions): JsonResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $device = Device::findOrFail($request->validated('device_id'));

        // No UID given -> simulate a scan from an unknown/unregistered card.
        $uid = $request->validated('uid') ?: strtoupper(Str::random(8));

        $log = $decisions->decide($device, $uid);

        return response()->json(new AccessLogResource($log->load(['device', 'rfidCard'])), 201);
    }

    /**
     * Approve a pending manual-mode scan. Both admin and karyawan may do this.
     */
    public function approve(Request $request, AccessLog $accessLog): JsonResponse
    {
        $this->authorize('approve', $accessLog);

        return $this->resolve($request, $accessLog, AccessLogStatus::Approved);
    }

    /**
     * Reject a pending manual-mode scan. Both admin and karyawan may do this.
     */
    public function reject(Request $request, AccessLog $accessLog): JsonResponse
    {
        $this->authorize('reject', $accessLog);

        return $this->resolve($request, $accessLog, AccessLogStatus::Denied);
    }

    private function resolve(Request $request, AccessLog $accessLog, AccessLogStatus $status): JsonResponse
    {
        if ($accessLog->mode !== AccessLogMode::Manual) {
            return response()->json([
                'message' => 'Only manual-mode scans can be approved or rejected.',
            ], 422);
        }

        if ($accessLog->status !== AccessLogStatus::Pending) {
            return response()->json([
                'message' => 'This scan has already been processed.',
            ], 422);
        }

        $accessLog->update([
            'status' => $status,
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        return response()->json(new AccessLogResource($accessLog->load(['device', 'rfidCard', 'processor'])));
    }
}

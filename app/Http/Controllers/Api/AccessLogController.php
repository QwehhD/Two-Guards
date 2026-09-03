<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccessLogMode;
use App\Enums\AccessLogStatus;
use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessLogController extends Controller
{
    use AuthorizesRequests;

    /**
     * List access history. Both admin and karyawan may view this.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', AccessLog::class);

        return response()->json(
            AccessLog::query()->latest('scanned_at')->get()
        );
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

        return response()->json($accessLog);
    }
}

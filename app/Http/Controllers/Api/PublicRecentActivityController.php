<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicAccessLogResource;
use App\Models\AccessLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicRecentActivityController extends Controller
{
    /**
     * Public, unauthenticated feed of the last 5 access_logs entries for
     * the public landing page (Tahap 7). Uses PublicAccessLogResource
     * (never the admin-facing AccessLogResource) so the response contract
     * is fixed to a small, deliberately-chosen set of general fields —
     * status, mode, and a relative timestamp — with no owner name, no
     * UID, and no record of who approved/rejected it.
     */
    public function index(): AnonymousResourceCollection
    {
        $logs = AccessLog::query()
            ->latest('scanned_at')
            ->limit(5)
            ->get(['status', 'mode', 'scanned_at']);

        return PublicAccessLogResource::collection($logs);
    }
}

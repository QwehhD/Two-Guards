<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicAccessLogResource;
use App\Models\AccessLog;
use Illuminate\Database\Eloquent\Collection;
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
     *
     * Replaced by a WebSocket push (App\Events\RecentActivityUpdated,
     * Tahap 8) as the primary update path; this endpoint stays only as
     * the initial page-load snapshot before the socket subscription is
     * live — see latest() below, shared by both.
     */
    public function index(): AnonymousResourceCollection
    {
        return PublicAccessLogResource::collection(self::latest());
    }

    /**
     * The last 5 access_logs entries, shared by this endpoint and
     * App\Events\RecentActivityUpdated so the initial HTTP snapshot and
     * every subsequent WebSocket push carry exactly the same rows.
     *
     * @return Collection<int, AccessLog>
     */
    public static function latest(): Collection
    {
        return AccessLog::query()
            ->latest('scanned_at')
            ->limit(5)
            ->get(['status', 'mode', 'scanned_at']);
    }
}

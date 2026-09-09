<?php

namespace App\Events;

use App\Http\Controllers\Api\PublicRecentActivityController;
use App\Http\Resources\PublicAccessLogResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The public "recent activity" feed changed, for the public landing page
 * (Tahap 8). Fired from two distinct occurrences, both of which change
 * what the last-5-scans feed shows:
 *
 *  - AccessDecisionService, for EVERY new scan (not just pending ones —
 *    an auto-mode approval/denial is itself new recent activity); and
 *  - App\Listeners\BroadcastPublicLandingUpdates, as a side effect of
 *    AccessLogResolved, since an existing entry's status flips from
 *    pending to approved/rejected/expired without a new row appearing.
 *
 * Uses PublicAccessLogResource (never AccessLogResource) so this payload
 * carries the exact same restricted, deliberately-chosen fields as the
 * public recent-activity HTTP endpoint — no owner name, UID, or approver.
 */
class RecentActivityUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('landing')];
    }

    public function broadcastAs(): string
    {
        return 'RecentActivityUpdated';
    }

    /**
     * Queried fresh at broadcast time (not at dispatch time) — see
     * App\Events\PortalStatusUpdated for why.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'recent_activity' => PublicAccessLogResource::collection(
                PublicRecentActivityController::latest()
            )->resolve(),
        ];
    }
}

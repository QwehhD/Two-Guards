<?php

namespace App\Events;

use App\Http\Controllers\Api\PublicPortalStatusController;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A device's general status (online/offline, portal open/closed, mode)
 * changed, for the public landing page (Tahap 8).
 *
 * For now this is only ever fired as a side effect of AccessLogResolved
 * (see App\Listeners\BroadcastPublicLandingUpdates) — there is no real
 * hardware yet, so an approval/rejection/expiry is used to simulate "the
 * portal changed state". Tahap 9's MQTT device-status listener will fire
 * this same event from the real device-status update instead, alongside
 * (not instead of) this simulation trigger.
 *
 * Broadcast on the PUBLIC "landing" channel — deliberately unauthenticated,
 * unlike AccessLogCreated/AccessLogResolved, because this payload never
 * touches access_logs and carries nothing that identifies a person.
 */
class PortalStatusUpdated implements ShouldBroadcast
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
        return 'PortalStatusUpdated';
    }

    /**
     * Queried fresh at broadcast time (not at dispatch time) so the
     * payload reflects the latest device state even if this job sat in
     * the queue for a moment before being sent.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'devices' => PublicPortalStatusController::snapshot(),
        ];
    }
}

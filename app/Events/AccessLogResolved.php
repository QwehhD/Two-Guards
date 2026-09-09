<?php

namespace App\Events;

use App\Http\Resources\AccessLogResource;
use App\Models\AccessLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A pending manual-mode scan just reached a final status — approved,
 * rejected, or expired (Tahap 8).
 *
 * Fired from every code path that can resolve a pending scan: the
 * approve/reject endpoints (AccessLogController) AND the expire-pending
 * housekeeping command (ExpirePendingAccessLogs), so the Approvals page
 * drops the item from every connected client's pending list the instant
 * it's resolved anywhere — not just in the tab that clicked the button.
 */
class AccessLogResolved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public AccessLog $accessLog;

    public function __construct(AccessLog $accessLog)
    {
        $this->accessLog = $accessLog->loadMissing(['device', 'rfidCard', 'processor']);
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('access-logs')];
    }

    public function broadcastAs(): string
    {
        return 'AccessLogResolved';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'access_log' => (new AccessLogResource($this->accessLog))->resolve(),
        ];
    }
}

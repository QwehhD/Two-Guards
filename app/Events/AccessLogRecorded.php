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
 * A scan was just recorded, whatever its status — approved or denied on the
 * spot in auto mode, or pending in manual mode.
 *
 * The Access History page listens for this (and AccessLogResolved) to stay
 * current without a reload. AccessLogCreated stays separate and pending-only,
 * because the Approvals page must only ever see scans it can act on.
 */
class AccessLogRecorded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public AccessLog $accessLog;

    public function __construct(AccessLog $accessLog)
    {
        $this->accessLog = $accessLog->loadMissing(['device', 'rfidCard']);
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
        return 'AccessLogRecorded';
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

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
 * A new manual-mode scan is waiting for a human decision (Tahap 8).
 *
 * Fired ONLY when AccessDecisionService leaves a scan pending — an
 * auto-mode scan that resolves itself immediately (approved or denied)
 * never reaches here, because the Approvals page (the only listener on
 * this channel) only ever needs to know about scans it might act on.
 */
class AccessLogCreated implements ShouldBroadcast
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
        return 'AccessLogCreated';
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

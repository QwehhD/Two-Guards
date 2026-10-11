<?php

namespace App\Mqtt;

use App\Enums\DeviceCommandAction;
use App\Models\AccessLog;
use InvalidArgumentException;

/**
 * One "open"/"deny" command for one device, tied to the access log that
 * decided it.
 *
 * This is the single place the command topic and payload are built. The
 * manual path (approve/reject -> queued PublishDeviceCommand job ->
 * MqttPublisherService) and the auto path (mqtt:listen publishing on its
 * own persistent connection) only differ in how they deliver it.
 *
 * issued_at is the decision time as Unix epoch seconds, so the ESP32 can
 * compare it against its own NTP clock with plain integer arithmetic and
 * drop a command that arrives too late (see docs/mqtt-topics.md).
 */
readonly class DeviceCommand
{
    public function __construct(
        public int $deviceId,
        public DeviceCommandAction $action,
        public int $accessLogId,
        public int $issuedAt,
    ) {}

    public static function fromAccessLog(AccessLog $log): self
    {
        $action = DeviceCommandAction::fromStatus($log->status);

        if ($log->processed_at === null) {
            throw new InvalidArgumentException("Access log [{$log->id}] has no decision time to issue a command from.");
        }

        return new self(
            deviceId: $log->device_id,
            action: $action,
            accessLogId: $log->id,
            issuedAt: $log->processed_at->getTimestamp(),
        );
    }

    public function topic(): string
    {
        return "parkir/{$this->deviceId}/command";
    }

    public function payload(): string
    {
        return json_encode([
            'action' => $this->action->value,
            'access_log_id' => $this->accessLogId,
            'issued_at' => $this->issuedAt,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * The epoch second from which this command must no longer be sent.
     * A late "open" is more dangerous than one that never arrives, so
     * every sender checks this right before publishing.
     */
    public function staleAt(): int
    {
        return $this->issuedAt + (int) config('mqtt.command_ttl');
    }

    public function isStale(): bool
    {
        return now()->getTimestamp() >= $this->staleAt();
    }
}

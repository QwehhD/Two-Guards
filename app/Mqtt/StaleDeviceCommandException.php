<?php

namespace App\Mqtt;

use RuntimeException;

/**
 * The command's TTL ran out before it could be sent, so it was dropped
 * instead. Retrying can only make it staler.
 */
class StaleDeviceCommandException extends RuntimeException
{
    public static function for(DeviceCommand $command): self
    {
        return new self(
            "Device command for access log [{$command->accessLogId}] went stale at [{$command->staleAt()}] before it could be sent."
        );
    }
}

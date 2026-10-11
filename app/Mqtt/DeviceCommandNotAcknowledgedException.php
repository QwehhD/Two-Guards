<?php

namespace App\Mqtt;

use RuntimeException;

/**
 * The QoS 1 command was written to the broker connection, but no PUBACK
 * came back in time — so there is no proof the broker ever took it.
 */
class DeviceCommandNotAcknowledgedException extends RuntimeException
{
    public static function for(DeviceCommand $command, int $waitedSeconds): self
    {
        return new self(
            "Broker did not acknowledge the device command for access log [{$command->accessLogId}] within {$waitedSeconds}s."
        );
    }
}

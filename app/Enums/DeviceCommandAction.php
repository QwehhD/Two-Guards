<?php

namespace App\Enums;

use InvalidArgumentException;

enum DeviceCommandAction: string
{
    case Open = 'open';
    case Deny = 'deny';

    /**
     * Only a final decision has a command to send. Pending (still waiting
     * for a human) and expired (nobody decided in time) never reach the
     * device, so asking for their command is a bug in the caller.
     */
    public static function fromStatus(AccessLogStatus $status): self
    {
        return match ($status) {
            AccessLogStatus::Approved => self::Open,
            AccessLogStatus::Denied => self::Deny,
            default => throw new InvalidArgumentException(
                "No device command exists for access log status [{$status->value}]."
            ),
        };
    }
}

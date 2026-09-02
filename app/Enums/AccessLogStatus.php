<?php

namespace App\Enums;

enum AccessLogStatus: string
{
    case Approved = 'approved';
    case Denied = 'denied';
    case Pending = 'pending';
    case Expired = 'expired';
}

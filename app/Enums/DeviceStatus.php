<?php

namespace App\Enums;

enum DeviceStatus: string
{
    case Online = 'online';
    case Offline = 'offline';
}

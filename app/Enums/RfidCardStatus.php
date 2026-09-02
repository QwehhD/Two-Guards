<?php

namespace App\Enums;

enum RfidCardStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

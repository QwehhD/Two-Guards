<?php

namespace App\Models;

use App\Enums\DeviceMode;
use App\Enums\DeviceStatus;
use App\Enums\PortalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    /** @use HasFactory<\Database\Factories\DeviceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'mode',
        'last_seen_at',
        'status',
        'portal_status',
    ];

    protected function casts(): array
    {
        return [
            'mode' => DeviceMode::class,
            'last_seen_at' => 'datetime',
            'status' => DeviceStatus::class,
            'portal_status' => PortalStatus::class,
        ];
    }
}

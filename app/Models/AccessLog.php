<?php

namespace App\Models;

use App\Enums\AccessLogMode;
use App\Enums\AccessLogStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessLog extends Model
{
    /** @use HasFactory<\Database\Factories\AccessLogFactory> */
    use HasFactory;

    protected $fillable = [
        'device_id',
        'rfid_card_id',
        'scanned_uid',
        'mode',
        'status',
        'processed_by',
        'scanned_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'mode' => AccessLogMode::class,
            'status' => AccessLogStatus::class,
            'scanned_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function rfidCard(): BelongsTo
    {
        return $this->belongsTo(RfidCard::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}

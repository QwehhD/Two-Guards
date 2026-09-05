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

    /**
     * How long a manual-mode scan may sit pending before it's considered
     * expired. Used by both the expire-pending Artisan command (periodic
     * housekeeping) and the approve/reject endpoints (self-healing check
     * at the moment of the request, so correctness never depends on how
     * recently the housekeeping command last ran).
     */
    public const PENDING_TIMEOUT_SECONDS = 30;

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

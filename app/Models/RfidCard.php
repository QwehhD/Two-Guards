<?php

namespace App\Models;

use App\Enums\RfidCardStatus;
use Database\Factories\RfidCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfidCard extends Model
{
    /** @use HasFactory<RfidCardFactory> */
    use HasFactory;

    protected $fillable = [
        'uid',
        'owner_name',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RfidCardStatus::class,
        ];
    }

    /**
     * Who registered the card — kept after their account is deleted
     * (withTrashed), the same audit rule as AccessLog::processor().
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class);
    }
}

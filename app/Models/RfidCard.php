<?php

namespace App\Models;

use App\Enums\RfidCardStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfidCard extends Model
{
    /** @use HasFactory<\Database\Factories\RfidCardFactory> */
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class);
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccessLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scanned_uid' => $this->scanned_uid,
            'is_known_card' => $this->rfid_card_id !== null,
            'rfid_card_id' => $this->rfid_card_id,
            // Null-safe: an unknown/unregistered card has no rfidCard row, so
            // the frontend always has a name to show without checking twice.
            'owner_name' => $this->rfidCard?->owner_name ?? 'Tidak Dikenal',
            'mode' => $this->mode->value,
            'status' => $this->status->value,
            'device' => $this->whenLoaded('device', fn () => [
                'id' => $this->device->id,
                'name' => $this->device->name,
            ]),
            'processed_by' => $this->whenLoaded('processor', fn () => $this->processor ? [
                'id' => $this->processor->id,
                'name' => $this->processor->name,
            ] : null),
            'scanned_at' => $this->scanned_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
        ];
    }
}

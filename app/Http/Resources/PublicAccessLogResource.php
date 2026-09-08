<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The PUBLIC counterpart to AccessLogResource, used only by unauthenticated
 * endpoints (e.g. the landing page's "recent activity" feed).
 *
 * Deliberately kept as its own class, separate from AccessLogResource: the
 * two have different data contracts on purpose. AccessLogResource is free
 * to grow new fields for admin/karyawan tooling (owner_name, scanned_uid,
 * processed_by, etc.) without this resource silently inheriting and
 * leaking them to the public. Every field exposed here is chosen
 * deliberately — there is no fallback to "whatever AccessLogResource
 * exposes minus a few fields".
 *
 * @return array<string, mixed>
 */
class PublicAccessLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status->value,
            'mode' => $this->mode->value,
            'scanned_at' => $this->scanned_at?->diffForHumans(),
        ];
    }
}

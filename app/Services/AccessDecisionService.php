<?php

namespace App\Services;

use App\Enums\AccessLogMode;
use App\Enums\AccessLogStatus;
use App\Enums\DeviceMode;
use App\Enums\RfidCardStatus;
use App\Events\AccessLogCreated;
use App\Events\RecentActivityUpdated;
use App\Models\AccessLog;
use App\Models\Device;
use App\Models\RfidCard;
use Carbon\CarbonInterface;

/**
 * Turns a raw card scan into an access decision and records it.
 *
 * This is the single source of truth for the auto/manual + valid/invalid
 * decision matrix. The development-only simulate-scan endpoint (Tahap 6)
 * and the real MQTT listener (Tahap 9) both call this exact method,
 * instead of duplicating the decision logic at either call site.
 */
class AccessDecisionService
{
    /**
     * Record a scan of the given UID on the given device, and decide
     * whether it is approved, denied, or left pending for manual review.
     *
     * $scannedAt is when the physical scan happened, as reported by the
     * device. It defaults to now() for callers that have no such
     * timestamp of their own (e.g. the HTTP simulate-scan endpoint,
     * where the HTTP request itself is the "scan"). It is deliberately
     * kept separate from processed_at below, which always reflects when
     * *this server* made the decision — those two can legitimately
     * differ for a real device (e.g. a scan queued briefly by the ESP32
     * before it reached the broker).
     */
    public function decide(Device $device, string $uid, ?CarbonInterface $scannedAt = null): AccessLog
    {
        $card = RfidCard::query()->where('uid', $uid)->first();
        $isCardValid = $card !== null && $card->status === RfidCardStatus::Active;

        $status = match (true) {
            // Manual mode always defers to a human, regardless of card validity.
            $device->mode === DeviceMode::Manual => AccessLogStatus::Pending,
            $isCardValid => AccessLogStatus::Approved,
            default => AccessLogStatus::Denied,
        };

        $log = AccessLog::create([
            'device_id' => $device->id,
            'rfid_card_id' => $card?->id,
            'scanned_uid' => $uid,
            'mode' => AccessLogMode::from($device->mode->value),
            'status' => $status,
            'processed_by' => null,
            'scanned_at' => $scannedAt ?? now(),
            'processed_at' => $status === AccessLogStatus::Pending ? null : now(),
        ]);

        // Every new scan is new recent activity for the public landing
        // page, whatever its status — including an auto-mode scan that
        // was already approved/denied the instant it was recorded.
        RecentActivityUpdated::dispatch();

        // Only a scan left pending needs a human decision, so only that
        // case is relevant to the Approvals page's private feed.
        if ($status === AccessLogStatus::Pending) {
            AccessLogCreated::dispatch($log);
        }

        return $log;
    }
}

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

/**
 * Turns a raw card scan into an access decision and records it.
 *
 * This is the single source of truth for the auto/manual + valid/invalid
 * decision matrix. The development-only simulate-scan endpoint (Tahap 6)
 * calls this today; the real MQTT listener (Tahap 9) will call this same
 * service once hardware exists, instead of duplicating the logic.
 */
class AccessDecisionService
{
    /**
     * Record a scan of the given UID on the given device, and decide
     * whether it is approved, denied, or left pending for manual review.
     */
    public function decide(Device $device, string $uid): AccessLog
    {
        $card = RfidCard::query()->where('uid', $uid)->first();
        $isCardValid = $card !== null && $card->status === RfidCardStatus::Active;

        $status = match (true) {
            // Manual mode always defers to a human, regardless of card validity.
            $device->mode === DeviceMode::Manual => AccessLogStatus::Pending,
            $isCardValid => AccessLogStatus::Approved,
            default => AccessLogStatus::Denied,
        };

        $now = now();

        $log = AccessLog::create([
            'device_id' => $device->id,
            'rfid_card_id' => $card?->id,
            'scanned_uid' => $uid,
            'mode' => AccessLogMode::from($device->mode->value),
            'status' => $status,
            'processed_by' => null,
            'scanned_at' => $now,
            'processed_at' => $status === AccessLogStatus::Pending ? null : $now,
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

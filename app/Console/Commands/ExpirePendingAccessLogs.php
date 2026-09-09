<?php

namespace App\Console\Commands;

use App\Enums\AccessLogStatus;
use App\Events\AccessLogResolved;
use App\Models\AccessLog;
use Illuminate\Console\Command;

/**
 * Housekeeping sweep for stale pending scans (Tahap 6).
 *
 * This is a periodic backstop, not the source of truth for timing
 * correctness: the approve/reject endpoints independently self-heal a
 * stale pending log the moment someone tries to act on it, so a scan
 * can never be approved/rejected late just because this command hasn't
 * run yet. Run this manually for now (`php artisan access-logs:expire-pending`);
 * see the project notes for the tradeoffs of scheduling it every minute
 * vs. a more real-time approach.
 */
class ExpirePendingAccessLogs extends Command
{
    protected $signature = 'access-logs:expire-pending';

    protected $description = 'Mark pending manual-mode access logs older than the timeout as expired';

    public function handle(): int
    {
        $cutoff = now()->subSeconds(AccessLog::PENDING_TIMEOUT_SECONDS);

        $candidates = AccessLog::query()
            ->where('status', AccessLogStatus::Pending)
            ->where('scanned_at', '<=', $cutoff)
            ->get();

        $expiredCount = 0;

        // Updated (and broadcast) one row at a time, guarded by a
        // status check in the WHERE clause, instead of one bulk UPDATE:
        // a bulk update can't tell which rows it actually changed, so
        // it can't say which AccessLogResolved events to broadcast. The
        // guard also means a scan that an approve/reject request
        // resolves in the moment between this command's SELECT and
        // UPDATE is left alone — the same race protection the
        // approve/reject endpoint already applies from its side.
        foreach ($candidates as $log) {
            $updated = AccessLog::query()
                ->whereKey($log->id)
                ->where('status', AccessLogStatus::Pending)
                ->update([
                    'status' => AccessLogStatus::Expired,
                    'processed_at' => now(),
                ]);

            if ($updated === 1) {
                $expiredCount++;
                AccessLogResolved::dispatch($log->refresh());
            }
        }

        $this->info("Expired {$expiredCount} pending access log(s) older than ".AccessLog::PENDING_TIMEOUT_SECONDS.' second(s).');

        return self::SUCCESS;
    }
}

<?php

namespace App\Listeners;

use App\Events\AccessLogResolved;
use App\Events\PortalStatusUpdated;
use App\Events\RecentActivityUpdated;

/**
 * Every time a pending scan is resolved, refresh both halves of the
 * public landing page (Tahap 8).
 *
 * Kept as a listener rather than dispatching PortalStatusUpdated and
 * RecentActivityUpdated directly from AccessLogController::resolve()
 * and ExpirePendingAccessLogs — both of those already fire
 * AccessLogResolved, so this is the one place that says "a resolution
 * simulates a portal status change and refreshes recent activity",
 * instead of that rule being duplicated at every call site. Tahap 9's
 * real MQTT device-status listener will dispatch PortalStatusUpdated
 * from its own trigger without needing to touch this listener.
 */
class BroadcastPublicLandingUpdates
{
    public function handle(AccessLogResolved $event): void
    {
        PortalStatusUpdated::dispatch();
        RecentActivityUpdated::dispatch();
    }
}

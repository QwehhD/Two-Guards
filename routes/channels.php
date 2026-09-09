<?php

use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Private channel backing the Approvals page (Tahap 8): live pending-scan
 * events (AccessLogCreated) and resolution events (AccessLogResolved).
 *
 * Authorization is delegated to AccessLogPolicy::viewAny — the same
 * ability the "GET /access-logs" endpoint already checks (see
 * AccessLogController::index) — so there is exactly one place that
 * decides "can this user see access logs", not one for HTTP and a
 * second, possibly-drifting one for the socket.
 *
 * Deliberately a single global channel, not one per device: the
 * Approvals page (and the underlying access-logs listing) already shows
 * pending scans across every device in one list, so there is no
 * per-device audience to split the socket subscription by either.
 *
 * There is no equivalent entry needed for the public landing-page
 * channel — any channel name NOT registered here is implicitly public,
 * so the "landing" channel used by PortalStatusUpdated and
 * RecentActivityUpdated needs no authorization callback at all.
 */
Broadcast::channel('access-logs', function (User $user) {
    return $user->can('viewAny', AccessLog::class);
});

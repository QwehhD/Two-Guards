<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// A manual-mode scan nobody decided on within 30 s is marked expired here,
// which also drops it from every open Approvals page (AccessLogResolved).
// Approve/reject self-heal on their own, so this only has to be prompt, not
// exact. Runs under `schedule:work` in `php artisan dev`, and from a
// `* * * * * php artisan schedule:run` cron entry in production.
Schedule::command('access-logs:expire-pending')->everyFiveSeconds()->withoutOverlapping();

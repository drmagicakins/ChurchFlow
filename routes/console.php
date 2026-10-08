<?php

// --- Phase 8 addition: merge into routes/console.php, do not overwrite ---
// (Laravel 11+ registers the scheduler here rather than in a Kernel class;
// if this project predates that, put the same line in
// App\Console\Kernel::schedule() instead.)

use Illuminate\Support\Facades\Schedule;

Schedule::command('subscriptions:process-billing-cycle')->daily();

/*
 | Phase 13 — the platform health cron.
 |
 | Every five minutes rather than every minute: a check that runs every minute
 | mostly re-confirms what the last run found, and the notifier already
 | de-duplicates alerts, so the extra runs buy nothing but load on the very
 | database the check is measuring.
 |
 | `withoutOverlapping` matters more than it looks. A run that hangs (a database
 | that is accepting connections but never answering) would otherwise have the
 | next tick start a second probe on top of it, and the third on top of that,
 | turning a slow database into a pile-up of health checks — the monitor
 | becoming the outage.
 |
 | `runInBackground` keeps the scheduler free while a slow probe finishes;
 | `onOneServer` stops every app instance running its own copy of the check in
 | a multi-server deployment, which would multiply both the load and the
 | incident rows.
 */
Schedule::command('platform:health-check --silent')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground()
    ->onOneServer();

/*
 | Every minute, record the scheduler's own heartbeat.
 |
 | This is what the `scheduler` health check reads. Without it there is no way
 | to answer "is the scheduler actually running?" — and if it is not, nothing
 | errors at all. Subscription renewals simply stop happening, trials never
 | expire, and dunning never advances, all silently. Referencing a heartbeat
 | written by the same scheduler that is supposed to write it looks circular,
 | and is the point: if the scheduler stops, the timestamp goes stale and the
 | check notices. It is the absence of the write that is the signal.
 */
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::put(
    'health:scheduler_heartbeat',
    now()->toIso8601String(),
    now()->addHour(),
))->everyMinute()->name('scheduler-heartbeat')->onOneServer();

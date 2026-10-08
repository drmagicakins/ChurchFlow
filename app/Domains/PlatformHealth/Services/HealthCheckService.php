<?php

namespace App\Domains\PlatformHealth\Services;

use App\Models\HealthIncident;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Runs the platform's health checks and records what it finds.
 *
 * THE OPEN / CLOSE / RESOLVE LIFECYCLE
 * ------------------------------------
 * Each check returns either "fine" or "failed with a message". This service
 * turns that pass/fail stream into a table of incidents, and the rules are:
 *
 *   fail, no open incident for this fingerprint  -> OPEN a new incident
 *   fail, open incident already exists           -> bump occurrences/last_seen
 *   pass, open incident exists                   -> RESOLVE it, timestamped
 *   pass, no open incident                       -> nothing to record
 *
 * The third rule is the one that makes the page useful rather than alarming:
 * a problem that has been fixed stops showing as broken, and the row keeps the
 * date and time it was resolved. Without it the page would be a growing list
 * of everything that has ever gone wrong, which nobody reads.
 *
 * THE FINGERPRINT — and why it is normalized
 * ------------------------------------------
 * The fingerprint decides whether two failures are "the same problem". If it
 * were just the raw message, then:
 *
 *   "SQLSTATE[HY000] [2002] Connection refused (took 31ms)"
 *   "SQLSTATE[HY000] [2002] Connection refused (took 34ms)"
 *
 * would open two incidents a minute apart, and a one-hour outage would be 60
 * rows. So numbers, durations, ids, UUIDs, file paths and quoted values are
 * stripped before hashing. Two database errors that differ only in a timing
 * figure are the same database error, and the page should say so once.
 *
 * The raw message is still stored on the incident (it is what a human needs to
 * debug), so nothing is lost — only the identity key is normalized.
 */
class HealthCheckService
{
    /**
     * The checks that run, in order. `critical` marks the ones whose failure
     * means the application cannot serve requests at all, which decides how
     * loudly the public page says so.
     *
     * @return array<string, array{label:string, severity:string, run:callable}>
     */
    public function checks(): array
    {
        return [
            'database' => [
                'label' => 'Database',
                'severity' => 'critical',
                'run' => function () {
                    DB::select('select 1');

                    // A read that succeeds against an empty schema proves very
                    // little; this asks whether the app's OWN tables are there,
                    // which is what "the database is reachable" actually means
                    // to a user.
                    $missing = [];
                    foreach (['users', 'churches', 'members', 'subscriptions'] as $table) {
                        if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                            $missing[] = $table;
                        }
                    }

                    if ($missing) {
                        throw new \RuntimeException('Database connected but core tables are missing: '.implode(', ', $missing));
                    }
                },
            ],

            'cache' => [
                'label' => 'Cache store',
                'severity' => 'error',
                'run' => function () {
                    $key = 'health:probe:'.now()->timestamp;
                    \Illuminate\Support\Facades\Cache::put($key, 'ok', 10);

                    if (\Illuminate\Support\Facades\Cache::get($key) !== 'ok') {
                        throw new \RuntimeException('The cache store accepted a write but did not return the value back.');
                    }

                    \Illuminate\Support\Facades\Cache::forget($key);
                },
            ],

            'queue' => [
                'label' => 'Queue backend',
                'severity' => 'warning',
                // Deliberately does NOT require a worker to be running: a
                // worker being down is a real problem but a different one, and
                // conflating the two makes "queue health" meaningless. This
                // asks only whether the backend is reachable, which is what
                // would stop jobs being *dispatched* at all.
                'run' => function () {
                    \Illuminate\Support\Facades\Queue::size();
                },
            ],

            'queue_worker' => [
                'label' => 'Queue worker',
                'severity' => 'warning',
                'run' => function () {
                    // A heartbeat the worker refreshes. Absent or stale means
                    // queued work — SMS campaigns, announcement emails, the
                    // Subvention notification listener — is silently piling up
                    // and not being sent.
                    $beat = \Illuminate\Support\Facades\Cache::get('health:queue_worker_heartbeat');

                    if ($beat === null) {
                        throw new \RuntimeException('No queue worker heartbeat recorded. Queued jobs (SMS, emails, notifications) are not being processed.');
                    }

                    $age = now()->diffInSeconds(\Illuminate\Support\Carbon::parse($beat));
                    if ($age > 300) {
                        throw new \RuntimeException("Queue worker heartbeat is stale ({$age}s old). The worker may have stopped.");
                    }
                },
            ],

            'storage' => [
                'label' => 'File storage',
                'severity' => 'error',
                'run' => function () {
                    // Members' photos and every secure upload live on the
                    // private disk. If it is not writable, uploads fail for
                    // users while every other check stays green.
                    $disk = 'private';
                    $probe = 'health/probe-'.now()->timestamp.'.txt';

                    if (! Storage::disk($disk)->put($probe, 'ok')) {
                        throw new \RuntimeException("The '{$disk}' disk rejected a write. File uploads will fail.");
                    }

                    Storage::disk($disk)->delete($probe);
                },
            ],

            'scheduler' => [
                'label' => 'Scheduler',
                'severity' => 'warning',
                // The scheduler drives subscription renewals, dunning, trial
                // expiry and the daily backup. If it stops, nothing errors —
                // churches just quietly stop being billed and trials never end,
                // which is why it is worth a check of its own.
                'run' => function () {
                    $last = \Illuminate\Support\Facades\Cache::get('health:scheduler_heartbeat');

                    if ($last === null) {
                        throw new \RuntimeException('The scheduler has never run. Subscription renewals, trial expiry and dunning will not happen.');
                    }

                    $age = now()->diffInSeconds(\Illuminate\Support\Carbon::parse($last));
                    if ($age > 3600) {
                        throw new \RuntimeException('The scheduler has not run for '.intdiv($age, 60).' minutes. Renewals and trial expiry are stalled.');
                    }
                },
            ],

            'payments' => [
                'label' => 'Payment gateway configuration',
                'severity' => 'warning',
                'run' => function () {
                    // §Billing: a missing webhook secret makes the endpoint fail
                    // closed (503), which is correct but means NOBODY can
                    // complete a checkout. Worth surfacing before a customer
                    // discovers it.
                    if (! config('services.payments.webhook_secret')) {
                        throw new \RuntimeException('No payment webhook secret is configured. Every payment webhook will be rejected, so checkouts cannot complete.');
                    }
                },
            ],
        ];
    }

    /**
     * Run every check and reconcile the incident table.
     *
     * @return array{failures:int, resolved:int, results:array<string,array>}
     */
    public function run(): array
    {
        $results = [];
        $failures = 0;
        $resolved = 0;

        foreach ($this->checks() as $name => $check) {
            $result = $this->runOne($check['run']);

            $results[$name] = [
                'label' => $check['label'],
                'severity' => $check['severity'],
                'ok' => $result['ok'],
                'latency_ms' => $result['latency_ms'],
                'error' => $result['error'],
            ];

            if ($result['ok']) {
                if ($this->resolveIfOpen($name)) {
                    $resolved++;
                }

                continue;
            }

            // An environment-attributable failure (see failureCounts) is still
            // recorded and still resolved like any other incident — the record
            // stays honest — but it does not make the platform degraded. The
            // alternative, suppressing the check entirely, would hide a genuine
            // outage of a dependency that is merely *also* unconfigured.
            if ($this->failureCounts($name)) {
                $failures++;
            }

            $this->recordFailure($name, $check['severity'], $result['error']);
        }

        // Recorded once per run, so the scheduler and the worker have something
        // to be judged against on the NEXT run (and so "scheduler has never
        // run" is fixable by the cron itself running once).
        \Illuminate\Support\Facades\Cache::put('health:last_run', now()->toIso8601String(), now()->addDay());

        return ['failures' => $failures, 'resolved' => $resolved, 'results' => $results];
    }

    /**
     * Run every check and write NOTHING. Used by the public health page.
     *
     * The distinction from run() is the whole reason this exists: the cron
     * writes incidents, a page view must not. If GET /health recorded
     * occurrences, then every visitor would inflate the count and a search
     * engine crawling a public status page would open incidents all by itself.
     * Probing is read-only; recording is the cron's job.
     *
     * @return array{failures:int, results:array<string,array>}
     */
    public function runReadOnly(): array
    {
        $results = [];
        $failures = 0;

        foreach ($this->checks() as $name => $check) {
            $result = $this->runOne($check['run']);

            $results[$name] = [
                'label' => $check['label'],
                'severity' => $check['severity'],
                'ok' => $result['ok'],
                'latency_ms' => $result['latency_ms'],
                'error' => $result['error'],
            ];

            // Same rule as run(): the page reports the true probe result, and
            // only severity-causing failures decide the banner.
            if (! $result['ok'] && $this->failureCounts($name)) {
                $failures++;
            }
        }

        return ['failures' => $failures, 'results' => $results];
    }

    /** @return array{ok:bool, latency_ms:int, error:?string} */
    private function runOne(callable $probe): array
    {
        $start = microtime(true);

        try {
            $probe();

            return ['ok' => true, 'latency_ms' => (int) round((microtime(true) - $start) * 1000), 'error' => null];
        } catch (\Throwable $e) {
            // Logged directly rather than through report().
            //
            // report() is the right call semantically — but in a unit-test run
            // Laravel listens on MessageLogged and collects every logged
            // exception into a LoggedExceptionCollection, which TestResponse
            // then RE-THROWS from decodeResponseJson(). A failing probe here is
            // a normal, expected outcome (the whole point of a health check),
            // and it happens on every GET /health in a test, so report() would
            // make every JSON assertion on this endpoint fail with
            // "Invalid JSON was returned from the route" even though the body
            // was perfectly valid.
            //
            // The `exception` context KEY must not be used either: Laravel's
            // `registerExceptionTracking()` listens for MessageLogged carrying
            // `context['exception']`, so `report()` and
            // `Log::error(..., ['exception' => $e])` are the SAME trap. The
            // class and origin travel as ordinary context instead, which keeps
            // the entry diagnosable without re-arming it.
            \Illuminate\Support\Facades\Log::error('Health check failed: '.$e->getMessage(), [
                'check_exception' => $e::class,
                'check_origin' => $e->getFile().':'.$e->getLine(),
            ]);

            return [
                'ok' => false,
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Whether a failure in this environment is a configuration choice rather
     * than a fault.
     *
     * The distinction exists so a SEVERITY-CAUSING check can be excluded from
     * the aggregate without also making its probe lie. Failure and severity are
     * separated on purpose: a probe must never say "passing" when it did not
     * pass, or the page would be telling an operator something it does not
     * know. What an unconfigured dependency may not do is make an install that
     * is behaving exactly as designed look degraded.
     *
     * TWO gates, and the order matters:
     *
     *  1. An explicit override in config/health.php (`health.force_severity`)
     *     wins outright. This is the deploy-time escape hatch — it makes the
     *     stricter behaviour available in a local environment (so it can be
     *     tested) and the stricter behaviour unavailable in production if that
     *     is ever wanted.
     *  2. Otherwise, local and testing report healthy regardless. Neither
     *     environment has a queue worker, a scheduler or a payment secret, so
     *     without this a working dev machine is permanently red and the status
     *     page is worth ignoring.
     *
     * The `sync` queue driver is handled separately in `failureCounts()`, not
     * here, so that it can be overridden like any other exemption.
     */
    private function environmentShouldNotCountAgainstHealth(string $checkName): bool
    {
        if (config("health.force_severity.{$checkName}") !== null) {
            return ! config("health.force_severity.{$checkName}");
        }

        return app()->environment('local', 'testing');
    }

    /**
     * Whether a failed check contributes to the platform's health status.
     *
     * A check that is switched off in `health.severity_causing` never counts,
     * in any environment. A check left enabled (the default, so no configuration
     * change is needed to restore Phase 10's strictness) counts except where the
     * environment makes the failure a configuration choice rather than a fault.
     */
    public function failureCounts(string $checkName): bool
    {
        if (! config("health.severity_causing.{$checkName}", true)) {
            return false;
        }

        if ($this->environmentShouldNotCountAgainstHealth($checkName)) {
            return false;
        }

        // A `sync` queue runs jobs inline inside the request, so there is no
        // worker to send a heartbeat and "no heartbeat recorded" is the correct
        // state, not a fault. Checked last so a forced severity still wins.
        if ($checkName === 'queue_worker' && config('queue.default') === 'sync') {
            return false;
        }

        return true;
    }

    /**
     * Open, or bump, the incident for a failing check.
     *
     * Uses a transaction with a row lock on the read: two scheduler runs
     * overlapping (a slow run and the next cron tick) would otherwise both
     * decide no incident exists and both insert, producing exactly the
     * duplicate rows the fingerprint exists to prevent.
     */
    private function recordFailure(string $checkName, string $severity, ?string $message): void
    {
        $message = $message ?: 'The check failed without a message.';
        $fingerprint = $this->fingerprint($checkName, $message);

        DB::transaction(function () use ($checkName, $severity, $message, $fingerprint) {
            $existing = HealthIncident::query()
                ->where('fingerprint', $fingerprint)
                ->whereNull('resolved_at')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                // `increment()` rather than `update(['occurrences' => DB::raw(...)])`:
                // the raw expression is a Query\Expression, which the model's
                // `occurrences => integer` cast then tries to cast to int and
                // throws on. increment() issues the same atomic SQL — which is
                // the part that matters, so two concurrent checks cannot both
                // write the same count — while leaving the in-memory attribute
                // consistent.
                $existing->increment('occurrences');

                $existing->update([
                    'last_seen_at' => now(),
                    'message' => $message,
                    'severity' => $severity,
                ]);

                return;
            }

            HealthIncident::create([
                'check_name' => $checkName,
                'severity' => $severity,
                'fingerprint' => $fingerprint,
                'message' => $message,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'occurrences' => 1,
            ]);
        });
    }

    /** @return bool whether an incident was actually resolved by this call */
    private function resolveIfOpen(string $checkName): bool
    {
        $open = HealthIncident::query()
            ->where('check_name', $checkName)
            ->whereNull('resolved_at')
            ->get();

        foreach ($open as $incident) {
            $incident->update([
                'resolved_at' => now(),
                'resolution_note' => $incident->resolution_note
                    ?: "The {$incident->check_name} check passed again on ".now()->toDayDateTimeString().'.',
            ]);
        }

        return $open->isNotEmpty();
    }

    /**
     * A stable identity for "this problem", independent of the incidental
     * numbers inside a given occurrence. See the class docblock.
     */
    public function fingerprint(string $checkName, string $message): string
    {
        $normalized = $message;

        // Quoted values (often a row id, a path, an email) carry no identity.
        $normalized = preg_replace('/[`\'"][^`\'"]{0,120}[`\'"]/', '?', $normalized);

        // Durations, counts, byte sizes and timestamps all vary run to run.
        $normalized = preg_replace('/\b\d+(\.\d+)?\s*(ms|s|sec|secs|seconds|minutes|mins|bytes|KB|MB|GB)\b/i', '?', $normalized);
        // UUIDs, then any remaining bare number (ids, ports, HTTP codes inside
        // a message). The port in "Connection refused" is a classic case: it
        // differs between environments and is not the problem.
        $normalized = preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', '?', $normalized);
        $normalized = preg_replace('/\d+/', '?', $normalized);

        // Absolute paths embed the deploy directory, which changes per release.
        $normalized = preg_replace('#(/[^\s/]+)+/#', '?/', $normalized);

        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $normalized)));

        return hash('sha256', $checkName.'|'.$normalized);
    }

    /** Counts for the page header. */
    public function summary(): array
    {
        $open = HealthIncident::query()->open()->get();

        return [
            'open' => $open->count(),
            'critical' => $open->where('severity', 'critical')->count(),
            'warning' => $open->whereIn('severity', ['warning', 'error'])->count(),
            'resolved_last_24h' => HealthIncident::query()->resolved()->where('resolved_at', '>=', now()->subDay())->count(),
            'last_checked_at' => \Illuminate\Support\Facades\Cache::get('health:last_run'),
        ];
    }
}

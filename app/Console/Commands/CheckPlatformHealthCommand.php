<?php

namespace App\Console\Commands;

use App\Domains\PlatformHealth\Services\HealthCheckService;
use App\Domains\PlatformHealth\Services\HealthIncidentNotifier;
use Illuminate\Console\Command;

/**
 * The cron entry point: run every platform health check, reconcile the
 * incident table, and tell the platform admins about anything newly broken.
 *
 * Scheduled every five minutes (see routes/console.php). Five rather than one
 * because a check that runs every minute mostly re-confirms what the last one
 * found, and the notification de-duplication already stops repeat alerts — so
 * the extra runs buy nothing but load.
 *
 * `--silent` is for a cron wrapper that only wants output when something is
 * wrong, so a healthy platform does not fill a log with "all fine" lines.
 */
class CheckPlatformHealthCommand extends Command
{
    protected $signature = 'platform:health-check
                            {--silent : Print nothing when every check passes}
                            {--no-notify : Record incidents without notifying platform admins}';

    protected $description = 'Run the platform health checks and record any failures as incidents';

    public function handle(HealthCheckService $checks, HealthIncidentNotifier $notifier): int
    {
        // The heartbeat is refreshed BEFORE the checks run. If a check hangs or
        // the process is killed mid-run, the heartbeat still proves the
        // scheduler fired — which is the question this timestamp answers. A
        // heartbeat written afterwards would be absent precisely when the run
        // failed, making "scheduler never ran" and "the run crashed"
        // indistinguishable.
        \Illuminate\Support\Facades\Cache::put('health:scheduler_heartbeat', now()->toIso8601String(), now()->addHours(2));

        $result = $checks->run();

        $failures = $result['failures'];
        $resolved = $result['resolved'];

        if (! $this->option('no-notify')) {
            // Notifies only for incidents that are NEWLY open. Bumping an
            // existing incident's counter must not send a second alert, or an
            // hour-long outage sends twelve identical notifications and the
            // admin learns to ignore them.
            $notifier->notifyNewIncidents();
            $notifier->notifyRecoveredSince(now()->subMinutes(6));
        }

        if ($failures === 0 && $resolved === 0 && $this->option('silent')) {
            return self::SUCCESS;
        }

        foreach ($result['results'] as $name => $check) {
            $this->line(sprintf(
                '%s %-34s %s',
                $check['ok'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>',
                $check['label'],
                $check['ok'] ? $check['latency_ms'].'ms' : $check['error'],
            ));
        }

        $this->newLine();
        $this->line(sprintf('Failures: %d   Resolved this run: %d', $failures, $resolved));

        // A non-zero exit code when something is broken, so a shell-based
        // monitor can act on it without parsing output.
        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}

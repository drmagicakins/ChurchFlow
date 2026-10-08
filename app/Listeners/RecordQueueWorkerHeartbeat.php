<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Cache;

/**
 * Refreshes the queue-worker heartbeat that the `queue_worker` health check
 * reads.
 *
 * Registered against the JobProcessing EVENT (see AppServiceProvider). Note it
 * is a listener, not a job middleware: the obvious `Queue::globalMiddleware()`
 * call does not exist in this framework version, and a listener is the better
 * fit anyway — this observes a fact about the worker ("it picked up work")
 * rather than changing how a job runs.
 *
 * Fires on PICKUP, before the job body executes. A job that then throws or
 * hangs has already proven the worker was alive, which is exactly what this
 * heartbeat claims. Writing it on JobProcessed would lose the signal on the
 * failures most worth knowing about.
 *
 * THE HONEST LIMITATION: this proves a worker is ALIVE, not that it is keeping
 * up. A worker chewing through a large backlog keeps writing heartbeats and
 * looks perfectly healthy while jobs sit queued for an hour. Detecting a
 * backlog is a different measurement, and conflating the two would make
 * "queue worker" mean two unrelated things at once.
 *
 * The TTL (30 min) is deliberately much longer than the check's staleness
 * threshold (5 min). If they were equal, a worker that had merely gone quiet
 * for a moment would present identically to one that had crashed — no
 * heartbeat either way — and the check could not tell a lull from a failure.
 */
class RecordQueueWorkerHeartbeat
{
    public function handle(JobProcessing|JobExceptionOccurred $event): void
    {
        try {
            Cache::put('health:queue_worker_heartbeat', now()->toIso8601String(), now()->addMinutes(30));
        } catch (\Throwable $e) {
            // The heartbeat must never be the reason a job fails. If the cache
            // is the thing that is broken, the `cache` health check says so
            // directly — this is not the place to find out.
            report($e);
        }
    }
}

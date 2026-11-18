<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStarting;
use Illuminate\Support\Facades\Cache;

/**
 * Refreshes the queue-worker heartbeat that the `queue_worker` health check
 * reads.
 *
 * THREE SIGNALS, AND WHY THE THIRD IS THE ONE THAT WAS MISSING
 * ------------------------------------------------------------
 * The heartbeat was originally written only on `JobProcessing` — the moment a
 * worker PICKS UP a job. That is a good signal for "the worker is doing work",
 * but it cannot answer the question the health check actually asks ("is a
 * worker running?"), because a worker that is up and polling an EMPTY queue
 * never processes anything and therefore never wrote a heartbeat. On a fresh
 * deployment, or a quiet night, /health reported
 *
 *     No queue worker heartbeat recorded. Queued jobs (SMS, emails,
 *     notifications) are not being processed.
 *
 * while the worker was running perfectly — and the first real job, arriving
 * later, proves nothing until it arrives. The check was telling operators a
 * worker was down because nothing had been sent yet.
 *
 * `Looping` fires once per worker cycle — every `--sleep` seconds, whether or
 * not a job was found. That is the honest "the worker is alive and polling"
 * signal, and it is why it is registered alongside the pickup events:
 *
 *   - WorkerStarting / Looping  -> the worker process is alive
 *   - JobProcessing             -> a worker took a job (the original signal)
 *   - JobExceptionOccurred      -> a worker saw a failure and is still running
 *
 * THE HONEST LIMITATION. This proves a worker is ALIVE, not that it is keeping
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
    public function handle(JobProcessing|JobExceptionOccurred|Looping|WorkerStarting $event): void
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

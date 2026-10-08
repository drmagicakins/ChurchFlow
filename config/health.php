<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Severity-causing checks
    |--------------------------------------------------------------------------
    |
    | Which health checks, when they fail, make the platform REPORT as degraded
    | (503 from `/health.json`, a red banner on `/health`).
    |
    | This is deliberately separate from whether a check RUNS. A check that is
    | switched off here still probes, still records and resolves its incident on
    | the page, and still shows its true pass/fail result — it simply does not
    | drag the whole platform's status down with it.
    |
    | Why the distinction is needed at all: a fresh install that has taken no
    | payment credentials yet is behaving exactly as designed, and every queue
    | worker being absent is the correct state for a `sync`-queue deployment.
    | Counting those as failures makes a healthy install permanently red, so the
    | status page stops meaning anything.
    |
    | Setting one of these to TRUE (the default below) restores Phase 10's
    | original behaviour for that check: any failure counts, which is what a
    | production deployment of the billing platform probably wants for
    | `payments` and `queue_worker`.
    |
    | Independent of these flags, local and testing never report degraded —
    | neither environment has a worker heartbeat, a scheduler or a webhook
    | secret, so neither should ever be red. That exemption is the reason a
    | fresh clone and `php artisan test` both come up green.
    |
    */

    'severity_causing' => [

        // Queued SMS campaigns, announcement emails and Subvention
        // notifications stop flowing if no worker is running. In a deployed
        // environment this is a real, user-visible fault — so it counts by
        // default. The `sync` driver and local/testing are exempted in code
        // (see the class docblock note above), which is why this can stay true
        // without breaking the test suite or a dev machine.
        'queue_worker' => (bool) env('HEALTH_QUEUE_WORKER_SEVERITY', true),

        // A missing webhook secret makes payment webhooks fail closed (503),
        // so no checkout can ever complete. That is a genuine billing outage on
        // a live deployment, and a non-event on one that has not started
        // selling. Flag-off in the environment only when that is intentional.
        'payments' => (bool) env('HEALTH_PAYMENTS_SEVERITY', true),

    ],

    /*
    |--------------------------------------------------------------------------
    | Force severity (deploy-time override of the environment exemption)
    |--------------------------------------------------------------------------
    |
    | `severity_causing` decides WHETHER a check counts. `force_severity`
    | overrides the ENVIRONMENT exemption that would otherwise stop it: TRUE
    | makes that check count even in local/testing, FALSE makes it never count
    | even in production.
    |
    | Null (the default) means "no override" — defer to the environment rules.
    | This exists so the stricter behaviour stays testable without weakening the
    | default, which is what the health test suite uses it for.
    |
    */

    'force_severity' => [

        'queue_worker' => env('HEALTH_FORCE_QUEUE_WORKER_SEVERITY'),

        'payments' => env('HEALTH_FORCE_PAYMENTS_SEVERITY'),

    ],

];

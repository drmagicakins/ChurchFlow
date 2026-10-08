<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Laravel 11's default `/up` route only proves the app booted — it says
 * nothing about the database, cache, or queue actually being reachable,
 * which is what an uptime monitor or load balancer actually needs to know
 * before routing traffic here. This checks each dependency independently
 * so a monitoring dashboard can tell "database is down" apart from "queue
 * backend is down" instead of one opaque failure.
 */
class HealthController extends Controller
{
    public function show(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'cache' => $this->check(function () {
                Cache::put('health:check', true, 5);
                abort_unless(Cache::get('health:check') === true, 500);
            }),
            'queue' => $this->check(fn () => Queue::size()), // connects to the queue backend; doesn't require a worker running
        ];

        $healthy = !in_array(false, array_column($checks, 'ok'), true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    private function check(\Closure $probe): array
    {
        $start = microtime(true);

        try {
            $probe();

            return ['ok' => true, 'latency_ms' => (int) round((microtime(true) - $start) * 1000)];
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

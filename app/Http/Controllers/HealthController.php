<?php

namespace App\Http\Controllers;

use App\Domains\PlatformHealth\Services\HealthCheckService;
use App\Models\HealthIncident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The platform status page.
 *
 * REPLACES the Phase 10 JSON-only `/health`. That endpoint returned
 * `{"status":"ok"}` for a load balancer — machine-readable and nothing else.
 * An operator, or a church wondering why their dashboard is slow, could not
 * see *what* was wrong, *since when*, or whether it had happened before.
 *
 * This keeps the same probe set and adds the incident history on top:
 *
 *   GET /health        HTML, PUBLIC — anyone can see whether the platform is
 *                      healthy, what is broken, and when it was last checked
 *   GET /health.json   the old machine-readable shape, unchanged, so any
 *                      existing monitor or uptime check keeps working
 *
 * WHY PUBLIC, AND WHAT IS SHOWN
 * -----------------------------
 * "All users can access this" is the requirement, and it is a reasonable one:
 * a status page nobody can reach during an outage is useless. But public means
 * genuinely public, including to someone not logged in, so the page must not
 * leak anything about the deployment. Two rules follow:
 *
 *  1. An incident shows its check name, a human-readable message, severity and
 *     timestamps. It never shows the raw exception, a stack trace, a file path,
 *     a query, or a credential — those are logged server-side for whoever is
 *     debugging, and the `context` column is deliberately NOT rendered here.
 *  2. The checks run WITHOUT authentication but are read-only probes. Nothing
 *     on this page writes business data or exposes a tenant's records.
 *
 * The page is deliberately honest about a subtlety: an open incident is not
 * re-probed on every view, and the page states when the last cron run was — a
 * status page that implies live freshness it does not have is worse than one
 * that says "as of 14:32".
 */
class HealthController extends Controller
{
    public function __construct(private readonly HealthCheckService $checks) {}

    /** The human-facing status page. Public. */
    public function index(Request $request): View
    {
        $incidents = HealthIncident::query()
            ->open()
            // Critical first: the ordering is the page's whole editorial
            // decision — what a reader must know before what is merely useful.
            ->orderByRaw("case severity when 'critical' then 0 when 'error' then 1 when 'warning' then 2 else 3 end")
            ->orderByDesc('last_seen_at')
            ->get();

        $recentlyResolved = HealthIncident::query()
            ->resolved()
            ->orderByDesc('resolved_at')
            ->limit(15)
            ->get();

        // The live probe runs for the page too, so a visitor sees the CURRENT
        // state and not only the last cron's opinion. Results are NOT written
        // to the incident table here: a GET that mutates the incident log would
        // count page views as occurrences, and a crawler hitting a public
        // status page would open incidents by itself.
        $live = $this->checks->runReadOnly();

        return view('health.index', [
            'incidents' => $incidents,
            'recentlyResolved' => $recentlyResolved,
            'summary' => $this->checks->summary(),
            'live' => $live,
            'healthy' => $live['failures'] === 0,
        ]);
    }

    /**
     * The machine-readable endpoint, unchanged from Phase 10 in shape, so a
     * monitor configured against it keeps working. Status is 200/503 based on
     * the LIVE probe alone — a lingering open incident whose check is passing
     * again must not keep a load balancer routing traffic away.
     */
    public function json(): JsonResponse
    {
        $live = $this->checks->runReadOnly();

        return response()->json([
            'status' => $live['failures'] === 0 ? 'ok' : 'degraded',
            'checks' => $live['results'],
            'open_incidents' => HealthIncident::query()->open()->count(),
            'timestamp' => now()->toIso8601String(),
        ], $live['failures'] === 0 ? 200 : 503);
    }
}

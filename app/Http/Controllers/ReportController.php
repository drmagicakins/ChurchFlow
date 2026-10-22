<?php

namespace App\Http\Controllers;

use App\Domains\Reports\Services\ReportsService;
use App\Domains\Subscriptions\Services\PlanFeatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * §30: advanced reports. The index lists the catalogue (filtered to what the
 * user's permissions allow), and each report page renders the same normalised
 * shape ReportsService returns — one view, four reports.
 *
 * Two gates, deliberately separate:
 *   1. The PLAN must include `advanced_reports` (Growth and up). This is the
 *      product feature a church pays for.
 *   2. The USER must hold the report's permission (e.g. finance.view). This is
 *      the same authority the module page enforces, so a report can never show
 *      a figure its owner couldn't see on the page it comes from.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportsService $reports,
        private readonly PlanFeatureService $features,
    ) {}

    public function index(Request $request): View
    {
        $church = $request->user()->church;

        // The catalogue is filtered by permission, so a user without
        // finance.view never sees a finance report tile they'd be 403'd on.
        $available = collect($this->reports->catalogue())
            ->filter(fn ($r) => $r['permission'] === null || $request->user()->hasPermission($r['permission']))
            ->values();

        return view('reports.index', [
            'reports' => $available,
            'enabled' => $this->features->hasFeature($church, 'advanced_reports'),
            'requiredPlan' => $this->features->requiredPlanName('advanced_reports'),
        ]);
    }

    public function show(Request $request, string $report): View
    {
        $definition = $this->reports->find($report)
            ?? abort(404, 'Unknown report.');

        if ($definition['permission'] !== null) {
            abort_unless($request->user()->hasPermission($definition['permission']), 403);
        }

        [$from, $to] = $this->range($request);
        $branchId = $request->filled('branch') ? (int) $request->input('branch') : null;

        return view('reports.show', [
            'report' => $definition,
            'payload' => $this->reports->run($report, $from, $to, $branchId),
            'from' => $from,
            'to' => $to,
            'branchId' => $branchId,
            'branches' => $this->reports->branchOptions(),
            'enabled' => $this->features->hasFeature($request->user()->church, 'advanced_reports'),
            'requiredPlan' => $this->features->requiredPlanName('advanced_reports'),
        ]);
    }

    /** §30: CSV of exactly the rows the page shows — no separate extraction path to drift. */
    public function export(Request $request, string $report): StreamedResponse
    {
        $definition = $this->reports->find($report) ?? abort(404, 'Unknown report.');

        if ($definition['permission'] !== null) {
            abort_unless($request->user()->hasPermission($definition['permission']), 403);
        }

        abort_unless($this->features->hasFeature($request->user()->church, 'advanced_reports'), 403,
            'Advanced reports are not included in your current plan.');

        [$from, $to] = $this->range($request);
        $branchId = $request->filled('branch') ? (int) $request->input('branch') : null;

        $payload = $this->reports->run($report, $from, $to, $branchId);
        $matrix = $this->reports->toCsvMatrix($payload);
        $filename = $report.'-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($matrix) {
            $out = fopen('php://output', 'w');
            foreach ($matrix as $line) {
                fputcsv($out, $line);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Parse the period, defaulting to the last 30 days. `from` is clamped to not
     * be after `to`, so a reversed range yields an empty report rather than a
     * nonsensical one.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->toString())->startOfDay()
            : now()->subDays(29)->startOfDay();

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->toString())->endOfDay()
            : now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }
}

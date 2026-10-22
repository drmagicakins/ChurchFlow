<?php

namespace App\Domains\Reports\Services;

use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\Event;
use App\Models\Member;
use App\Models\OrganizationalUnit;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * §30: the advanced reports engine.
 *
 * Every figure here is an aggregate query over the SAME tables the module pages
 * write to — never a pre-computed reporting table. That is the same rule the
 * dashboard follows, and for the same reason: a stored total is a second source
 * of truth that drifts the moment a voided transaction or a corrected
 * attendance record changes the underlying rows. Reports are the one place a
 * church will notice drift, so they are the last place to introduce it.
 *
 * The service is deliberately read-only and takes an explicit date range +
 * optional branch filter on every method, so the controller stays a thin
 * adapter and each report is independently unit-testable.
 */
class ReportsService
{
    /**
     * The catalogue of reports the module offers. `key` is what appears in the
     * URL; `permission` is the gate on top of the plan feature, because a
     * finance report is not something every member-viewing user should read.
     *
     * @return array<int, array{key:string,title:string,description:string,icon:string,permission:?string}>
     */
    public function catalogue(): array
    {
        return [
            [
                'key' => 'attendance_summary',
                'title' => 'Attendance summary',
                'description' => 'Turnout per session with a present/late/absent breakdown, and the trend over the period.',
                'icon' => 'check-in',
                'permission' => 'attendance.manage',
            ],
            [
                'key' => 'member_growth',
                'title' => 'Membership & growth',
                'description' => 'Headcount, new members and status mix over the period, by branch.',
                'icon' => 'users',
                'permission' => 'members.view',
            ],
            [
                'key' => 'giving_summary',
                'title' => 'Giving & finance',
                'description' => 'Income by category against expenses, with net position — approved, non-void entries only.',
                'icon' => 'wallet',
                'permission' => 'finance.view',
            ],
            [
                'key' => 'branch_performance',
                'title' => 'Branch performance',
                'description' => 'Side-by-side members, giving and attendance for every branch.',
                'icon' => 'branches',
                'permission' => 'members.view',
            ],
        ];
    }

    public function find(string $key): ?array
    {
        foreach ($this->catalogue() as $report) {
            if ($report['key'] === $key) {
                return $report;
            }
        }

        return null;
    }

    /**
     * Resolve the per-report payload. Returns a normalised shape every report
     * view understands: `columns`, `rows` and a `summary` of labelled figures,
     * plus an optional `chart` series. Keeping the shape uniform is what lets
     * one view render all of them.
     *
     * @return array{columns:array,rows:array,summary:array,chart:?array}
     */
    public function run(string $key, Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        return match ($key) {
            'attendance_summary' => $this->attendanceSummary($from, $to, $branchId),
            'member_growth' => $this->memberGrowth($from, $to, $branchId),
            'giving_summary' => $this->givingSummary($from, $to, $branchId),
            'branch_performance' => $this->branchPerformance($from, $to),
            default => ['columns' => [], 'rows' => [], 'summary' => [], 'chart' => null],
        };
    }

    /** @return array{columns:array,rows:array,summary:array,chart:?array} */
    public function attendanceSummary(Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $sessions = AttendanceSession::query()
            ->when($branchId, fn ($q) => $q->where('organizational_unit_id', $branchId))
            ->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('session_date')
            ->get();

        $rows = [];
        $totalPresent = 0;
        $totalRecorded = 0;

        foreach ($sessions as $session) {
            $records = $session->records()->get();
            $counts = [
                'present' => $records->where('status', 'present')->count(),
                'late' => $records->where('status', 'late')->count(),
                'excused' => $records->where('status', 'excused')->count(),
                'absent' => $records->where('status', 'absent')->count(),
            ];
            $present = $counts['present'] + $counts['late'];
            $recorded = $records->count();
            $totalPresent += $present;
            $totalRecorded += $recorded;

            $rows[] = [
                'Session' => $session->name,
                'Date' => $session->session_date?->format('d M Y') ?? '—',
                'Type' => ucfirst($session->type),
                'Present' => number_format($counts['present']),
                'Late' => number_format($counts['late']),
                'Absent' => number_format($counts['absent']),
                'Excused' => number_format($counts['excused']),
                'Turnout' => $recorded > 0 ? round(($present / $recorded) * 100).'%' : '—',
            ];
        }

        return [
            'columns' => ['Session', 'Date', 'Type', 'Present', 'Late', 'Absent', 'Excused', 'Turnout'],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Sessions', 'value' => number_format($sessions->count())],
                ['label' => 'Total present', 'value' => number_format($totalPresent)],
                ['label' => 'Records kept', 'value' => number_format($totalRecorded)],
                ['label' => 'Overall turnout', 'value' => $totalRecorded > 0 ? round(($totalPresent / $totalRecorded) * 100).'%' : '—'],
            ],
            'chart' => [
                'labels' => $sessions->map(fn ($s) => $s->session_date?->format('d M'))->all(),
                'series' => [[
                    'label' => 'Present',
                    'data' => $sessions->map(fn ($s) => $s->presentCount())->all(),
                ]],
            ],
        ];
    }

    /** @return array{columns:array,rows:array,summary:array,chart:?array} */
    public function memberGrowth(Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $base = Member::query()->when($branchId, fn ($q) => $q->where('organizational_unit_id', $branchId));

        $new = (clone $base)->whereBetween('created_at', [$from->startOfDay(), $to->copy()->endOfDay()])->count();
        $total = (clone $base)->count();

        // Status mix, one row per status — the same taxonomy the members grid
        // filters on, so the numbers reconcile with the module page.
        $statuses = ['active', 'inactive', 'visitor', 'transferred'];
        $rows = [];
        foreach ($statuses as $status) {
            $count = (clone $base)->where('membership_status', $status)->count();
            $rows[] = [
                'Status' => ucfirst($status),
                'Members' => number_format($count),
                'Share' => $total > 0 ? round(($count / $total) * 100).'%' : '—',
            ];
        }

        // Month-by-month sign-ups across the period for the trend chart.
        $labels = [];
        $series = [];
        $cursor = $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $labels[] = $cursor->format('M Y');
            $series[] = (clone $base)
                ->whereYear('created_at', $cursor->year)
                ->whereMonth('created_at', $cursor->month)
                ->count();
            $cursor->addMonthNoOverflow();
        }

        return [
            'columns' => ['Status', 'Members', 'Share'],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Total members', 'value' => number_format($total)],
                ['label' => 'New in period', 'value' => number_format($new)],
                ['label' => 'Active', 'value' => number_format((clone $base)->where('membership_status', 'active')->count())],
                ['label' => 'Growth rate', 'value' => $total > 0 ? round(($new / $total) * 100, 1).'%' : '—'],
            ],
            'chart' => ['labels' => $labels, 'series' => [['label' => 'New members', 'data' => $series]]],
        ];
    }

    /** @return array{columns:array,rows:array,summary:array,chart:?array} */
    public function givingSummary(Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        // Giving is attributed to a branch through the member who gave; a
        // church-wide (anonymous) gift has no member and therefore no branch,
        // and is counted only when no branch filter is applied.
        $query = Transaction::query()
            ->approved()->notVoid()
            ->whereBetween('transacted_on', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn ($q) => $q->whereHas('member', fn ($m) => $m->where('organizational_unit_id', $branchId)));

        $incomeTypes = ['income', 'donation', 'transfer_in'];
        $expenseTypes = ['expense', 'transfer_out'];

        $income = (clone $query)->whereIn('type', $incomeTypes)->sum('amount');
        $expenses = (clone $query)->whereIn('type', $expenseTypes)->sum('amount');

        $byCategory = (clone $query)
            ->whereIn('type', $incomeTypes)
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as entries')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $rows = $byCategory->map(fn ($r) => [
            'Category' => $r->category ?: 'Uncategorised',
            'Entries' => number_format($r->entries),
            'Total' => number_format((float) $r->total, 2),
        ])->all();

        // Month-by-month income/expense for the period.
        $labels = [];
        $incomeSeries = [];
        $expenseSeries = [];
        $cursor = $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $range = [$cursor->copy()->startOfMonth()->toDateString(), $cursor->copy()->endOfMonth()->toDateString()];
            $labels[] = $cursor->format('M Y');
            $incomeSeries[] = (float) (clone $query)->whereIn('type', $incomeTypes)->whereBetween('transacted_on', $range)->sum('amount');
            $expenseSeries[] = (float) (clone $query)->whereIn('type', $expenseTypes)->whereBetween('transacted_on', $range)->sum('amount');
            $cursor->addMonthNoOverflow();
        }

        return [
            'columns' => ['Category', 'Entries', 'Total'],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Total income', 'value' => number_format((float) $income, 2)],
                ['label' => 'Total expenses', 'value' => number_format((float) $expenses, 2)],
                ['label' => 'Net position', 'value' => number_format((float) $income - (float) $expenses, 2)],
                ['label' => 'Categories', 'value' => number_format($byCategory->count())],
            ],
            'chart' => [
                'labels' => $labels,
                'series' => [
                    ['label' => 'Income', 'data' => $incomeSeries],
                    ['label' => 'Expenses', 'data' => $expenseSeries],
                ],
            ],
        ];
    }

    /** @return array{columns:array,rows:array,summary:array,chart:?array} */
    public function branchPerformance(Carbon $from, Carbon $to): array
    {
        $units = OrganizationalUnit::query()->with('unitType')->orderBy('name')->get();

        $rows = $units->map(function (OrganizationalUnit $unit) use ($from, $to) {
            $members = Member::query()->where('organizational_unit_id', $unit->id)->count();

            $giving = Transaction::query()
                ->approved()->notVoid()
                ->whereIn('type', ['income', 'donation', 'transfer_in'])
                ->whereBetween('transacted_on', [$from->toDateString(), $to->toDateString()])
                ->whereHas('member', fn ($m) => $m->where('organizational_unit_id', $unit->id))
                ->sum('amount');

            $sessions = AttendanceSession::query()
                ->where('organizational_unit_id', $unit->id)
                ->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
                ->get();

            $present = $sessions->sum(fn ($s) => $s->presentCount());
            $recorded = $sessions->sum(fn ($s) => $s->records()->count());

            return [
                'Branch' => $unit->name,
                'Members' => number_format($members),
                'Giving' => number_format((float) $giving, 2),
                'Sessions' => number_format($sessions->count()),
                'Turnout' => $recorded > 0 ? round(($present / $recorded) * 100).'%' : '—',
            ];
        })->all();

        $totalMembers = $units->sum(fn ($u) => Member::query()->where('organizational_unit_id', $u->id)->count());

        return [
            'columns' => ['Branch', 'Members', 'Giving', 'Sessions', 'Turnout'],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Branches', 'value' => number_format($units->count())],
                ['label' => 'Members in branches', 'value' => number_format($totalMembers)],
                ['label' => 'Total giving', 'value' => number_format((float) Transaction::query()
                    ->approved()->notVoid()
                    ->whereIn('type', ['income', 'donation', 'transfer_in'])
                    ->whereBetween('transacted_on', [$from->toDateString(), $to->toDateString()])
                    ->whereNotNull('member_id')
                    ->sum('amount'), 2)],
                ['label' => 'Unassigned members', 'value' => number_format(Member::query()->whereNull('organizational_unit_id')->count())],
            ],
            'chart' => [
                'labels' => $units->pluck('name')->all(),
                'series' => [[
                    'label' => 'Members',
                    'data' => $units->map(fn ($u) => Member::query()->where('organizational_unit_id', $u->id)->count())->all(),
                ]],
            ],
        ];
    }

    /**
     * Rows for CSV export — a plain header + data matrix the controller can
     * stream without caring which report produced it.
     *
     * @param  array{columns:array,rows:array}  $payload
     * @return array<int, array<int, string>>
     */
    public function toCsvMatrix(array $payload): array
    {
        $matrix = [$payload['columns']];

        foreach ($payload['rows'] as $row) {
            $line = [];
            foreach ($payload['columns'] as $column) {
                $line[] = (string) ($row[$column] ?? '');
            }
            $matrix[] = $line;
        }

        return $matrix;
    }

    /** Branch options for the filter dropdown. @return Collection<int, OrganizationalUnit> */
    public function branchOptions(): Collection
    {
        return OrganizationalUnit::query()->orderBy('name')->get(['id', 'name']);
    }
}

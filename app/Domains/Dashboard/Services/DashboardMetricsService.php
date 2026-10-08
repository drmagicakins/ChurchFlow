<?php

namespace App\Domains\Dashboard\Services;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Member;
use App\Models\OrganizationalUnit;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Every figure here is computed on read from the same tables the module
 * pages use (Members, Transactions, Departments, AuditLog) — there is no
 * cached/denormalized counter that could drift from reality. Trends compare
 * the current calendar month against the previous one, per §18.
 *
 * Each public method is independent and cheap enough to call individually,
 * so DashboardController can skip whichever ones a permission-gated panel
 * doesn't need rather than computing everything up front.
 */
class DashboardMetricsService
{
    /**
     * Trend = growth in the headcount since the start of this month, i.e.
     * (now − at month start) / at month start, so "↑ 12% this month" means the
     * number itself grew 12%, not that sign-ups did.
     *
     * The active trend applies the same method to members who are active *now*:
     * membership status history isn't tracked, so "active at month start" can't
     * be reconstructed exactly — this is the closest honest proxy.
     *
     * @return array{total:int, active:int, totalTrendPct:?float, activeTrendPct:?float}
     */
    public function memberStats(): array
    {
        $monthStart = now()->startOfMonth();

        $total = Member::query()->count();
        $totalAtStart = Member::query()->where('created_at', '<', $monthStart)->count();

        $active = Member::query()->where('membership_status', 'active')->count();
        $activeAtStart = Member::query()->where('membership_status', 'active')
            ->where('created_at', '<', $monthStart)->count();

        return [
            'total' => $total,
            'active' => $active,
            'totalTrendPct' => $this->percentChange($totalAtStart, $total),
            'activeTrendPct' => $this->percentChange($activeAtStart, $active),
        ];
    }

    /** @return array{income:string, expenses:string, incomeTrendPct:?float, expensesTrendPct:?float} */
    public function financeStats(): array
    {
        // Month-to-date vs the same span of the previous month (day 1 → same
        // day-of-month), so a mid-month reading isn't compared to a full month.
        $thisMonth = [now()->startOfMonth(), now()->endOfDay()];
        $prevStart = now()->subMonthNoOverflow()->startOfMonth();
        $lastMonth = [$prevStart, $prevStart->copy()->addDays(now()->day - 1)->endOfDay()];

        $incomeThis = $this->sumByType(['income', 'donation', 'transfer_in'], $thisMonth);
        $incomeLast = $this->sumByType(['income', 'donation', 'transfer_in'], $lastMonth);
        $expenseThis = $this->sumByType(['expense', 'transfer_out'], $thisMonth);
        $expenseLast = $this->sumByType(['expense', 'transfer_out'], $lastMonth);

        return [
            'income' => $incomeThis,
            'expenses' => $expenseThis,
            'incomeTrendPct' => $this->percentChange((float) $incomeLast, (float) $incomeThis),
            'expensesTrendPct' => $this->percentChange((float) $expenseLast, (float) $expenseThis),
        ];
    }

    /**
     * Monthly income/expense totals for the trailing $months, oldest first —
     * shape matches what the Chart.js line/bar combo on the dashboard needs.
     *
     * @return array{labels: string[], income: float[], expenses: float[]}
     */
    public function financialSeries(int $months = 6): array
    {
        $labels = [];
        $income = [];
        $expenses = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonthsNoOverflow($i);
            $range = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];

            $labels[] = $month->format('M');
            $income[] = (float) $this->sumByType(['income', 'donation', 'transfer_in'], $range);
            $expenses[] = (float) $this->sumByType(['expense', 'transfer_out'], $range);
        }

        return compact('labels', 'income', 'expenses');
    }

    /** @return array{newMembers:int, paymentsReceived:string, eventsHeld:int, messagesSent:int} */
    public function activitySummary(): array
    {
        $thisMonth = [now()->startOfMonth(), now()];

        return [
            'newMembers' => Member::query()->whereBetween('created_at', $thisMonth)->count(),
            'paymentsReceived' => $this->sumByType(['income', 'donation'], $thisMonth),
            'eventsHeld' => \App\Models\Event::query()
                ->whereBetween('starts_at', [now()->startOfMonth(), now()])
                ->count(),
            'messagesSent' => \App\Models\Announcement::query()
                ->whereBetween('created_at', $thisMonth)
                ->count(),
        ];
    }

    /**
     * Human-readable recent activity feed straight from the audit trail —
     * every action a tenant-owned model logs on create/update/delete (see
     * AuditableObserver), not a bespoke "activity" table that could get out
     * of sync with what actually happened.
     *
     * @return Collection<int, array{icon:string, tone:string, label:string, when: Carbon}>
     */
    public function recentActivities(int $limit = 6): Collection
    {
        return AuditLog::query()
            ->latest('created_at')
            ->take($limit)
            ->get()
            ->map(fn (AuditLog $log) => $this->describe($log));
    }

    /**
     * @return Collection<int, array{name:string, subtitle:string, icon:string, tone:string}>
     */
    public function departmentsOverview(int $limit = 6): Collection
    {
        $branches = OrganizationalUnit::query()
            ->whereNull('parent_id')
            ->with('unitType')
            ->get()
            ->map(fn (OrganizationalUnit $unit) => [
                'name' => $unit->name,
                'subtitle' => $unit->unitType?->name ?? 'Branch',
                'icon' => 'building',
                'tone' => 'blue',
            ]);

        $departments = Department::query()
            ->withCount('members')
            ->get()
            ->map(fn (Department $d) => [
                'name' => $d->name,
                'subtitle' => number_format($d->members_count).' members',
                'icon' => 'users',
                'tone' => 'green',
            ]);

        return $branches->concat($departments)->take($limit)->values();
    }

    private function sumByType(array $types, array $range): string
    {
        return (string) Transaction::query()
            ->approved()->notVoid()
            ->whereIn('type', $types)
            ->whereBetween('transacted_on', $range)
            ->sum('amount');
    }

    private function percentChange(float $previous, float $current): ?float
    {
        if ($previous == 0.0) {
            return $current > 0 ? 100.0 : null; // no prior baseline to compare against
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function describe(AuditLog $log): array
    {
        [$model, $verb] = array_pad(explode('.', $log->action, 2), 2, 'updated');
        $new = $log->new_values ?? [];

        [$label, $icon, $tone] = match ($model) {
            'member' => [
                $verb === 'created'
                    ? 'New member registered — '.($new['full_name'] ?? 'Unnamed member')
                    : ucfirst($verb).' — member '.($new['full_name'] ?? '#'.$log->auditable_id),
                'users', 'blue',
            ],
            'transaction' => [
                isset($new['type']) && in_array($new['type'], ['income', 'donation'], true)
                    ? 'Payment received — ₦'.number_format((float) ($new['amount'] ?? 0))
                    : 'Expense recorded — ₦'.number_format((float) ($new['amount'] ?? 0)),
                'wallet', 'green',
            ],
            'event' => [
                'Event '.$verb.' — '.($new['title'] ?? '#'.$log->auditable_id),
                'calendar', 'purple',
            ],
            'attendancerecord' => ['Attendance updated', 'check-in', 'orange'],
            'announcement' => ['Announcement posted — '.($new['title'] ?? ''), 'megaphone', 'pink'],
            'department' => [ucfirst($verb).' department — '.($new['name'] ?? ''), 'building', 'blue'],
            default => [\Illuminate\Support\Str::headline(class_basename($log->auditable_type ?? $model)).' '.$verb, 'sparkle', 'blue'],
        };

        return ['icon' => $icon, 'tone' => $tone, 'label' => rtrim($label, ' —'), 'when' => $log->created_at];
    }
}

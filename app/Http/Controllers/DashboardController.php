<?php

namespace App\Http\Controllers;

use App\Domains\Calendar\Services\CalendarService;
use App\Domains\Dashboard\Services\DashboardMetricsService;
use App\Domains\Subscriptions\Services\TrialService;
use App\Models\Announcement;
use App\Models\Approval;
use App\Models\Event;
use App\Models\FinancialAccount;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubventionSubmission;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The dashboard is a real overview, not a welcome banner. Every figure here is
 * derived from the same services the module pages use — no cached counters, so a
 * dashboard can never disagree with the page it links to.
 *
 * Each panel is individually guarded: an administrator without `finance.view`
 * gets a dashboard without the finance panel rather than a 403 on the whole page.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, CalendarService $calendar, DashboardMetricsService $metrics, TrialService $trials): View
    {
        $user = $request->user();
        $church = $user->church;

        // Phase 11: the subscription panel. Only for someone who can actually
        // act on it — showing a trial countdown to a user who cannot reach the
        // billing page would be a countdown to something they can't fix.
        $subscription = $user->hasPermission('billing.manage')
            ? Subscription::query()->with(['plan', 'pendingPlan'])->latest()->first()
            : null;

        $upcoming = $calendar
            ->itemsBetween(now()->startOfDay(), now()->addDays(30)->endOfDay())
            ->take(6);

        // Quick actions link to the index pages that host each create form
        // (this app has no standalone /create routes). Filtered by the same
        // permission strings the policies enforce, so a hidden action is
        // also one the backend would refuse — the check here is display only.
        $quickActions = collect([
            ['label' => 'Add Member', 'icon' => 'users', 'tone' => 'blue', 'route' => 'members.index', 'permission' => 'members.create'],
            ['label' => 'Record Payment', 'icon' => 'wallet', 'tone' => 'green', 'route' => 'finance.accounts.index', 'permission' => 'finance.record'],
            ['label' => 'Create Event', 'icon' => 'calendar', 'tone' => 'purple', 'route' => 'events.index', 'permission' => 'events.manage'],
            ['label' => 'Send Message', 'icon' => 'chat', 'tone' => 'orange', 'route' => 'announcements.index', 'permission' => 'announcements.manage'],
        ])->filter(fn ($a) => $user->hasPermission($a['permission']))->values();

        $canMembers = $user->hasPermission('members.view');
        $canFinance = $user->hasPermission('finance.view');

        return view('dashboard', [
            'quickActions' => $quickActions,
            'subscription' => $subscription,
            'trialDaysRemaining' => $subscription ? $trials->daysRemaining($subscription) : 0,
            'memberStats' => $canMembers ? $metrics->memberStats() : null,
            'financeStats' => $canFinance ? $metrics->financeStats() : null,
            'financialSeries' => $canFinance ? $metrics->financialSeries(6) : null,
            'activitySummary' => $canMembers ? $metrics->activitySummary() : null,
            'recentActivities' => $user->hasPermission('settings.manage') ? $metrics->recentActivities(6) : null,
            'departmentsOverview' => $canMembers ? $metrics->departmentsOverview(6) : null,
            'todaysSchedule' => Event::query()
                ->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])
                ->orderBy('starts_at')
                ->get(),
            'church' => $church,
            'memberCount' => $this->can($user, 'members.view', fn () => Member::query()->count()),
            'activeMembers' => $this->can($user, 'members.view', fn () => Member::query()->where('membership_status', 'active')->count()),
            'upcoming' => $upcoming,
            'openTasks' => $this->can($user, 'members.view', fn () => Task::query()
                ->whereNotIn('status', ['done', 'cancelled'])
                ->count()),
            'upcomingEvents' => Event::query()
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->take(4)
                ->get(),
            'pendingApprovals' => $this->can($user, 'finance.approve', fn () => Approval::query()
                ->where('status', 'pending')
                ->count()),
            'recentAnnouncements' => Announcement::query()->latest()->take(3)->get(),
            'accounts' => $this->can($user, 'finance.view', fn () => FinancialAccount::query()
                ->orderBy('name')
                ->get()),
            'activeLoans' => $this->can($user, 'loans.manage', fn () => Loan::query()
                ->where('status', 'active')
                ->count()),
            'subventions' => $this->can($user, 'subvention.submit', fn () => SubventionSubmission::query()
                ->with('period')
                ->latest('id')
                ->take(4)
                ->get()),
        ]);
    }

    /**
     * Runs a panel's query only if the user holds the permission, returning null
     * otherwise so the view can hide the panel. Deliberately not an abort: one
     * missing permission should thin the dashboard, not break it.
     */
    private function can($user, string $permission, \Closure $query): mixed
    {
        return $user->hasPermission($permission) ? $query() : null;
    }
}
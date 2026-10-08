<?php

namespace App\Http\Controllers\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\View\View;

/**
 * The platform operator's home screen.
 *
 * This was a two-line stub — `<x-layout><h1>Platform Admin</h1></x-layout>` —
 * so the one person who needs an overview of the whole business had no
 * overview at all. That is a strange gap: every church-scoped dashboard in
 * the app was built out properly, and the screen that aggregates them was
 * empty.
 *
 * Every query here relies on PlatformAdminOnly setting `tenant.disabled`,
 * which every tenant-scoped model's global scope already checks. Nothing
 * calls withoutGlobalScopes() — so if the middleware were ever missing, these
 * queries would return nothing rather than leaking one church's data into a
 * cross-tenant view. Failing closed, silently, is the right failure for a
 * screen that shows every tenant at once.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        // Subscription status is the source of truth for what each church can
        // do; churches.status is its cache. Grouping by the subscription's own
        // status (not the cache) keeps this consistent with the billing screen.
        $byStatus = Subscription::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $subscriptions = Subscription::query()->count();

        return view('platform-admin.dashboard', [
            'churchCount' => Church::query()->count(),
            'userCount' => User::query()->count(),
            'subscriptionCount' => $subscriptions,
            'trialingCount' => (int) ($byStatus['trialing'] ?? 0),
            'activeCount' => (int) ($byStatus['active'] ?? 0),
            'paidOrTrialing' => (int) ($byStatus['active'] ?? 0) + (int) ($byStatus['trialing'] ?? 0),
            'statusBreakdown' => $byStatus,
            'planCount' => Plan::query()->count(),
            'activePlanCount' => Plan::where('is_active', true)->count(),

            // Both revenue streams, kept separate on purpose — §31 is explicit
            // that subscription and SMS revenue must stay distinguishable, and
            // a single "total revenue" number is exactly what would hide a
            // collapse in one of them.
            'subscriptionRevenue' => Invoice::query()->where('type', 'subscription')->where('status', 'paid')->sum('amount'),
            'smsRevenue' => Invoice::query()->where('type', 'sms_credits')->where('status', 'paid')->sum('amount'),
            'unpaidInvoiceCount' => Invoice::query()->where('status', '!=', 'paid')->count(),

            // The most recent churches, which is what an operator actually
            // checks after a signup wave.
            'recentChurches' => Church::query()
                ->withCount('users')
                ->latest('id')
                ->limit(8)
                ->get(),

            // Churches whose subscription needs attention — a support queue
            // rather than a report.
            'needsAttention' => Subscription::query()
                ->with('church')
                ->whereIn('status', ['past_due', 'grace_period', 'expired', 'suspended'])
                ->latest('updated_at')
                ->limit(8)
                ->get(),
        ]);
    }
}

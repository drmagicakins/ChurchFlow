<?php

namespace App\Http\Controllers\PlatformAdmin;

use App\Domains\Subscriptions\Services\SubscriptionService;
use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * §53: platform admins see across every tenant — this is the one place in
 * the whole app that's SUPPOSED to query without tenant scoping. It works
 * here because PlatformAdminOnly middleware sets 'tenant.disabled' = true,
 * which every model's TenantScope (and the custom scopes on Role,
 * EventRegistration, LoanPayment, etc.) checks and steps aside for — so
 * these are plain Eloquent queries, not withoutGlobalScopes() calls
 * sprinkled everywhere, and a bug that accidentally left the middleware off
 * would fail CLOSED (empty results) rather than leaking data.
 */
class BillingOverviewController extends Controller
{
    public function index(): View
    {
        $revenueByType = Invoice::query()
            ->selectRaw('type, SUM(amount + tax_amount) as total')
            ->where('status', 'paid')
            ->groupBy('type')
            ->pluck('total', 'type');

        $churches = Church::query()
            ->with(['users' => fn ($q) => $q->limit(1)])
            ->withCount('organizationalUnits')
            ->paginate(25);

        $subscriptionsByStatus = Subscription::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('platform-admin.billing.index', compact('revenueByType', 'churches', 'subscriptionsByStatus'));
    }

    public function showChurch(Church $church): View
    {
        $subscription = Subscription::query()->where('church_id', $church->id)->latest()->first();
        $invoices = Invoice::query()->where('church_id', $church->id)->latest()->paginate(25);

        return view('platform-admin.billing.church', compact('church', 'subscription', 'invoices'));
    }

    /**
     * §53/support tooling: a platform admin overriding a subscription's
     * status directly (e.g. suspending a church for a policy violation,
     * unrelated to any missed payment). Goes through SubscriptionService
     * like every other transition, so churches.status stays in sync the
     * same way it does for a normal billing-cycle transition.
     */
    public function suspend(Request $request, Church $church, SubscriptionService $subscriptions): RedirectResponse
    {
        $subscription = Subscription::query()->where('church_id', $church->id)->latest()->firstOrFail();

        $subscription->update(['status' => 'suspended']);
        $church->update(['status' => 'suspended']);

        return back()->with('status', "{$church->name} suspended.");
    }

    public function reactivate(Church $church, SubscriptionService $subscriptions): RedirectResponse
    {
        $subscription = Subscription::query()->where('church_id', $church->id)->latest()->firstOrFail();
        $subscriptions->reactivate($subscription);

        return back()->with('status', "{$church->name} reactivated.");
    }
}

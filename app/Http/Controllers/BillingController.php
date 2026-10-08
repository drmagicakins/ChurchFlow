<?php

namespace App\Http\Controllers;

use App\Domains\Communication\Services\SmsWalletService;
use App\Domains\PlatformAdmin\Services\PlatformSettingsService;
use App\Domains\Subscriptions\Services\CheckoutService;
use App\Domains\Subscriptions\Services\ProrationCalculator;
use App\Domains\Subscriptions\Services\SubscriptionService;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly SmsWalletService $wallets,
        private readonly CheckoutService $checkouts,
        private readonly PlatformSettingsService $settings,
        private readonly ProrationCalculator $proration,
    ) {}

    private function authorizeBilling(Request $request): void
    {
        abort_unless($request->user()->hasPermission('billing.manage'), 403);
    }

    /** §29: subscription billing and SMS billing shown as clearly separate things. */
    public function show(Request $request): View
    {
        $this->authorizeBilling($request);

        $church = $request->user()->church;
        $subscription = Subscription::query()->with('plan')->latest()->first();

        return view('billing.show', [
            'subscription' => $subscription,
            'smsWallet' => $this->wallets->walletFor($church),
            'invoices' => Invoice::query()->latest()->limit(10)->get(),
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function invoices(Request $request): View
    {
        $this->authorizeBilling($request);

        return view('billing.invoices', ['invoices' => Invoice::query()->latest()->paginate(25)]);
    }

    /** §13: recorded, not instant — access continues to period end. */
    public function requestCancellation(Request $request): RedirectResponse
    {
        $this->authorizeBilling($request);

        $subscription = Subscription::query()->latest()->firstOrFail();
        $this->subscriptions->requestCancellation($subscription);

        return back()->with('status', 'Cancellation requested. Your access continues until '.$subscription->current_period_end->toFormattedDateString().'.');
    }

    public function reactivate(Request $request): RedirectResponse
    {
        $this->authorizeBilling($request);

        $subscription = Subscription::query()->latest()->firstOrFail();
        $this->subscriptions->reactivate($subscription);

        return back()->with('status', 'Subscription reactivated.');
    }

    /** §13: preview the prorated charge/credit before committing to a plan change. */
    public function previewPlanChange(Request $request): View
    {
        $this->authorizeBilling($request);

        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id']]);
        $subscription = Subscription::query()->with('plan')->latest()->firstOrFail();
        $newPlan = Plan::findOrFail($data['plan_id']);

        return view('billing.plan-change-preview', [
            'subscription' => $subscription,
            'newPlan' => $newPlan,
            'proration' => $this->proration->calculate($subscription, $newPlan),
        ]);
    }

    /**
     * §13: "Do not silently change billing." Never just swaps the plan_id —
     * computes the prorated net amount for the remainder of the current
     * period and records it as its own Invoice (a negative amount is a
     * credit, applied as a line item rather than an immediate refund),
     * BEFORE changing the plan, so there's always a record of exactly what
     * the switch cost or credited.
     */
    public function changePlan(Request $request): RedirectResponse
    {
        $this->authorizeBilling($request);

        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id']]);
        $subscription = Subscription::query()->with('plan')->latest()->firstOrFail();
        $newPlan = Plan::findOrFail($data['plan_id']);

        $proration = $this->proration->calculate($subscription, $newPlan);

        \App\Models\Invoice::create([
            'church_id' => $subscription->church_id,
            'type' => 'subscription',
            'amount' => $proration['net'],
            'status' => 'paid', // §13: this simplified flow settles proration as a statement credit/charge, not a separate live gateway transaction — see the README's noted limitation
            'description' => "Proration: {$subscription->plan->name} → {$newPlan->name} ({$proration['days_remaining']} days remaining)",
        ]);

        $this->subscriptions->changePlan($subscription, $newPlan);

        $direction = bccomp($proration['net'], '0', 2) > 0 ? 'charge' : 'credit';

        return redirect()->route('billing.show')->with(
            'status',
            "Plan changed to {$newPlan->name}. A {$direction} of ".abs((float) $proration['net'])." has been recorded for the remainder of this billing period."
        );
    }

    /** §17-18: SMS credit purchase — a separate checkout from the subscription. */
    public function buySmsCredits(Request $request): RedirectResponse
    {
        $this->authorizeBilling($request);

        $data = $request->validate(['package' => ['required', 'in:1000,5000,10000,25000']]);

        // §18/§28: pricing must be platform-configurable at runtime, never
        // hard-coded — PlatformSettingsService checks the admin-editable
        // override first and falls back to the env default.
        $pricePerUnit = $this->settings->smsPricePerUnit();
        abort_if($pricePerUnit === null, 503, 'SMS pricing has not been configured by the platform administrator.');

        $units = (int) $data['package'];
        $amount = bcmul((string) $units, (string) $pricePerUnit, 2);

        [$checkout, $redirectUrl] = $this->checkouts->startSmsCreditsCheckout(
            $request->user(), $request->user()->church, $units, $amount,
        );

        return redirect()->away($redirectUrl);
    }
}

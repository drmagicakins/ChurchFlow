<?php

namespace App\Http\Controllers;

use App\Domains\Communication\Services\SmsWalletService;
use App\Domains\PlatformAdmin\Services\PlatformSettingsService;
use App\Domains\Subscriptions\Services\CheckoutService;
use App\Domains\Subscriptions\Services\ProrationCalculator;
use App\Domains\Subscriptions\Services\SubscriptionService;
use App\Domains\Subscriptions\Services\TrialService;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * §29: everything a church needs to run its own subscription — see what it
 * is on, subscribe when trialing, move up or down a plan, cancel, reactivate,
 * buy SMS credit. Deliberately all on one page: "where do I pay you" should
 * have exactly one answer.
 *
 * PHASE 11 — TRIAL AWARENESS
 * --------------------------
 * Three behaviours changed for the 14-day trial, and each one is a decision
 * rather than a mechanism:
 *
 *  1. `subscribeNow()` — a trialing church is charged TODAY, and gets a full
 *     paid period from today. Making someone wait out their remaining trial
 *     days before they are allowed to pay is a worse experience than taking
 *     their money when they offer it.
 *  2. `changePlan()` during a trial records the intent in `pending_plan_id`
 *     and does NOT switch the plan. Switching mid-trial would move every
 *     limit (members, branches, admins) instantly, letting someone trial on
 *     the top plan and drop to the cheapest on day 13 — and worse, plan
 *     limits read from `plan_id`, so a "downgrade" could instantly lock a
 *     church out of its own data. Intent is recorded; the switch happens at
 *     conversion, which is what the person is actually promising to pay for.
 *  3. `previewPlanChange()` says plainly which of the two it is doing, so
 *     the proration maths shown is the maths that will apply.
 */
class BillingController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly SmsWalletService $wallets,
        private readonly CheckoutService $checkouts,
        private readonly PlatformSettingsService $settings,
        private readonly ProrationCalculator $proration,
        private readonly TrialService $trials,
    ) {}

    private function authorizeBilling(Request $request): void
    {
        abort_unless($request->user()->hasPermission('billing.manage'), 403);
    }

    /** The church's current subscription, newest first. */
    private function currentSubscription(): ?Subscription
    {
        return Subscription::query()->with(['plan', 'pendingPlan'])->latest()->first();
    }

    /** §29: subscription billing and SMS billing shown as clearly separate things. */
    public function show(Request $request): View
    {
        $this->authorizeBilling($request);

        $church = $request->user()->church;
        $subscription = $this->currentSubscription();

        return view('billing.show', [
            'subscription' => $subscription,
            'trialDaysRemaining' => $subscription ? $this->trials->daysRemaining($subscription) : 0,
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

    /**
     * Phase 11: convert a trial into a paid subscription, right now.
     *
     * Uses TrialService, which charges through the same gateway abstraction
     * the renewal cycle uses — so this is not a parallel payment path that
     * could drift from the real one.
     */
    public function subscribeNow(Request $request): RedirectResponse
    {
        $this->authorizeBilling($request);

        $subscription = $this->currentSubscription();
        abort_unless($subscription && $subscription->isTrialing(), 409, 'There is no active trial to subscribe.');

        try {
            $this->trials->subscribeNow($subscription);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // 402 from TrialService means the gateway refused the charge. The
            // subscription is deliberately left trialing so the trial days
            // the church has left are not thrown away by a failed attempt.
            return back()->withErrors(['billing' => $e->getMessage()]);
        }

        return redirect()->route('billing.show')
            ->with('status', 'Your subscription is active. Thank you — you will be billed again on '.$subscription->fresh()->current_period_end->toFormattedDateString().'.');
    }

    public function reactivate(Request $request): RedirectResponse
    {
        $this->authorizeBilling($request);

        $subscription = Subscription::query()->latest()->firstOrFail();
        $this->subscriptions->reactivate($subscription);

        return back()->with('status', 'Subscription reactivated.');
    }

    /**
     * §13: preview the prorated charge/credit before committing to a plan change.
     *
     * During a trial there is nothing to prorate — no money has changed hands
     * yet — so the preview says so instead of showing a proration of zero and
     * letting someone infer a free upgrade.
     */
    public function previewPlanChange(Request $request): View
    {
        $this->authorizeBilling($request);

        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id']]);
        $subscription = Subscription::query()->with('plan')->latest()->firstOrFail();
        $newPlan = Plan::findOrFail($data['plan_id']);

        return view('billing.plan-change-preview', [
            'subscription' => $subscription,
            'newPlan' => $newPlan,
            'isTrial' => $subscription->isTrialing(),
            // Null during a trial on purpose: there is no period to prorate
            // against, and passing a zeroed array would read as "this change
            // is free", which is not the same statement at all.
            'proration' => $subscription->isTrialing() ? null : $this->proration->calculate($subscription, $newPlan),
            'trialDaysRemaining' => $this->trials->daysRemaining($subscription),
        ]);
    }

    /**
     * §13: "Do not silently change billing."
     *
     * An ACTIVE subscription computes the prorated net amount for the rest of
     * the current period and records it as its own Invoice (a negative amount
     * is a credit, applied as a line item rather than an immediate refund),
     * BEFORE changing the plan — so there is always a record of exactly what
     * the switch cost or credited.
     *
     * A TRIALING subscription does none of that: nothing has been charged, so
     * there is nothing to prorate and no invoice to write. It records the
     * chosen plan in `pending_plan_id` and changes nothing else. See the class
     * docblock for why switching the plan itself mid-trial is the wrong move.
     */
    public function changePlan(Request $request): RedirectResponse
    {
        $this->authorizeBilling($request);

        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id']]);
        $subscription = Subscription::query()->with('plan')->latest()->firstOrFail();
        $newPlan = Plan::findOrFail($data['plan_id']);

        if ($subscription->isTrialing()) {
            $subscription->update(['pending_plan_id' => $newPlan->id]);

            return redirect()->route('billing.show')->with(
                'status',
                "Your trial stays on {$subscription->plan->name} for now. You'll move to {$newPlan->name} automatically when your trial ends and your subscription begins."
            );
        }

        $proration = $this->proration->calculate($subscription, $newPlan);

        Invoice::create([
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

    /**
     * The public plan catalogue from a signed-in church's point of view: every
     * active plan, with the current one marked. This is where "upgrade" and
     * "downgrade" actually live — a single table where the church can compare
     * and choose, rather than a separate UI per direction (they are the same
     * action with a different sign on the price difference, and treating them
     * as two screens is how they drift apart).
     *
     * Lives here rather than in PlanController because this is the
     * decision-maker's view: it needs the current subscription to label
     * anything, whereas PlanController::index is the pre-signup catalogue.
     */
    public function plans(Request $request): View
    {
        $this->authorizeBilling($request);

        $subscription = $this->currentSubscription();

        return view('billing.plans', [
            'subscription' => $subscription,
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->get(),
            'currentPlanId' => $subscription?->plan_id,
            'pendingPlanId' => $subscription?->pending_plan_id,
            'isTrial' => (bool) $subscription?->isTrialing(),
            'trialDaysRemaining' => $subscription ? $this->trials->daysRemaining($subscription) : 0,
        ]);
    }
}

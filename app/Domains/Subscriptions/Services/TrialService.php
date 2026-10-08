<?php

namespace App\Domains\Subscriptions\Services;

use App\Domains\Subscriptions\Gateways\PaymentGatewayInterface;
use App\Models\Church;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * The 14-day trial, and its conversion to a paid subscription.
 *
 * WHY A TRIAL IS A REAL SUBSCRIPTION ROW
 * --------------------------------------
 * It would have been easier to give a trial church no subscription at all and
 * teach every other class to special-case "trialing". That is exactly the kind
 * of branch that rots: plan limits (`PlanLimitService`), the access gate
 * (`EnsureSubscriptionAllowsAccess`), the billing screen, the renewal cycle —
 * each would need its own `if (trialing)` and each could disagree with the
 * others. Instead a trial IS a subscription whose status is `trialing`:
 * status is still the single source of truth, `churches.status` is still the
 * one denormalized cache written only by `SubscriptionService`, and every
 * existing query keeps working unchanged.
 *
 * The only genuinely new state is `trial_ends_at`, and the only new rule is
 * what happens when it passes.
 *
 * WHAT HAPPENS AT THE END OF THE TRIAL
 * ------------------------------------
 * `convert()` is the whole conversion, and it is deliberately all-or-nothing:
 *
 *   - payment succeeds -> the plan becomes the (possibly changed) plan, the
 *     period becomes a real paid period, the subscription goes `active`, an
 *     invoice is written. This is the normal path.
 *   - payment fails -> `past_due`, which the existing dunning chain
 *     (`RenewalAndDunningService`) then walks through grace_period to
 *     expired on the standard timings. Nothing new is invented for this.
 *
 * `expireExhaustedTrials()` is the safety net for a church that never even
 * attempted to pay: it simply marks the subscription `past_due` rather than
 * expiring it outright, so the same dunning timings apply to a lapsed trial
 * as to a lapsed paid subscription. A trial that runs out is never a special
 * case that deletes or hides anything — §35 still holds.
 */
class TrialService
{
    /** The one place the trial length is defined. */
    public const TRIAL_DAYS = 14;

    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    /**
     * Used by ActivateChurchFromCheckout: give a brand-new church its 14 free
     * days instead of charging it immediately. No invoice is written, because
     * nothing has been charged — an invoice for a trial would be a lie.
     */
    public function startTrial(Church $church, Plan $plan, string $billingInterval): Subscription
    {
        $endsAt = now()->addDays(self::TRIAL_DAYS);

        $subscription = Subscription::create([
            'church_id' => $church->id,
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'billing_interval' => $billingInterval,
            'current_period_start' => now(),
            // The trial period IS the first billing period, so the period
            // dates and the trial dates are the same window. That keeps
            // proration and every period-based query meaningful during a
            // trial rather than referring to a period that doesn't exist.
            'current_period_end' => $endsAt,
            'trial_ends_at' => $endsAt,
        ]);

        $this->subscriptions->syncStatus($church, 'trial');

        return $subscription;
    }

    /**
     * The church owner's explicit "subscribe now" during a trial. Charges
     * immediately and starts a real paid period from today — someone who
     * chooses to pay on day 3 has bought their month now, they are not made
     * to wait until day 14.
     *
     * Idempotent in the same way the webhook path is: converting an already
     * active subscription is refused rather than charged twice.
     */
    public function subscribeNow(Subscription $subscription): Subscription
    {
        abort_unless(
            $subscription->isTrialing(),
            409,
            'This subscription is not trialing. Use change-plan for an active subscription.'
        );

        return $this->convert($subscription);
    }

    /**
     * Charge the trial's chosen plan and turn the subscription into a real
     * paid one. Returns the invoice on success; throws on failure so the
     * caller can show the reason rather than a silent no-op.
     */
    public function convert(Subscription $subscription, ?Plan $plan = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $plan) {
            $subscription = Subscription::withoutGlobalScopes()
                ->whereKey($subscription->id)->lockForUpdate()->first();

            $targetPlan = $plan
                ?? ($subscription->pending_plan_id ? Plan::find($subscription->pending_plan_id) : null)
                ?? $subscription->plan;

            $result = $this->gateway->chargeRecurring($subscription);

            abort_unless(
                $result->success,
                402,
                $result->failureReason ?? 'The payment could not be completed. Please check your payment details and try again.'
            );

            $amount = $targetPlan->priceFor($subscription->billing_interval);

            \App\Models\Invoice::create([
                'church_id' => $subscription->church_id,
                'type' => 'subscription',
                'amount' => $amount,
                'currency' => $targetPlan->currency,
                'status' => 'paid',
                'description' => "{$targetPlan->name} plan ({$subscription->billing_interval})",
                'provider' => $subscription->provider,
                'provider_reference' => $result->providerReference,
                'paid_at' => now(),
            ]);

            $periodEnd = $subscription->billing_interval === 'yearly'
                ? now()->addYear()
                : now()->addMonthNoOverflow();

            $subscription->update([
                'plan_id' => $targetPlan->id,
                'pending_plan_id' => null,
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
                'trial_ends_at' => null,
                'trial_converted_at' => now(),
            ]);

            $this->subscriptions->syncStatus($subscription->church, 'active');

            return $subscription->fresh();
        });
    }

    /**
     * The daily safety net, run alongside the paid renewal cycle: a trial
     * whose window has closed without the owner ever choosing to pay moves
     * to `past_due`, not straight to `expired`.
     *
     * That is deliberate. The alternative (expire the moment the trial ends)
     * gives a church that simply forgot to pay a hard cut-off with no
     * warning, while a church whose card was declined gets the full grace
     * period. Two different outcomes for the same "we owe money" state is
     * the kind of inconsistency nobody can explain to a customer. So:
     * the trial ends into `past_due`, and the existing
     * `RenewalAndDunningService` timings take it from there for everyone.
     *
     * @return int the number of trials moved out of `trialing`
     */
    public function expireExhaustedTrials(): int
    {
        $count = 0;

        Subscription::withoutGlobalScopes()
            ->where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->each(function (Subscription $subscription) use (&$count) {
                $this->subscriptions->markPastDue($subscription);
                $count++;
            });

        return $count;
    }

    /** Days left, floored at zero — never a negative countdown on a lapsed trial. */
    public function daysRemaining(Subscription $subscription): int
    {
        if (! $subscription->isTrialing() || ! $subscription->trial_ends_at) {
            return 0;
        }

        return max((int) now()->startOfDay()->diffInDays($subscription->trial_ends_at->startOfDay(), false), 0);
    }
}

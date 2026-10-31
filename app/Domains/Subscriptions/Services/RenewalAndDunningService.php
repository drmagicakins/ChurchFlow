<?php

namespace App\Domains\Subscriptions\Services;

use App\Domains\Subscriptions\Gateways\PaymentGatewayInterface;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

/**
 * The daily billing cycle §9's states imply but never got a scheduler for:
 *
 *   active, period ended, charge succeeds    -> renew (new period, invoice)
 *   active, period ended, charge fails       -> past_due
 *   past_due for > GRACE_PERIOD_DAYS         -> grace_period
 *   grace_period for > EXPIRE_AFTER_DAYS     -> expired
 *   cancel_requested_at set, period ended    -> cancelled (§13: access
 *                                               continues until period end,
 *                                               which this is what enforces)
 *
 * Every transition still goes through SubscriptionService, so
 * churches.status stays the single cache it's always been — this class
 * only decides WHEN a transition should happen, never writes the status
 * itself.
 */
class RenewalAndDunningService
{
    // Timing is measured from the moment the subscription ENTERED its current
    // status (past_due_at / grace_period_started_at), not from `updated_at`.
    // Using `updated_at` meant any unrelated write to the row — a plan note, an
    // admin touch, a cancellation flag — reset the clock and silently EXTENDED
    // a delinquent church's access. Rows created before these columns existed
    // have NULL and fall back to `updated_at`, so no legacy row jumps state on
    // deploy. Both columns are written only by SubscriptionService's
    // markPastDue()/enterGracePeriod(), so there is one writer, not two.
    private const GRACE_PERIOD_DAYS = 5;
    private const EXPIRE_AFTER_DAYS = 10; // days spent in grace_period before expiring

    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PaymentGatewayInterface $gateway,
        private readonly TrialService $trials,
    ) {}

    /** @return array{renewed:int, past_due:int, grace_period:int, expired:int, cancelled:int, trials_lapsed:int} */
    public function runDailyCycle(): array
    {
        $counts = ['renewed' => 0, 'past_due' => 0, 'grace_period' => 0, 'expired' => 0, 'cancelled' => 0, 'trials_lapsed' => 0];

        // Phase 11: a trial whose 14 days are up and which nobody chose to
        // convert moves to past_due FIRST, so it then flows through the
        // ordinary grace_period/expired chain below on the same timings a
        // lapsed paid subscription gets. Doing this before the other passes
        // means a trial that lapsed today is picked up by this same run
        // rather than waiting a day.
        $counts['trials_lapsed'] = $this->trials->expireExhaustedTrials();

        $this->finalizeRequestedCancellations($counts);
        $this->attemptRenewals($counts);
        $this->advancePastDueToGracePeriod($counts);
        $this->expireOverdueGracePeriods($counts);

        return $counts;
    }

    private function finalizeRequestedCancellations(array &$counts): void
    {
        Subscription::withoutGlobalScopes()
            ->whereNotNull('cancel_requested_at')
            ->whereIn('status', ['active', 'past_due', 'grace_period'])
            ->where('current_period_end', '<=', now()->toDateString())
            ->each(function (Subscription $subscription) use (&$counts) {
                $this->subscriptions->cancel($subscription);
                $counts['cancelled']++;
            });
    }

    private function attemptRenewals(array &$counts): void
    {
        Subscription::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereNull('cancel_requested_at')
            ->where('current_period_end', '<=', now()->toDateString())
            ->each(function (Subscription $subscription) use (&$counts) {
                $result = $this->gateway->chargeRecurring($subscription);

                if ($result->success) {
                    $amount = $subscription->plan->priceFor($subscription->billing_interval);

                    Invoice::create([
                        'church_id' => $subscription->church_id,
                        'type' => 'subscription',
                        'amount' => $amount,
                        'status' => 'paid',
                        'description' => "{$subscription->plan->name} plan renewal ({$subscription->billing_interval})",
                        'provider' => $subscription->provider,
                        'provider_reference' => $result->providerReference,
                        'paid_at' => now(),
                    ]);

                    $this->subscriptions->renew($subscription);
                    $counts['renewed']++;
                } else {
                    Log::warning('Subscription renewal charge failed', [
                        'subscription_id' => $subscription->id,
                        'reason' => $result->failureReason,
                    ]);
                    $this->subscriptions->markPastDue($subscription);
                    $counts['past_due']++;
                }
            });
    }

    private function advancePastDueToGracePeriod(array &$counts): void
    {
        $threshold = now()->subDays(self::GRACE_PERIOD_DAYS);

        Subscription::withoutGlobalScopes()
            ->where('status', 'past_due')
            ->where(function ($query) use ($threshold) {
                $query->where('past_due_at', '<=', $threshold)
                    // Legacy rows (pre-2026_10_24 migration) have no explicit
                    // timestamp. Falling back keeps their behaviour identical to
                    // before rather than skipping or instantly advancing them.
                    ->orWhere(function ($legacy) use ($threshold) {
                        $legacy->whereNull('past_due_at')
                            ->where('updated_at', '<=', $threshold);
                    });
            })
            ->each(function (Subscription $subscription) use (&$counts) {
                $this->subscriptions->enterGracePeriod($subscription);
                $counts['grace_period']++;
            });
    }

    private function expireOverdueGracePeriods(array &$counts): void
    {
        $threshold = now()->subDays(self::EXPIRE_AFTER_DAYS);

        Subscription::withoutGlobalScopes()
            ->where('status', 'grace_period')
            ->where(function ($query) use ($threshold) {
                $query->where('grace_period_started_at', '<=', $threshold)
                    ->orWhere(function ($legacy) use ($threshold) {
                        $legacy->whereNull('grace_period_started_at')
                            ->where('updated_at', '<=', $threshold);
                    });
            })
            ->each(function (Subscription $subscription) use (&$counts) {
                $this->subscriptions->expire($subscription);
                $counts['expired']++;
            });
    }
}

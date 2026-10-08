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
    // Timing uses `updated_at` as a proxy for "when did this subscription
    // enter its current status" rather than a dedicated timestamp column
    // per state. Simple, but fragile if something unrelated ever touches
    // the row while it's past_due/grace_period (e.g. changePlan) — a real
    // deployment should add explicit past_due_at/grace_period_started_at
    // columns if that turns out to matter.
    private const GRACE_PERIOD_DAYS = 5;
    private const EXPIRE_AFTER_DAYS = 10; // days spent in grace_period before expiring

    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    /** @return array{renewed:int, past_due:int, grace_period:int, expired:int, cancelled:int} */
    public function runDailyCycle(): array
    {
        $counts = ['renewed' => 0, 'past_due' => 0, 'grace_period' => 0, 'expired' => 0, 'cancelled' => 0];

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
        Subscription::withoutGlobalScopes()
            ->where('status', 'past_due')
            ->where('updated_at', '<=', now()->subDays(self::GRACE_PERIOD_DAYS))
            ->each(function (Subscription $subscription) use (&$counts) {
                $this->subscriptions->enterGracePeriod($subscription);
                $counts['grace_period']++;
            });
    }

    private function expireOverdueGracePeriods(array &$counts): void
    {
        Subscription::withoutGlobalScopes()
            ->where('status', 'grace_period')
            ->where('updated_at', '<=', now()->subDays(self::EXPIRE_AFTER_DAYS))
            ->each(function (Subscription $subscription) use (&$counts) {
                $this->subscriptions->expire($subscription);
                $counts['expired']++;
            });
    }
}

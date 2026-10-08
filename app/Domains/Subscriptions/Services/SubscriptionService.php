<?php

namespace App\Domains\Subscriptions\Services;

use App\Models\Church;
use App\Models\Plan;
use App\Models\Subscription;

/**
 * subscriptions.status is the source of truth; churches.status is a
 * denormalized cache of it, updated ONLY here — the same pattern Phase 4
 * used for Transaction.approval_status via ApprovalWorkflow. Nothing else
 * in the app should ever write churches.status directly.
 */
class SubscriptionService
{
    public function createInitial(Church $church, Plan $plan, string $billingInterval): Subscription
    {
        $periodEnd = $billingInterval === 'yearly' ? now()->addYear() : now()->addMonthNoOverflow();

        $subscription = Subscription::create([
            'church_id' => $church->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => $billingInterval,
            'current_period_start' => now(),
            'current_period_end' => $periodEnd,
        ]);

        $this->syncChurchStatus($church, 'active');

        return $subscription;
    }

    public function renew(Subscription $subscription): Subscription
    {
        $periodEnd = $subscription->billing_interval === 'yearly'
            ? $subscription->current_period_end->addYear()
            : $subscription->current_period_end->addMonthNoOverflow();

        $subscription->update([
            'status' => 'active',
            'current_period_start' => $subscription->current_period_end,
            'current_period_end' => $periodEnd,
        ]);

        $this->syncChurchStatus($subscription->church, 'active');

        return $subscription;
    }

    public function markPastDue(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => 'past_due']);
        $this->syncChurchStatus($subscription->church, 'past_due');

        return $subscription;
    }

    public function enterGracePeriod(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => 'grace_period']);
        $this->syncChurchStatus($subscription->church, 'grace_period');

        return $subscription;
    }

    /** §35: expiring never deletes data — access is restricted, nothing more, until reactivation. */
    public function expire(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => 'expired']);
        $this->syncChurchStatus($subscription->church, 'expired');

        return $subscription;
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $this->syncChurchStatus($subscription->church, 'cancelled');

        return $subscription;
    }

    public function requestCancellation(Subscription $subscription): Subscription
    {
        // Access continues until the period actually ends — this only
        // records the request; a scheduled job (not built in this phase)
        // would call cancel() once current_period_end passes.
        $subscription->update(['cancel_requested_at' => now()]);

        return $subscription;
    }

    public function reactivate(Subscription $subscription): Subscription
    {
        abort_unless(
            in_array($subscription->status, ['expired', 'cancelled', 'suspended'], true),
            409,
            'Only an expired, cancelled, or suspended subscription can be reactivated.'
        );

        return $this->renew($subscription);
    }

    public function changePlan(Subscription $subscription, Plan $newPlan): Subscription
    {
        $subscription->update(['plan_id' => $newPlan->id]);

        return $subscription;
    }

    private function syncChurchStatus(Church $church, string $status): void
    {
        $church->update(['status' => $status]);
    }
}

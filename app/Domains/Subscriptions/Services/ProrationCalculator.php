<?php

namespace App\Domains\Subscriptions\Services;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Carbon;

/**
 * §13: "Clearly explain prorated charges/credits". Classic day-based
 * proration: credit the unused days on the old plan, charge for the
 * remaining days on the new plan, net the two. A positive result is an
 * amount still owed now; a negative result is a credit (this app applies
 * it as a negative-amount Invoice rather than an immediate refund — see
 * BillingController::changePlan).
 */
class ProrationCalculator
{
    /** @return array{days_remaining:int, days_in_period:int, credit:string, charge:string, net:string} */
    public function calculate(Subscription $subscription, Plan $newPlan, ?Carbon $today = null): array
    {
        $today = $today ?? now()->startOfDay();
        $periodStart = Carbon::parse($subscription->current_period_start)->startOfDay();
        $periodEnd = Carbon::parse($subscription->current_period_end)->startOfDay();

        $daysInPeriod = max((int) $periodStart->diffInDays($periodEnd), 1);
        // Absolute diff + an explicit "already past" check, rather than
        // relying on Carbon's signed-diff sign convention (easy to get
        // backwards and hard to notice when it is).
        $daysRemaining = $today->greaterThanOrEqualTo($periodEnd) ? 0 : (int) $today->diffInDays($periodEnd);

        $oldPlan = $subscription->plan;
        // Multiply before dividing, at full precision, then round the final
        // amount. Dividing first and truncating the daily rate to a fixed
        // number of decimals (the previous approach) lost fractions of a cent
        // on the way through: 10000/30 truncated to 6dp then x15 came out as
        // 4999.99 instead of 5000.00. bcmul(x, days) / daysInPeriod keeps the
        // exact value and only the final result is rounded to cents.
        $oldPrice = (string) $oldPlan->priceFor($subscription->billing_interval);
        $newPrice = (string) $newPlan->priceFor($subscription->billing_interval);
        $days = (string) $daysRemaining;
        $period = (string) $daysInPeriod;

        $credit = bcdiv(bcmul($oldPrice, $days, 6), $period, 2);
        $charge = bcdiv(bcmul($newPrice, $days, 6), $period, 2);
        $net = bcsub($charge, $credit, 2);

        return [
            'days_remaining' => $daysRemaining,
            'days_in_period' => $daysInPeriod,
            'credit' => $credit,
            'charge' => $charge,
            'net' => $net,
        ];
    }
}

<?php

namespace App\Domains\Subscriptions\Services;

use App\Models\Church;
use App\Models\Subscription;

/**
 * §36/§14: resolve which plan a church is on and whether that plan enables a
 * named feature. The `features` JSON on `plans` has existed since Phase 8
 * (api_access, custom_domain, advanced_reports) but nothing read it — so the
 * features a pricing page promised had no effect anywhere in the app. This is
 * the single accessor those checks go through, so the rule can't drift between
 * callers the way three inline Subscription queries would.
 *
 * Reads are deliberately tolerant: a church with no subscription yet (mid
 * signup, or a platform-admin viewing) resolves to "no features" rather than
 * throwing — the caller decides what that means, and none of the feature gates
 * are load-bearing for basic access.
 */
class PlanFeatureService
{
    /** The active subscription for the church, if any. */
    public function subscriptionFor(?Church $church): ?Subscription
    {
        if (! $church) {
            return null;
        }

        return Subscription::withoutGlobalScopes()
            ->with('plan')
            ->where('church_id', $church->id)
            ->latest()
            ->first();
    }

    public function hasFeature(?Church $church, string $feature): bool
    {
        $subscription = $this->subscriptionFor($church);

        if (! $subscription || ! $subscription->plan) {
            return false;
        }

        // A trialing church gets the plan it is trialing, wholesale — the
        // point of a trial is to experience the plan, features included.
        return $subscription->plan->hasFeature($feature);
    }

    /**
     * The feature's value as a human string for a locked panel: names the plan
     * that DOES include it, so "upgrade" is actionable rather than vague.
     * Returns null when some plan already includes it (or the church has it).
     */
    public function requiredPlanName(string $feature): ?string
    {
        $plan = \App\Models\Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->first(fn ($p) => $p->hasFeature($feature));

        return $plan?->name;
    }
}

<?php

namespace App\Domains\Subscriptions\Services;

use App\Models\Plan;

/**
 * The bridge between "what the marketing page says" and "what the app
 * charges".
 *
 * These used to be two independent copies of the same price list. The
 * `plans` table held real numbers; config('marketing.plans') held the copy
 * AND a `price => null` on every tier, so the pricing page rendered
 * "Price on request" while the checkout page charged a specific amount. Both
 * were individually "correct" and the combination was indefensible.
 *
 * This class resolves one from the other, with an explicit precedence:
 *
 *   1. The `plans` ROW wins when it exists, because that is what the
 *      application actually charges — checkout, plan limits and invoices all
 *      read from it, so a price the platform admin edited at runtime must be
 *      the price the marketing page shows. Showing the config default here
 *      while charging the database value would recreate exactly the bug this
 *      class exists to kill.
 *   2. config('billing.plans') is the fallback for a tier that has no row yet
 *      (a fresh install where PlanSeeder hasn't run, or a tier that exists as
 *      marketing copy only, like Enterprise).
 *
 * Note the direction of the fallback: the database overrides the config, the
 * config only fills gaps. That is the opposite of how config usually works,
 * and it is deliberate — see the docblock on config/billing.php.
 */
class PlanCatalog
{
    /**
     * The full catalogue for display: every marketing tier, in order, with its
     * resolved price attached.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forDisplay(): array
    {
        $tiers = config('marketing.plans', []);
        $rows = Plan::whereIn('slug', array_column($tiers, 'key'))->get()->keyBy('slug');

        return array_map(function (array $tier) use ($rows) {
            $plan = $rows->get($tier['key']);

            return $tier + [
                // Resolved price strings, ready to render. Null monthly_price
                // (Enterprise) intentionally yields "Custom" rather than a
                // number — a bespoke agreement has no honest sticker price.
                'price' => $plan?->displayPrice('monthly'),
                'yearly_price' => $plan?->yearly_price !== null ? $plan->displayPrice('yearly') : null,
                'is_self_serve' => $plan?->isSelfServe() ?? false,
                'plan_id' => $plan?->id,
            ];
        }, $tiers);
    }

    /**
     * The tiers a church can actually buy, from the database.
     *
     * Filtered on is_active, so Enterprise (advertised with is_active = false)
     * is correctly absent: it is shown on the pricing page with a "Talk to us"
     * CTA and must not appear in a self-serve checkout picker.
     */
    public function purchasable()
    {
        return Plan::where('is_active', true)->orderBy('sort_order')->get();
    }
}

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
        $fallbacks = config('billing.plans', []);
        $rows = Plan::whereIn('slug', array_column($tiers, 'key'))->get()->keyBy('slug');

        return array_map(function (array $tier) use ($rows, $fallbacks) {
            $plan = $rows->get($tier['key']);

            // Fall back to config('billing.plans') ONLY when the tier has no row.
            //
            // This is the documented precedence (database wins, config fills
            // gaps) and it is load-bearing: on a fresh install where PlanSeeder
            // has not run — or if every row is later deleted — reading only the
            // `plans` table would resolve every price to null and render the
            // whole pricing page as "Custom"/blank. A price that is written down
            // in config is a better answer than a blank page, and it is the same
            // number checkout would use once the seeder runs.
            //
            // The key detail: when a ROW EXISTS, its values are used verbatim,
            // including a deliberate null. `$plan->monthly_price ?? $config[...]`
            // would have treated a row that says "this tier is quoted, not
            // listed" (null price) as "no row" and resurrected the config
            // number — so clearing a yearly price in the database would not have
            // removed the annual toggle, and nulling Enterprise's price would
            // have quoted it anyway. A row's null is a decision; the config is
            // only the default for a tier nobody has defined yet.
            $config = $fallbacks[$tier['key']] ?? null;

            if ($plan) {
                $monthly = $plan->monthly_price;
                $yearly = $plan->yearly_price;
                $currency = $plan->currency;
            } else {
                $monthly = $config['monthly_price'] ?? null;
                $yearly = $config['yearly_price'] ?? null;
                $currency = 'NGN';
            }

            return $tier + [
                // Resolved price strings, ready to render. Null monthly_price
                // (Enterprise) intentionally yields "Custom" rather than a
                // number — a bespoke agreement has no honest sticker price.
                'price' => $monthly === null
                    ? 'Custom'
                    : Plan::currencySymbol($currency).number_format((float) $monthly),
                'yearly_price' => $yearly !== null
                    ? Plan::currencySymbol($currency).number_format((float) $yearly)
                    : null,
                'is_self_serve' => $monthly !== null,
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

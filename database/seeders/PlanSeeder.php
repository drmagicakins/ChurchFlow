<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Seeds the `plans` table from config('billing.plans') — the single place a
 * price is written down.
 *
 * It used to carry its own hard-coded array, which drifted from the marketing
 * pricing page (that one had `price => null` on every tier and rendered
 * "Price on request" while the database held real numbers). Two tables
 * disagreeing about what a plan costs is the worst possible bug on a pricing
 * page, so both now read the same array.
 *
 * Re-runnable: updateOrCreate on slug, so running it again after a price
 * change updates the existing rows rather than duplicating them. Note that a
 * platform admin who edits a plan through the admin area is editing the
 * DATABASE, and re-running this seeder will overwrite that edit — which is
 * the correct precedence for a seeder (config is the code-level default) but
 * worth knowing before running it against production.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Enterprise is seeded too, but with is_active = false — it is
        // advertised on the pricing page with a "Talk to us" CTA and is not
        // self-serve purchasable, so it must not appear in the checkout plan
        // picker (which filters on is_active).
        foreach (config('billing.plans') as $key => $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                [
                    'name' => $plan['name'],
                    'monthly_price' => $plan['monthly_price'],
                    'yearly_price' => $plan['yearly_price'],
                    'currency' => 'NGN',
                    'max_members' => $plan['max_members'],
                    'max_branches' => $plan['max_branches'],
                    'max_admins' => $plan['max_admins'],
                    'storage_mb' => $plan['storage_mb'],
                    'features' => $plan['features'],
                    'is_active' => $plan['is_active'] ?? true,
                    'sort_order' => $plan['sort_order'],
                ],
            );
        }
    }
}

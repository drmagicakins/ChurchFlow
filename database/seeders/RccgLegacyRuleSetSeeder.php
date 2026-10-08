<?php

namespace Database\Seeders;

use App\Models\Church;
use App\Models\SubventionRuleSet;
use Illuminate\Database\Seeder;

/**
 * Demonstrates the Phase 0 migration plan concretely: the legacy
 * tbl_groups table stored one row per rate tier with flat percentage
 * columns (general_rate, minister_rate, admin_rate, admin_max). Each
 * legacy group becomes one SubventionRuleSet with four rules here — no
 * PHP formula, no RCCG-specific code anywhere in the engine itself.
 *
 * Example legacy rates carried over (illustrative — replace with the
 * real values from the migrated tbl_groups rows for a real cutover):
 *   Group 1: general 25%, minister 25%, admin 5% capped at 5,000, salary is
 *            a plain figure entered per submission (not a rate), classified
 *            as a deduction on its own via a `fixed`-per-submission value —
 *            modeled here as a percentage rule with rate 100 against a
 *            `salary` figure, which is equivalent to "pass the figure
 *            through unchanged" without a special-cased rule type.
 */
class RccgLegacyRuleSetSeeder extends Seeder
{
    public function run(): void
    {
        $church = Church::first();

        if (!$church) {
            $this->command?->warn('No church found — run this after a church exists.');
            return;
        }

        $groups = [
            'Group 1' => ['general' => 25.00, 'minister' => 25.00, 'admin' => 5.00, 'admin_max' => 5000.00],
            'Group 2' => ['general' => 20.00, 'minister' => 20.00, 'admin' => 5.00, 'admin_max' => 4000.00],
            'Group 3' => ['general' => 15.00, 'minister' => 15.00, 'admin' => 5.00, 'admin_max' => 3000.00],
        ];

        foreach ($groups as $name => $rates) {
            $ruleSet = SubventionRuleSet::firstOrCreate(
                ['church_id' => $church->id, 'name' => $name],
            );

            $ruleSet->rules()->delete();

            $ruleSet->rules()->createMany([
                [
                    'name' => 'General Tithe Retention',
                    'base_field' => 'general_tithe',
                    'type' => 'percentage',
                    'rate' => $rates['general'],
                    'classification' => 'retention',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Minister Tithe Retention',
                    'base_field' => 'minister_tithe',
                    'type' => 'percentage',
                    'rate' => $rates['minister'],
                    'classification' => 'retention',
                    'sort_order' => 2,
                ],
                [
                    'name' => 'Administrative Fee',
                    'base_field' => 'admin_base',
                    'type' => 'capped_percentage',
                    'rate' => $rates['admin'],
                    'cap_amount' => $rates['admin_max'],
                    'classification' => 'deduction',
                    'sort_order' => 3,
                ],
                [
                    'name' => 'Salary',
                    'base_field' => 'salary',
                    'type' => 'percentage',
                    'rate' => 100.00, // pass the submitted figure through unchanged
                    'classification' => 'deduction',
                    'sort_order' => 4,
                ],
            ]);
        }
    }
}

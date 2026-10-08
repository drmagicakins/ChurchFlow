<?php

namespace Tests\Feature;

use App\Domains\Subventions\Services\SubventionCalculationEngine;
use App\Models\Church;
use App\Models\Loan;
use App\Models\OrganizationalUnit;
use App\Models\SubventionPeriod;
use App\Models\SubventionRuleSet;
use App\Models\SubventionSubmission;
use App\Models\UnitType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubventionCalculationEngineTest extends TestCase
{
    use RefreshDatabase;

    private function branch(Church $church): OrganizationalUnit
    {
        $unitType = UnitType::create(['church_id' => $church->id, 'name' => 'Province', 'level' => 0]);

        return OrganizationalUnit::create([
            'church_id' => $church->id, 'unit_type_id' => $unitType->id, 'name' => 'Abia Province 01',
        ]);
    }

    private function ruleSetLikeLegacyGroup1(Church $church): SubventionRuleSet
    {
        $ruleSet = SubventionRuleSet::factory()->for($church, 'church')->create();

        $ruleSet->rules()->createMany([
            ['name' => 'General Tithe Retention', 'base_field' => 'general_tithe', 'type' => 'percentage', 'rate' => 25, 'classification' => 'retention', 'sort_order' => 1],
            ['name' => 'Minister Tithe Retention', 'base_field' => 'minister_tithe', 'type' => 'percentage', 'rate' => 25, 'classification' => 'retention', 'sort_order' => 2],
            ['name' => 'Administrative Fee', 'base_field' => 'admin_base', 'type' => 'capped_percentage', 'rate' => 5, 'cap_amount' => 5000, 'classification' => 'deduction', 'sort_order' => 3],
            ['name' => 'Salary', 'base_field' => 'salary', 'type' => 'percentage', 'rate' => 100, 'classification' => 'deduction', 'sort_order' => 4],
        ]);

        return $ruleSet;
    }

    public function test_calculation_reproduces_the_legacy_formula_shape(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $ruleSet = $this->ruleSetLikeLegacyGroup1($church);
        $period = SubventionPeriod::factory()->for($church, 'church')->create();
        $branch = $this->branch($church);

        $submission = SubventionSubmission::create([
            'church_id' => $church->id,
            'subvention_period_id' => $period->id,
            'organizational_unit_id' => $branch->id,
            'subvention_rule_set_id' => $ruleSet->id,
            'figures' => [
                'general_tithe' => '10000.00', // 25% = 2500
                'minister_tithe' => '4000.00',  // 25% = 1000
                'admin_base' => '10000.00',     // 5% = 500 (under the 5000 cap)
                'salary' => '2000.00',          // 100% passthrough = 2000
            ],
        ]);

        $calc = (new SubventionCalculationEngine())->calculate($submission);

        // retention = 2500 + 1000 = 3500; deduction = 500 + 2000 = 2500
        $this->assertSame('3500.00', $calc->retention_total);
        $this->assertSame('2500.00', $calc->deduction_total);
        // shortfall = deduction - retention = 2500 - 3500 = -1000 (a surplus)
        $this->assertSame('-1000.00', $calc->shortfall);
        $this->assertSame('0.00', $calc->remittance_amount, 'A surplus branch owes nothing.');
    }

    public function test_calculation_produces_a_positive_remittance_when_deductions_exceed_retention(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $ruleSet = $this->ruleSetLikeLegacyGroup1($church);
        $period = SubventionPeriod::factory()->for($church, 'church')->create();
        $branch = $this->branch($church);

        $submission = SubventionSubmission::create([
            'church_id' => $church->id,
            'subvention_period_id' => $period->id,
            'organizational_unit_id' => $branch->id,
            'subvention_rule_set_id' => $ruleSet->id,
            'figures' => [
                'general_tithe' => '1000.00', // 25% = 250
                'minister_tithe' => '400.00',  // 25% = 100
                'admin_base' => '1000.00',     // 5% = 50
                'salary' => '2000.00',         // passthrough = 2000
            ],
        ]);

        $calc = (new SubventionCalculationEngine())->calculate($submission);

        // retention = 250+100 = 350; deduction = 50+2000 = 2050; shortfall = 1700
        $this->assertSame('350.00', $calc->retention_total);
        $this->assertSame('2050.00', $calc->deduction_total);
        $this->assertSame('1700.00', $calc->shortfall);
        $this->assertSame('0.00', $calc->loan_deduction_applied, 'No active loan on this branch.');
        $this->assertSame('1700.00', $calc->remittance_amount);
    }

    public function test_admin_fee_is_clamped_at_its_cap(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $ruleSet = $this->ruleSetLikeLegacyGroup1($church);
        $period = SubventionPeriod::factory()->for($church, 'church')->create();
        $branch = $this->branch($church);

        $submission = SubventionSubmission::create([
            'church_id' => $church->id,
            'subvention_period_id' => $period->id,
            'organizational_unit_id' => $branch->id,
            'subvention_rule_set_id' => $ruleSet->id,
            'figures' => [
                'general_tithe' => '0', 'minister_tithe' => '0',
                'admin_base' => '500000.00', // 5% would be 25,000 — far over the 5,000 cap
                'salary' => '0',
            ],
        ]);

        $calc = (new SubventionCalculationEngine())->calculate($submission);
        $adminRow = collect($calc->breakdown)->firstWhere('name', 'Administrative Fee');

        $this->assertSame('5000.00', $adminRow['amount']);
    }

    public function test_loan_deduction_is_applied_and_never_exceeds_the_shortfall(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $ruleSet = $this->ruleSetLikeLegacyGroup1($church);
        $period = SubventionPeriod::factory()->for($church, 'church')->create();
        $branch = $this->branch($church);

        Loan::create([
            'church_id' => $church->id,
            'organizational_unit_id' => $branch->id,
            'principal_amount' => '10000.00',
            'monthly_deduction' => '5000.00', // deliberately larger than the shortfall below
            'status' => 'active',
        ]);

        $submission = SubventionSubmission::create([
            'church_id' => $church->id,
            'subvention_period_id' => $period->id,
            'organizational_unit_id' => $branch->id,
            'subvention_rule_set_id' => $ruleSet->id,
            'figures' => [
                'general_tithe' => '0', 'minister_tithe' => '0',
                'admin_base' => '0', 'salary' => '1000.00', // shortfall will be exactly 1000
            ],
        ]);

        $calc = (new SubventionCalculationEngine())->calculate($submission);

        $this->assertSame('1000.00', $calc->shortfall);
        // Corrected vs. legacy: clamped at the shortfall (1000), not the full monthly_deduction (5000).
        $this->assertSame('1000.00', $calc->loan_deduction_applied);
        $this->assertSame('0.00', $calc->remittance_amount);
    }

    public function test_recalculating_a_submission_never_overwrites_the_previous_calculation(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $ruleSet = $this->ruleSetLikeLegacyGroup1($church);
        $period = SubventionPeriod::factory()->for($church, 'church')->create();
        $branch = $this->branch($church);

        $submission = SubventionSubmission::create([
            'church_id' => $church->id,
            'subvention_period_id' => $period->id,
            'organizational_unit_id' => $branch->id,
            'subvention_rule_set_id' => $ruleSet->id,
            'figures' => ['general_tithe' => '1000', 'minister_tithe' => '0', 'admin_base' => '0', 'salary' => '0'],
        ]);

        $engine = new SubventionCalculationEngine();
        $first = $engine->calculate($submission);

        $submission->update(['figures' => ['general_tithe' => '2000', 'minister_tithe' => '0', 'admin_base' => '0', 'salary' => '0']]);
        $second = $engine->calculate($submission->fresh());

        $this->assertNotSame($first->id, $second->id, 'Recalculating must insert a new row, never update the old one.');
        $this->assertCount(2, $submission->fresh()->calculations);
        $this->assertSame($second->id, $submission->fresh()->latestCalculation->id);

        // The first calculation's own figures are untouched by the second run.
        $this->assertSame('250.00', $first->fresh()->retention_total); // 25% of 1000
    }

    public function test_a_church_cannot_see_another_churchs_rule_sets_or_submissions(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();

        SubventionRuleSet::factory()->for($churchA, 'church')->create(['name' => 'A Rules']);
        SubventionRuleSet::factory()->for($churchB, 'church')->create(['name' => 'B Rules']);

        app()->instance('tenant.church_id', $churchA->id);

        $this->assertCount(1, SubventionRuleSet::all());
        $this->assertSame('A Rules', SubventionRuleSet::first()->name);
    }
}

<?php

namespace Tests\Feature;

use App\Domains\Subventions\Services\SubventionWorkflowService;
use App\Models\Church;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SubventionPeriod;
use App\Models\SubventionRuleSet;
use App\Models\SubventionSubmission;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The subvention SCREENS, not just the service. The service was well covered;
 * the pages a person actually uses were bare stubs, so a broken variable or a
 * missing RCCG figure would never have shown up in the service tests.
 */
class SubventionPagesTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(Church $church, array $permissions): User
    {
        $ids = collect($permissions)->map(
            fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'x', 'label' => 'x'])->id
        );
        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::create(['church_id' => $church->id, 'name' => 'Subvention '.uniqid()]);
        $role->permissions()->attach($ids);
        $user->roles()->attach($role);

        return $user;
    }

    private function submission(Church $church, array $figures = []): SubventionSubmission
    {
        $unitType = UnitType::create(['church_id' => $church->id, 'name' => 'Province', 'level' => 0]);
        $branch = OrganizationalUnit::create(['church_id' => $church->id, 'unit_type_id' => $unitType->id, 'name' => 'Grace Branch']);

        $ruleSet = SubventionRuleSet::factory()->for($church, 'church')->create(['name' => 'RCCG Group 1']);
        $ruleSet->rules()->createMany([
            ['name' => 'General Tithe Retention', 'base_field' => 'general_tithe', 'type' => 'percentage', 'rate' => 25, 'classification' => 'retention', 'sort_order' => 1],
            ['name' => 'Minister Tithe Retention', 'base_field' => 'minister_tithe', 'type' => 'percentage', 'rate' => 25, 'classification' => 'retention', 'sort_order' => 2],
            ['name' => 'Administrative Fee', 'base_field' => 'admin_base', 'type' => 'capped_percentage', 'rate' => 5, 'cap_amount' => 5000, 'classification' => 'deduction', 'sort_order' => 3],
            ['name' => 'Salary', 'base_field' => 'salary', 'type' => 'percentage', 'rate' => 100, 'classification' => 'deduction', 'sort_order' => 4],
        ]);

        $period = SubventionPeriod::factory()->for($church, 'church')->create(['name' => 'Q3 2026']);

        return SubventionSubmission::create([
            'church_id' => $church->id,
            'subvention_period_id' => $period->id,
            'organizational_unit_id' => $branch->id,
            'subvention_rule_set_id' => $ruleSet->id,
            'figures' => array_merge([
                'general_tithe' => '100000',
                'minister_tithe' => '20000',
                'offering' => '40000',
                'special_offering' => '5000',
                'admin_base' => '100000',
                'salary' => '18000',
            ], $figures),
        ]);
    }

    public function test_the_submission_page_shows_every_rccg_figure(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = $this->userWith($church, ['subvention.submit']);
        $submission = $this->submission($church);

        $response = $this->actingAs($user)->get(route('subvention.submissions.show', $submission));

        $response->assertOk();
        // The figures the RCCG reference collects.
        $response->assertSee('General tithe');
        $response->assertSee('Minister tithe');
        $response->assertSee('Offerings');
        $response->assertSee('Special / thanksgiving');
        $response->assertSee('Salary total');
        $response->assertSee('₦100,000.00');
        $response->assertSee('₦40,000.00');
    }

    public function test_the_submission_page_shows_the_calculation_breakdown(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = $this->userWith($church, ['subvention.submit']);
        $submission = $this->submission($church);

        // Submitting calculates and stores the breakdown.
        app(SubventionWorkflowService::class)->submit($submission, $user);

        $response = $this->actingAs($user)->get(route('subvention.submissions.show', $submission->fresh()));

        $response->assertOk();
        $response->assertSee('General Tithe Retention');
        $response->assertSee('Administrative Fee');
        $response->assertSee('Retention total');
        $response->assertSee('Remittance due to HQ');

        // 25% of 100,000 = 25,000 retention on the general tithe alone.
        $response->assertSee('₦25,000.00');
    }

    public function test_the_submission_index_form_records_offerings_and_special_offerings(): void
    {
        $church = Church::factory()->create(['status' => 'active']);
        app()->instance('tenant.church_id', $church->id);
        $user = $this->userWith($church, ['subvention.submit', 'subvention.manage']);

        $unitType = UnitType::create(['church_id' => $church->id, 'name' => 'Province', 'level' => 0]);
        $branch = OrganizationalUnit::create(['church_id' => $church->id, 'unit_type_id' => $unitType->id, 'name' => 'Branch A']);
        $ruleSet = SubventionRuleSet::factory()->for($church, 'church')->create();
        $period = SubventionPeriod::factory()->for($church, 'church')->create();

        $response = $this->actingAs($user)->post(route('subvention.submissions.store'), [
            'subvention_period_id' => $period->id,
            'organizational_unit_id' => $branch->id,
            'subvention_rule_set_id' => $ruleSet->id,
            'figures' => [
                'general_tithe' => '150000',
                'minister_tithe' => '30000',
                'offering' => '60000',
                'special_offering' => '7500',
                'admin_base' => '150000',
                'salary' => '22000',
            ],
        ]);

        $response->assertRedirect();

        $submission = SubventionSubmission::firstOrFail();
        $this->assertSame('60000', $submission->figures['offering']);
        $this->assertSame('7500', $submission->figures['special_offering']);
        $this->assertSame('30000', $submission->figures['minister_tithe']);
    }

    public function test_a_draft_submission_can_be_submitted_through_the_page(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = $this->userWith($church, ['subvention.submit']);
        $submission = $this->submission($church);

        $this->actingAs($user)
            ->post(route('subvention.submissions.submit', $submission))
            ->assertRedirect();

        $this->assertSame('submitted', $submission->fresh()->status);
        $this->assertNotNull($submission->fresh()->latestCalculation);
    }

    public function test_a_reviewer_can_approve_through_the_page(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $submitter = $this->userWith($church, ['subvention.submit']);
        $reviewer = $this->userWith($church, ['subvention.approve', 'subvention.submit']);
        $submission = $this->submission($church);

        app(SubventionWorkflowService::class)->submit($submission, $submitter);

        $this->actingAs($reviewer)
            ->post(route('subvention.submissions.approve', $submission), ['comments' => 'Looks right.'])
            ->assertRedirect();

        $this->assertSame('approved', $submission->fresh()->status);
    }

    public function test_a_submission_page_renders_for_a_returned_submission_with_its_reason(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $submitter = $this->userWith($church, ['subvention.submit']);
        $reviewer = $this->userWith($church, ['subvention.approve', 'subvention.submit']);
        $submission = $this->submission($church);

        $workflow = app(SubventionWorkflowService::class);
        $workflow->submit($submission, $submitter);
        $workflow->returnForRevision($submission->fresh(), $reviewer, 'Salary figure looks wrong.');

        app()->instance('tenant.church_id', $church->id);
        $this->actingAs($submitter)
            ->get(route('subvention.submissions.show', $submission->fresh()))
            ->assertOk()
            ->assertSee('Salary figure looks wrong.');
    }

    /**
     * The direction is recorded on the row, not guessed from the data. A row
     * stored before the correction is flipped even when its stored shortfall
     * is exactly 0.00 — the case an inferred cut-off would have misread.
     */
    public function test_a_row_marked_with_the_old_direction_is_shown_the_right_way_round(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = $this->userWith($church, ['subvention.submit']);
        $submission = $this->submission($church);

        app(SubventionWorkflowService::class)->submit($submission, $user);

        // Rewrite the stored row as the old engine would have left it:
        // deductions − retention, here a 1,000 surplus stored as -1000.
        $calc = $submission->fresh()->latestCalculation;
        $calc->forceFill([
            'shortfall' => '-1000.00',
            'shortfall_direction' => \App\Models\SubventionCalculation::SHORTFALL_DEDUCTIONS_MINUS_RETENTION,
        ])->save();

        $this->assertSame(1000.0, $calc->fresh()->shortfallInCurrentDirection());

        $this->actingAs($user)
            ->get(route('subvention.submissions.show', $submission->fresh()))
            ->assertOk()
            ->assertSee('₦1,000.00');
    }

    public function test_a_new_calculation_records_the_corrected_direction(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = $this->userWith($church, ['subvention.submit']);
        $submission = $this->submission($church);

        app(SubventionWorkflowService::class)->submit($submission, $user);

        $this->assertSame(
            \App\Models\SubventionCalculation::SHORTFALL_RETENTION_MINUS_DEDUCTIONS,
            $submission->fresh()->latestCalculation->shortfall_direction
        );
    }
}

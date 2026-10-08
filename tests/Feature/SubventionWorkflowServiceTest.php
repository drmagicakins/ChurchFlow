<?php

namespace Tests\Feature;

use App\Domains\Approvals\Services\ApprovalWorkflow;
use App\Domains\Loans\Services\LoanService;
use App\Domains\Subventions\Services\SubventionCalculationEngine;
use App\Domains\Subventions\Services\SubventionWorkflowService;
use App\Models\Church;
use App\Models\Loan;
use App\Models\OrganizationalUnit;
use App\Models\SubventionPeriod;
use App\Models\SubventionRuleSet;
use App\Models\SubventionSubmission;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubventionWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubventionWorkflowService
    {
        return new SubventionWorkflowService(
            new SubventionCalculationEngine(),
            new ApprovalWorkflow(),
            new LoanService(),
        );
    }

    private function makeSubmission(Church $church, array $figures = [], ?Loan $loan = null): SubventionSubmission
    {
        $unitType = UnitType::create(['church_id' => $church->id, 'name' => 'Province', 'level' => 0]);
        $branch = OrganizationalUnit::create(['church_id' => $church->id, 'unit_type_id' => $unitType->id, 'name' => 'Test Branch']);

        $ruleSet = SubventionRuleSet::factory()->for($church, 'church')->create();
        $ruleSet->rules()->createMany([
            ['name' => 'General Retention', 'base_field' => 'general_tithe', 'type' => 'percentage', 'rate' => 25, 'classification' => 'retention', 'sort_order' => 1],
            ['name' => 'Salary', 'base_field' => 'salary', 'type' => 'percentage', 'rate' => 100, 'classification' => 'deduction', 'sort_order' => 2],
        ]);

        $period = SubventionPeriod::factory()->for($church, 'church')->create();

        if ($loan) {
            $loan->update(['organizational_unit_id' => $branch->id]);
        }

        return SubventionSubmission::create([
            'church_id' => $church->id,
            'subvention_period_id' => $period->id,
            'organizational_unit_id' => $branch->id,
            'subvention_rule_set_id' => $ruleSet->id,
            'figures' => array_merge(['general_tithe' => '0', 'salary' => '0'], $figures),
        ]);
    }

    public function test_submitting_a_draft_calculates_it_and_creates_a_pending_approval(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $submitter = User::factory()->create(['church_id' => $church->id]);

        $submission = $this->makeSubmission($church, ['salary' => '1000']);

        $result = $this->service()->submit($submission, $submitter);

        $this->assertSame('submitted', $result->status);
        $this->assertSame('pending', $result->approval_status);
        $this->assertNotNull($result->latestCalculation, 'submit() must calculate before submitting.');
        $this->assertSame('pending', $result->approval->status);
    }

    public function test_cannot_submit_something_that_is_not_a_draft(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $submitter = User::factory()->create(['church_id' => $church->id]);

        $submission = $this->makeSubmission($church);
        $this->service()->submit($submission, $submitter);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service()->submit($submission->fresh(), $submitter);
    }

    public function test_approving_a_submission_with_an_active_loan_creates_exactly_one_loan_payment(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $submitter = User::factory()->create(['church_id' => $church->id]);
        $approver = User::factory()->create(['church_id' => $church->id]);

        $loan = Loan::create([
            'church_id' => $church->id,
            'principal_amount' => '5000.00',
            'monthly_deduction' => '200.00',
            'status' => 'active',
        ]);

        // shortfall will be 1000 (salary passthrough, no retention) — bigger than the 200 monthly deduction
        $submission = $this->makeSubmission($church, ['salary' => '1000'], $loan);

        $service = $this->service();
        $service->submit($submission, $submitter);
        $service->approve($submission->fresh(), $approver);

        $submission = $submission->fresh();
        $this->assertSame('approved', $submission->status);

        $loan = $loan->fresh();
        $this->assertCount(1, $loan->payments);
        $this->assertSame('200.00', (string) $loan->payments->first()->amount);

        $calculation = $submission->latestCalculation;
        $this->assertNotNull($calculation->loan_payment_id, 'The calculation must record which LoanPayment it produced.');

        // Remittance = shortfall(1000) - loan_deduction_applied(200) = 800
        $this->assertSame('800.00', $calculation->remittance_amount);
    }

    public function test_approving_twice_is_rejected_and_never_creates_a_second_loan_payment(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $submitter = User::factory()->create(['church_id' => $church->id]);
        $approver = User::factory()->create(['church_id' => $church->id]);

        $loan = Loan::create([
            'church_id' => $church->id, 'principal_amount' => '5000.00', 'monthly_deduction' => '200.00', 'status' => 'active',
        ]);
        $submission = $this->makeSubmission($church, ['salary' => '1000'], $loan);

        $service = $this->service();
        $service->submit($submission, $submitter);
        $service->approve($submission->fresh(), $approver);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->approve($submission->fresh(), $approver);
    }

    public function test_returning_a_submission_closes_its_approval_but_allows_resubmission_with_a_fresh_approval(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $submitter = User::factory()->create(['church_id' => $church->id]);
        $reviewer = User::factory()->create(['church_id' => $church->id]);

        $submission = $this->makeSubmission($church, ['salary' => '500']);

        $service = $this->service();
        $service->submit($submission, $submitter);
        $firstApprovalId = $submission->fresh()->approval->id;

        $service->returnForRevision($submission->fresh(), $reviewer, 'Please recheck the salary figure.');
        $submission = $submission->fresh();

        $this->assertSame('returned', $submission->status);
        $this->assertSame('rejected', \App\Models\Approval::find($firstApprovalId)->status);

        $service->reopen($submission);
        $this->assertSame('draft', $submission->fresh()->status);

        $service->submit($submission->fresh(), $submitter);
        $submission = $submission->fresh();

        $this->assertSame('submitted', $submission->status);
        $this->assertNotSame($firstApprovalId, $submission->approval->id, 'Resubmission must create a brand-new Approval.');
        $this->assertCount(2, \App\Models\Approval::withoutGlobalScopes()->where('approvable_id', $submission->id)->get());
    }

    public function test_rejecting_a_submission_is_final_and_distinct_from_returning(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $submitter = User::factory()->create(['church_id' => $church->id]);
        $reviewer = User::factory()->create(['church_id' => $church->id]);

        $submission = $this->makeSubmission($church, ['salary' => '500']);
        $this->service()->submit($submission, $submitter);
        $this->service()->reject($submission->fresh(), $reviewer, 'Branch is not in good standing.');

        $this->assertSame('rejected', $submission->fresh()->status);
    }

    public function test_a_church_cannot_see_another_churchs_subvention_submissions_or_calculations(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();

        $submissionA = $this->makeSubmission($churchA, ['salary' => '100']);
        $submissionB = $this->makeSubmission($churchB, ['salary' => '200']);

        app()->instance('tenant.church_id', $churchA->id);
        (new SubventionCalculationEngine())->calculate($submissionA);

        app()->instance('tenant.church_id', $churchB->id);
        (new SubventionCalculationEngine())->calculate($submissionB);

        app()->instance('tenant.church_id', $churchA->id);
        $this->assertCount(1, SubventionSubmission::all());
        $this->assertCount(1, \App\Models\SubventionCalculation::all());
        $this->assertSame($submissionA->id, SubventionSubmission::first()->id);
    }
}

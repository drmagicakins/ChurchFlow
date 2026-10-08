<?php

namespace Tests\Feature;

use App\Domains\Finance\Services\BudgetService;
use App\Domains\Loans\Services\LoanService;
use App\Models\Budget;
use App\Models\Church;
use App\Models\FinancialAccount;
use App\Models\Loan;
use App\Models\OrganizationalUnit;
use App\Models\Transaction;
use App\Models\UnitType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanAndBudgetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_outstanding_balance_decreases_as_payments_are_recorded(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $loan = Loan::create([
            'church_id' => $church->id,
            'member_id' => \App\Models\Member::factory()->for($church, 'church')->create()->id,
            'principal_amount' => '1000.00',
            'monthly_deduction' => '100.00',
        ]);

        $service = new LoanService();
        $this->assertSame('1000.00', $service->outstandingBalance($loan));

        $service->recordPayment($loan, '250.00', now()->toDateString());
        $this->assertSame('750.00', $service->outstandingBalance($loan->fresh()));
        $this->assertSame(2, $service->monthsPaid($loan->fresh())); // floor(250/100) = 2
    }

    public function test_loan_closes_automatically_once_fully_paid_and_never_reports_a_negative_balance(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $loan = Loan::create([
            'church_id' => $church->id,
            'member_id' => \App\Models\Member::factory()->for($church, 'church')->create()->id,
            'principal_amount' => '500.00',
            'monthly_deduction' => '500.00',
        ]);

        $service = new LoanService();
        $service->recordPayment($loan, '600.00', now()->toDateString()); // overpayment

        $this->assertSame('closed', $loan->fresh()->status);
        $this->assertSame('0.00', $service->outstandingBalance($loan->fresh()));
    }

    public function test_cannot_record_a_payment_against_a_closed_loan(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $loan = Loan::create([
            'church_id' => $church->id,
            'member_id' => \App\Models\Member::factory()->for($church, 'church')->create()->id,
            'principal_amount' => '100.00',
            'monthly_deduction' => '100.00',
            'status' => 'closed',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new LoanService())->recordPayment($loan, '50.00', now()->toDateString());
    }

    public function test_a_loan_can_belong_to_a_branch_instead_of_a_member(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $unitType = UnitType::create(['church_id' => $church->id, 'name' => 'Province', 'level' => 0]);
        $province = OrganizationalUnit::create([
            'church_id' => $church->id, 'unit_type_id' => $unitType->id, 'name' => 'Abia Province 01',
        ]);

        $loan = Loan::create([
            'church_id' => $church->id,
            'organizational_unit_id' => $province->id,
            'principal_amount' => '2000.00',
            'monthly_deduction' => '200.00',
        ]);

        $this->assertSame('Abia Province 01', $loan->borrowerLabel());
    }

    public function test_a_church_cannot_see_another_churchs_loans_or_payments(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();

        $loanA = Loan::create([
            'church_id' => $churchA->id,
            'member_id' => \App\Models\Member::factory()->for($churchA, 'church')->create()->id,
            'principal_amount' => '100.00', 'monthly_deduction' => '10.00',
        ]);
        $loanB = Loan::create([
            'church_id' => $churchB->id,
            'member_id' => \App\Models\Member::factory()->for($churchB, 'church')->create()->id,
            'principal_amount' => '100.00', 'monthly_deduction' => '10.00',
        ]);
        (new LoanService())->recordPayment($loanB, '10.00', now()->toDateString());

        app()->instance('tenant.church_id', $churchA->id);
        $this->assertCount(1, Loan::all());
        $this->assertSame($loanA->id, Loan::first()->id);
        $this->assertCount(0, \App\Models\LoanPayment::all());
    }

    public function test_budget_variance_report_compares_planned_to_approved_actual_spend(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $account = FinancialAccount::factory()->for($church, 'church')->create();

        $budget = Budget::create([
            'church_id' => $church->id,
            'financial_account_id' => $account->id,
            'name' => 'Q1 Budget',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
        ]);
        $budget->items()->create(['category' => 'Rent', 'planned_amount' => '1000.00']);

        // Approved expense within period: counts.
        Transaction::create([
            'church_id' => $church->id, 'financial_account_id' => $account->id,
            'type' => 'expense', 'category' => 'Rent', 'amount' => '600.00',
            'transacted_on' => now(), 'approval_status' => 'approved',
        ]);
        // Pending expense: must NOT count yet.
        Transaction::create([
            'church_id' => $church->id, 'financial_account_id' => $account->id,
            'type' => 'expense', 'category' => 'Rent', 'amount' => '300.00',
            'transacted_on' => now(), 'approval_status' => 'pending',
        ]);
        // Voided expense: must NOT count.
        Transaction::create([
            'church_id' => $church->id, 'financial_account_id' => $account->id,
            'type' => 'expense', 'category' => 'Rent', 'amount' => '9999.00',
            'transacted_on' => now(), 'approval_status' => 'approved', 'is_void' => true,
        ]);

        $report = (new BudgetService())->varianceReport($budget->fresh()->load('items'));

        $this->assertSame('600.00', $report->first()['actual']);
        $this->assertSame('400.00', $report->first()['variance']);
    }
}

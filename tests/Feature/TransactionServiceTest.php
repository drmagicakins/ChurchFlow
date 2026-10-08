<?php

namespace Tests\Feature;

use App\Domains\Approvals\Services\ApprovalWorkflow;
use App\Domains\Finance\Services\TransactionService;
use App\Models\Church;
use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TransactionService
    {
        return new TransactionService(new ApprovalWorkflow());
    }

    public function test_income_posts_immediately_and_increases_balance(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = User::factory()->create(['church_id' => $church->id]);
        $account = FinancialAccount::factory()->for($church, 'church')->create();

        $this->service()->recordIncome($account, [
            'amount' => '500.00', 'transacted_on' => now()->toDateString(), 'category' => 'Tithe',
        ], $user);

        $this->assertSame('500.00', $account->fresh()->balance());
    }

    public function test_expense_does_not_affect_balance_until_approved(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = User::factory()->create(['church_id' => $church->id]);
        $account = FinancialAccount::factory()->for($church, 'church')->create();

        $this->service()->recordIncome($account, [
            'amount' => '1000.00', 'transacted_on' => now()->toDateString(),
        ], $user);

        $expense = $this->service()->recordExpense($account, [
            'amount' => '300.00', 'transacted_on' => now()->toDateString(), 'category' => 'Rent',
        ], $user);

        $this->assertSame('pending', $expense->approval_status);
        $this->assertSame('1000.00', $account->fresh()->balance(), 'Pending expense must not reduce the balance yet.');

        $approval = $expense->approval;
        $this->assertNotNull($approval, 'Recording an expense must create a linked Approval row.');

        (new ApprovalWorkflow())->approve($approval, $user);

        $this->assertSame('700.00', $account->fresh()->balance(), 'Balance should drop only once the expense is approved.');
    }

    public function test_rejected_expense_never_affects_balance(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = User::factory()->create(['church_id' => $church->id]);
        $account = FinancialAccount::factory()->for($church, 'church')->create();

        $this->service()->recordIncome($account, ['amount' => '1000.00', 'transacted_on' => now()->toDateString()], $user);
        $expense = $this->service()->recordExpense($account, ['amount' => '300.00', 'transacted_on' => now()->toDateString()], $user);

        (new ApprovalWorkflow())->reject($expense->approval, $user, 'Not budgeted.');

        $this->assertSame('1000.00', $account->fresh()->balance());
    }

    public function test_transfer_moves_the_same_amount_between_two_accounts_atomically(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = User::factory()->create(['church_id' => $church->id]);

        $general = FinancialAccount::factory()->for($church, 'church')->create(['name' => 'General']);
        $building = FinancialAccount::factory()->for($church, 'church')->create(['name' => 'Building']);

        $this->service()->recordIncome($general, ['amount' => '1000.00', 'transacted_on' => now()->toDateString()], $user);

        [$out, $in] = $this->service()->recordTransfer($general, $building, '400.00', now()->toDateString(), $user);

        $this->assertSame($out->transfer_group_id, $in->transfer_group_id);
        $this->assertSame('600.00', $general->fresh()->balance());
        $this->assertSame('400.00', $building->fresh()->balance());
    }

    public function test_transfer_between_two_different_churches_accounts_is_rejected(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $churchA->id]);

        $accountA = FinancialAccount::factory()->for($churchA, 'church')->create();
        $accountB = FinancialAccount::factory()->for($churchB, 'church')->create();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service()->recordTransfer($accountA, $accountB, '100.00', now()->toDateString(), $user);
    }

    public function test_voiding_a_transaction_removes_it_from_the_balance_but_keeps_the_row(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = User::factory()->create(['church_id' => $church->id]);
        $account = FinancialAccount::factory()->for($church, 'church')->create();

        $income = $this->service()->recordIncome($account, ['amount' => '500.00', 'transacted_on' => now()->toDateString()], $user);
        $this->assertSame('500.00', $account->fresh()->balance());

        $this->service()->voidTransaction($income, 'Entered twice by mistake', $user);

        $this->assertSame('0.00', $account->fresh()->balance());
        $this->assertNotNull(\App\Models\Transaction::find($income->id), 'Voiding must never delete the row.');
        $this->assertTrue($income->fresh()->is_void);
        $this->assertSame('Entered twice by mistake', $income->fresh()->void_reason);
    }

    public function test_voiding_requires_a_non_empty_reason(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = User::factory()->create(['church_id' => $church->id]);
        $account = FinancialAccount::factory()->for($church, 'church')->create();

        $income = $this->service()->recordIncome($account, ['amount' => '500.00', 'transacted_on' => now()->toDateString()], $user);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service()->voidTransaction($income, '   ', $user);
    }

    public function test_a_church_cannot_see_another_churchs_accounts_or_transactions(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        $userA = User::factory()->create(['church_id' => $churchA->id]);

        $accountA = FinancialAccount::factory()->for($churchA, 'church')->create();
        $accountB = FinancialAccount::factory()->for($churchB, 'church')->create();

        app()->instance('tenant.church_id', $churchA->id);
        $this->service()->recordIncome($accountA, ['amount' => '100.00', 'transacted_on' => now()->toDateString()], $userA);

        app()->instance('tenant.church_id', $churchB->id);
        $this->service()->recordIncome($accountB, ['amount' => '999.00', 'transacted_on' => now()->toDateString()], User::factory()->create(['church_id' => $churchB->id]));

        app()->instance('tenant.church_id', $churchA->id);
        $this->assertCount(1, FinancialAccount::all());
        $this->assertCount(1, \App\Models\Transaction::all());
        $this->assertSame('100.00', \App\Models\Transaction::first()->amount);
    }
}

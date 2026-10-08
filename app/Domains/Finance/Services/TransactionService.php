<?php

namespace App\Domains\Finance\Services;

use App\Domains\Approvals\Services\ApprovalWorkflow;
use App\Models\FinancialAccount;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionService
{
    public function __construct(private readonly ApprovalWorkflow $approvals) {}

    /**
     * Income and donations post immediately — the risk profile of "money
     * came in" is different from "money is about to go out", and requiring
     * approval on every tithe entry would make the system fight the
     * person using it for no safety benefit. See §15/§48.
     */
    public function recordIncome(FinancialAccount $account, array $data, User $recordedBy): Transaction
    {
        return Transaction::create([
            'church_id' => $account->church_id,
            'financial_account_id' => $account->id,
            'type' => ($data['member_id'] ?? null) ? 'donation' : 'income',
            'category' => $data['category'] ?? null,
            'amount' => $data['amount'],
            'description' => $data['description'] ?? null,
            'transacted_on' => $data['transacted_on'],
            'member_id' => $data['member_id'] ?? null,
            'recorded_by' => $recordedBy->id,
            'approval_status' => 'not_required',
        ]);
    }

    /**
     * Expenses are NOT immediately spendable-looking: they are created with
     * approval_status = pending and submitted into the shared Approval
     * workflow in the same transaction, so an expense can never exist
     * without a corresponding Approval row to decide it (§15 approval
     * workflows, §48 auditable financial operations).
     */
    public function recordExpense(FinancialAccount $account, array $data, User $recordedBy): Transaction
    {
        return DB::transaction(function () use ($account, $data, $recordedBy) {
            $transaction = Transaction::create([
                'church_id' => $account->church_id,
                'financial_account_id' => $account->id,
                'type' => 'expense',
                'category' => $data['category'] ?? null,
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'transacted_on' => $data['transacted_on'],
                'recorded_by' => $recordedBy->id,
                'approval_status' => 'pending',
            ]);

            $this->approvals->submit($transaction, $recordedBy);

            return $transaction;
        });
    }

    /**
     * A transfer is two linked rows, never one row that touches two
     * balances — see the comment on the transactions migration. Both legs
     * share a transfer_group_id and are created (or fail) together.
     */
    public function recordTransfer(
        FinancialAccount $from,
        FinancialAccount $to,
        string $amount,
        string $transactedOn,
        User $recordedBy,
        ?string $description = null,
    ): array {
        abort_if($from->church_id !== $to->church_id, 403, 'Cannot transfer between two different churches.');
        abort_if($from->id === $to->id, 422, 'Source and destination accounts must differ.');

        return DB::transaction(function () use ($from, $to, $amount, $transactedOn, $recordedBy, $description) {
            $groupId = (string) Str::uuid();

            $out = Transaction::create([
                'church_id' => $from->church_id,
                'financial_account_id' => $from->id,
                'type' => 'transfer_out',
                'category' => 'Transfer',
                'amount' => $amount,
                'description' => $description,
                'transacted_on' => $transactedOn,
                'transfer_group_id' => $groupId,
                'recorded_by' => $recordedBy->id,
                'approval_status' => 'not_required',
            ]);

            $in = Transaction::create([
                'church_id' => $to->church_id,
                'financial_account_id' => $to->id,
                'type' => 'transfer_in',
                'category' => 'Transfer',
                'amount' => $amount,
                'description' => $description,
                'transacted_on' => $transactedOn,
                'transfer_group_id' => $groupId,
                'recorded_by' => $recordedBy->id,
                'approval_status' => 'not_required',
            ]);

            return [$out, $in];
        });
    }

    /**
     * §48: financial records are never silently overwritten or deleted.
     * Voiding requires a reason, is itself audited (Transaction uses the
     * Auditable trait, so this update is logged), and the original row
     * stays in place forever — balance queries simply exclude it.
     */
    public function voidTransaction(Transaction $transaction, string $reason, User $voidedBy): Transaction
    {
        abort_if($transaction->is_void, 409, 'This transaction has already been voided.');
        abort_if(trim($reason) === '', 422, 'A reason is required to void a transaction.');

        $transaction->update([
            'is_void' => true,
            'voided_at' => now(),
            'void_reason' => $reason,
            'voided_by' => $voidedBy->id,
        ]);

        return $transaction;
    }
}

<?php

namespace App\Domains\Loans\Services;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\User;

/**
 * The legacy calculate-subvention.php mutated loans_svp.total_repay
 * IN PLACE as a side effect of an unrelated report page, with no
 * payment-history table at all — running the report twice double-counted
 * the deduction. Every method here instead derives the loan's state from
 * the immutable loan_payments ledger, so it can be recalculated safely any
 * number of times.
 */
class LoanService
{
    public function recordPayment(Loan $loan, string $amount, string $paidOn, ?User $recordedBy = null): LoanPayment
    {
        abort_if($loan->status !== 'active', 409, 'Cannot record a payment against a loan that is not active.');

        $payment = $loan->payments()->create([
            'amount' => $amount,
            'paid_on' => $paidOn,
            'recorded_by' => $recordedBy?->id,
        ]);

        if (bccomp($this->outstandingBalance($loan->fresh()), '0', 2) <= 0) {
            $loan->update(['status' => 'closed']);
        }

        return $payment;
    }

    public function totalPaid(Loan $loan): string
    {
        return (string) $loan->payments()->sum('amount');
    }

    public function outstandingBalance(Loan $loan): string
    {
        $balance = bcsub((string) $loan->principal_amount, $this->totalPaid($loan), 2);

        // Never report a negative outstanding balance just because the
        // last payment overshot what was left — clamp at zero rather than
        // let a report imply the church owes the borrower money.
        return bccomp($balance, '0', 2) < 0 ? '0.00' : $balance;
    }

    /**
     * Whole months' worth of scheduled deduction paid off so far. Uses the
     * configured monthly_deduction, not a raw count of payment rows, since
     * a church might record one lump-sum payment covering several months
     * or several partial payments within one month.
     */
    public function monthsPaid(Loan $loan): int
    {
        if (bccomp((string) $loan->monthly_deduction, '0', 2) <= 0) {
            return 0;
        }

        return (int) bcdiv($this->totalPaid($loan), (string) $loan->monthly_deduction, 0);
    }
}

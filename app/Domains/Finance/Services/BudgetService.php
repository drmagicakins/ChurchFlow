<?php

namespace App\Domains\Finance\Services;

use App\Models\Budget;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class BudgetService
{
    /**
     * §15/§24 "Budget vs actual". Actuals are approved, non-void expense
     * transactions within the budget's period, matched by category string
     * and (if the budget is scoped to one) financial account. Category
     * matching is a plain string equality for now — Phase 5+ may want a
     * proper categories table shared between transactions and budget
     * items; noted here rather than solved prematurely.
     */
    public function actualFor(Budget $budget, string $category): string
    {
        $sum = Transaction::query()
            ->approved()
            ->notVoid()
            ->where('type', 'expense')
            ->where('category', $category)
            ->when($budget->financial_account_id, fn ($q) => $q->where('financial_account_id', $budget->financial_account_id))
            ->whereBetween('transacted_on', [$budget->period_start, $budget->period_end])
            ->sum('amount');

        // Money is compared and displayed as a fixed-scale decimal string
        // everywhere else in the finance domain (TransactionService, the
        // loan ledger), so the report must not hand back the float 600.0
        // that a bare SQL SUM produces — "600" and "600.00" are different
        // strings even though they are the same number.
        return number_format((float) $sum, 2, '.', '');
    }

    /** @return Collection<int, array{category:string, planned:string, actual:string, variance:string}> */
    public function varianceReport(Budget $budget): Collection
    {
        return $budget->items->map(function ($item) use ($budget) {
            $actual = $this->actualFor($budget, $item->category);

            return [
                'category' => $item->category,
                'planned' => (string) $item->planned_amount,
                'actual' => $actual,
                'variance' => bcsub((string) $item->planned_amount, $actual, 2),
            ];
        });
    }
}

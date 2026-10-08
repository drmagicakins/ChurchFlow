<?php

namespace App\Http\Controllers;

use App\Domains\Finance\Services\TransactionService;
use App\Models\FinancialAccount;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(private readonly TransactionService $transactions) {}

    public function storeIncome(Request $request, FinancialAccount $account): RedirectResponse
    {
        $this->authorize('create', Transaction::class);
        $this->authorize('view', $account);

        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:2000'],
            'transacted_on' => ['required', 'date'],
            'member_id' => ['nullable', 'exists:members,id'],
        ]);

        $transaction = $this->transactions->recordIncome($account, $data, $request->user());

        return redirect()->route('finance.accounts.show', $account)
            ->with('status', 'Recorded '.$transaction->type.' of '.$transaction->amount.'.');
    }

    public function storeExpense(Request $request, FinancialAccount $account): RedirectResponse
    {
        $this->authorize('create', Transaction::class);
        $this->authorize('view', $account);

        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:2000'],
            'transacted_on' => ['required', 'date'],
        ]);

        $this->transactions->recordExpense($account, $data, $request->user());

        return redirect()->route('finance.accounts.show', $account)
            ->with('status', 'Expense recorded and submitted for approval.');
    }

    public function storeTransfer(Request $request): RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        $data = $request->validate([
            'from_account_id' => ['required', 'exists:financial_accounts,id', 'different:to_account_id'],
            'to_account_id' => ['required', 'exists:financial_accounts,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transacted_on' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $from = FinancialAccount::findOrFail($data['from_account_id']);
        $to = FinancialAccount::findOrFail($data['to_account_id']);

        $this->transactions->recordTransfer(
            $from, $to, (string) $data['amount'], $data['transacted_on'], $request->user(), $data['description'] ?? null,
        );

        return redirect()->route('finance.accounts.show', $from)->with('status', 'Transfer completed.');
    }

    public function void(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        $this->transactions->voidTransaction($transaction, $data['reason'], $request->user());

        return back()->with('status', 'Transaction voided.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Domains\Finance\Services\TransactionService;
use App\Models\FinancialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialAccountController extends Controller
{
    public function __construct(private readonly TransactionService $transactions) {}
    public function index(): View
    {
        $this->authorize('viewAny', FinancialAccount::class);
        $accounts = FinancialAccount::query()->where('is_active', true)->get();

        return view('finance.accounts.index', compact('accounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', FinancialAccount::class);

        // The form offers bank/cash/mobile_money/other and an optional opening
        // balance; the validator previously required general/building/missions/
        // welfare and never accepted opening_balance, so every submit failed
        // with a 422 on a field the user had just picked from the dropdown.
        // The accepted set now matches the form exactly, and opening_balance is
        // recorded as the account's first income transaction rather than a
        // hidden adjustment — keeping "balance = sum of transactions" true.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:bank,cash,mobile_money,general,building,missions,welfare,other'],
            'opening_balance' => ['nullable', 'numeric', 'gte:0'],
            'description' => ['nullable', 'string', 'max:2000'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:32'],
        ]);

        $account = FinancialAccount::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'bank_name' => $data['bank_name'] ?? null,
            'bank_account_name' => $data['bank_account_name'] ?? null,
            'bank_account_number' => $data['bank_account_number'] ?? null,
        ]);

        if (! empty($data['opening_balance']) && (float) $data['opening_balance'] > 0) {
            $this->transactions->recordIncome($account, [
                'category' => 'opening_balance',
                'amount' => $data['opening_balance'],
                'description' => $data['description'] ?? 'Opening balance',
                'transacted_on' => now()->toDateString(),
            ], $request->user());
        }

        return redirect()->route('finance.accounts.index')->with('status', 'Account created.');
    }

    public function show(FinancialAccount $account): View
    {
        $this->authorize('view', $account);

        return view('finance.accounts.show', [
            'account' => $account,
            'balance' => $account->balance(),
            'transactions' => $account->transactions()->notVoid()->latest('transacted_on')->paginate(25),
        ]);
    }
}

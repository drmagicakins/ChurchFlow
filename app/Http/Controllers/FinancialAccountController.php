<?php

namespace App\Http\Controllers;

use App\Models\FinancialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialAccountController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', FinancialAccount::class);
        $accounts = FinancialAccount::query()->where('is_active', true)->get();

        return view('finance.accounts.index', compact('accounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', FinancialAccount::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:general,building,missions,welfare,other'],
        ]);

        FinancialAccount::create($data);

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

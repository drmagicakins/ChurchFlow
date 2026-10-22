<?php

namespace App\Http\Controllers;

use App\Domains\Finance\Services\BudgetService;
use App\Models\Budget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Budget::class);
        $budgets = Budget::query()->latest('period_start')->paginate(20);

        return view('finance.budgets.index', compact('budgets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Budget::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'financial_account_id' => ['nullable', 'exists:financial_accounts,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
            'items' => ['nullable', 'array'],
            'items.*.category' => ['required_with:items', 'string', 'max:255'],
            'items.*.planned_amount' => ['required_with:items', 'numeric', 'gt:0'],
        ]);

        $budget = Budget::create([
            'name' => $data['name'],
            'financial_account_id' => $data['financial_account_id'] ?? null,
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
        ]);

        foreach ($data['items'] ?? [] as $item) {
            $budget->items()->create($item);
        }

        return redirect()->route('finance.budgets.show', $budget);
    }

    public function show(Budget $budget, BudgetService $budgetService): View
    {
        $this->authorize('view', $budget);

        return view('finance.budgets.show', [
            'budget' => $budget,
            'variance' => $budgetService->varianceReport($budget->load('items')),
        ]);
    }

    /** Add one planned line to an existing budget. */
    public function storeItem(Request $request, Budget $budget): RedirectResponse
    {
        $this->authorize('update', $budget);

        $data = $request->validate([
            'category' => [
                'required', 'string', 'max:255',
                // The budget_items unique index is (budget_id, category);
                // without this the second add of the same category would be an
                // unhandled 500 rather than a field error.
                \Illuminate\Validation\Rule::unique('budget_items', 'category')->where('budget_id', $budget->id),
            ],
            'planned_amount' => ['required', 'numeric', 'gt:0'],
        ], [
            'category.unique' => 'This budget already has a line for that category.',
        ]);

        $budget->items()->create($data);

        return back()->with('status', 'Planned line added.');
    }

    /** Remove a planned line from a budget. */
    public function destroyItem(Budget $budget, \App\Models\BudgetItem $item): RedirectResponse
    {
        $this->authorize('update', $budget);

        abort_unless($item->budget_id === $budget->id, 404);

        $item->delete();

        return back()->with('status', 'Planned line removed.');
    }
}

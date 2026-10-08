<?php

namespace App\Http\Controllers;

use App\Domains\Loans\Services\LoanService;
use App\Models\Loan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanController extends Controller
{
    public function __construct(private readonly LoanService $loans) {}

    public function index(): View
    {
        $this->authorize('viewAny', Loan::class);
        $loans = Loan::query()->latest()->paginate(25);

        return view('finance.loans.index', ['loans' => $loans, 'loanService' => $this->loans]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Loan::class);

        $data = $request->validate([
            'organizational_unit_id' => ['nullable', 'exists:organizational_units,id', 'required_without:member_id'],
            'member_id' => ['nullable', 'exists:members,id', 'required_without:organizational_unit_id'],
            'principal_amount' => ['required', 'numeric', 'gt:0'],
            'monthly_deduction' => ['required', 'numeric', 'gt:0'],
            'interest_rate' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_if(
            !empty($data['organizational_unit_id']) && !empty($data['member_id']),
            422,
            'A loan must belong to exactly one borrower: a branch or a member, not both.'
        );

        $loan = Loan::create($data);

        return redirect()->route('finance.loans.show', $loan);
    }

    public function show(Loan $loan): View
    {
        $this->authorize('view', $loan);

        return view('finance.loans.show', [
            'loan' => $loan,
            'outstanding' => $this->loans->outstandingBalance($loan),
            'monthsPaid' => $this->loans->monthsPaid($loan),
        ]);
    }

    public function recordPayment(Request $request, Loan $loan): RedirectResponse
    {
        $this->authorize('update', $loan);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_on' => ['required', 'date'],
        ]);

        $this->loans->recordPayment($loan, (string) $data['amount'], $data['paid_on'], $request->user());

        return back()->with('status', 'Payment recorded.');
    }
}

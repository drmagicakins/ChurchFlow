<?php

namespace App\Http\Controllers;

use App\Domains\Loans\Services\LoanService;
use App\Models\Loan;
use App\Models\Member;
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

    /**
     * Record a new loan (finance manager) or REQUEST one on your own behalf
     * (a pastor).
     *
     * A branch pastor normally carries only subvention.submit, so
     * LoanPolicy::create refuses them — which is what left the Loans page with
     * no way for a pastor to ask for a loan at all. Submitting the request form
     * is therefore allowed for any signed-in member of the church, and the two
     * paths are kept apart deliberately:
     *
     *   - a manager records a loan against any branch or member, exactly as
     *     before (unchanged behaviour);
     *   - a requester may only put the loan against THEIR OWN branch (or
     *     themselves), and the loan is created 'pending' so the finance desk
     *     knows it still has to be approved before money is lent.
     *
     * Borrower identity is resolved on the server, never taken from the form
     * for the requester path, so a pastor cannot request a loan against another
     * parish simply by editing the hidden field.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user(), 401);

        $canManage = $request->user()->can('create', Loan::class);

        $data = $request->validate([
            'organizational_unit_id' => ['nullable', 'integer', 'exists:organizational_units,id'],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'principal_amount' => ['required', 'numeric', 'gt:0'],
            'monthly_deduction' => ['required', 'numeric', 'gt:0'],
            'interest_rate' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'organizational_unit_id.exists' => 'Choose a branch that belongs to your church.',
            'member_id.exists' => 'Choose a member that belongs to your church.',
        ]);

        $user = $request->user();

        if ($canManage) {
            abort_if(
                empty($data['organizational_unit_id']) && empty($data['member_id']),
                422,
                'A loan must belong to a borrower: a branch or a member.'
            );

            abort_if(
                ! empty($data['organizational_unit_id']) && ! empty($data['member_id']),
                422,
                'A loan must belong to exactly one borrower: a branch or a member, not both.'
            );
        } else {
            // The requester is the borrower. Prefer their branch — that is what
            // "the pastor of a parish asks the headquarters for a loan" means —
            // and fall back to their own member record only when they have no
            // branch. Never trust the submitted ids on this path.
            $data['organizational_unit_id'] = $user->organizational_unit_id;
            $data['member_id'] = $user->organizational_unit_id
                ? null
                : Member::query()->where('email', $user->email)->value('id');

            abort_if(
                empty($data['organizational_unit_id']) && empty($data['member_id']),
                403,
                'Your account is not attached to a branch or a member record, so a loan cannot be requested for you yet. Ask a church administrator to link your account to a branch.'
            );
        }

        $loan = Loan::create([
            ...$data,
            'status' => $canManage ? 'active' : 'pending',
        ]);

        return redirect()->route('finance.loans.show', $loan)->with('status', $canManage
            ? 'Loan created.'
            : 'Your loan request has been submitted. Your church\'s finance team will review it.');
    }

    public function show(Loan $loan): View
    {
        $this->authorize('view', $loan);

        // A loan with neither a branch nor a member cannot name its borrower —
        // historically the store path could be reached with both fields blank
        // and produced exactly that row, which then rendered as "Unknown
        // borrower" and, downstream, as "No routing parameters". Refuse to draw
        // such a row rather than showing a half-rendered page, and say why.
        abort_if(
            $loan->organizational_unit_id === null && $loan->member_id === null,
            409,
            'This loan has no borrower recorded (no branch and no member). Ask a finance administrator to delete it and record it again with a borrower selected.'
        );

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

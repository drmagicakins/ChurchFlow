<?php

namespace App\Http\Controllers;

use App\Domains\Approvals\Services\ApprovalWorkflow;
use App\Models\Approval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalWorkflow $workflow) {}

    public function index(): View
    {
        $this->authorize('viewAny', Approval::class);

        $approvals = Approval::query()->where('status', 'pending')->with('approvable')->paginate(25);

        return view('approvals.index', compact('approvals'));
    }

    public function approve(Request $request, Approval $approval): RedirectResponse
    {
        $this->authorize('update', $approval);

        $this->workflow->approve($approval, $request->user(), $request->string('comments')->toString() ?: null);

        return back()->with('status', 'Approved.');
    }

    public function reject(Request $request, Approval $approval): RedirectResponse
    {
        $this->authorize('update', $approval);

        $data = $request->validate(['comments' => ['required', 'string', 'max:2000']]);

        $this->workflow->reject($approval, $request->user(), $data['comments']);

        return back()->with('status', 'Rejected.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Domains\Subventions\Services\SubventionWorkflowService;
use App\Models\SubventionSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubventionSubmissionController extends Controller
{
    public function __construct(private readonly SubventionWorkflowService $workflow) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SubventionSubmission::class);

        $submissions = SubventionSubmission::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->with(['organizationalUnit', 'period', 'latestCalculation'])
            ->latest()
            ->paginate(25);

        return view('subvention.submissions.index', compact('submissions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', SubventionSubmission::class);

        // The unique index `one_submission_per_unit_per_period` means a branch
        // can only ever have ONE submission per period. That rule is enforced in
        // the database, but it was not enforced in validation, so re-submitting
        // the form (or double-clicking "New submission") escaped as an unhandled
        // UniqueConstraintViolationException — a 500 for what is a completely
        // ordinary user mistake. Checking it here turns the same situation into
        // a field-level validation error the form can display.
        $data = $request->validate([
            'subvention_period_id' => [
                'required',
                'exists:subvention_periods,id',
                \Illuminate\Validation\Rule::unique('subvention_submissions', 'subvention_period_id')
                    ->where('organizational_unit_id', $request->input('organizational_unit_id'))
                    ->whereNull('deleted_at'),
            ],
            'organizational_unit_id' => ['required', 'exists:organizational_units,id'],
            'subvention_rule_set_id' => ['required', 'exists:subvention_rule_sets,id'],
            'figures' => ['required', 'array'],
        ], [
            'subvention_period_id.unique' => 'This branch already has a submission for that period.',
        ]);

        $submission = SubventionSubmission::create($data);

        return redirect()->route('subvention.submissions.show', $submission);
    }

    public function show(SubventionSubmission $submission): View
    {
        $this->authorize('view', $submission);

        return view('subvention.submissions.show', [
            'submission' => $submission->load(['ruleSet.rules', 'calculations', 'approval']),
        ]);
    }

    public function update(Request $request, SubventionSubmission $submission): RedirectResponse
    {
        $this->authorize('update', $submission);

        $data = $request->validate(['figures' => ['required', 'array']]);
        $submission->update($data);

        return back()->with('status', 'Figures updated.');
    }

    public function submit(Request $request, SubventionSubmission $submission): RedirectResponse
    {
        $this->authorize('update', $submission);

        $this->workflow->submit($submission, $request->user());

        return redirect()->route('subvention.submissions.show', $submission)
            ->with('status', 'Submitted for review.');
    }

    public function review(Request $request, SubventionSubmission $submission): RedirectResponse
    {
        $this->authorize('review', $submission);
        $this->workflow->markUnderReview($submission, $request->user());

        return back()->with('status', 'Marked under review.');
    }

    public function return(Request $request, SubventionSubmission $submission): RedirectResponse
    {
        $this->authorize('review', $submission);

        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $this->workflow->returnForRevision($submission, $request->user(), $data['reason']);

        return back()->with('status', 'Returned to the branch for revision.');
    }

    public function reopen(SubventionSubmission $submission): RedirectResponse
    {
        $this->authorize('update', $submission);
        $this->workflow->reopen($submission);

        return back()->with('status', 'Reopened as a draft.');
    }

    public function approve(Request $request, SubventionSubmission $submission): RedirectResponse
    {
        $this->authorize('review', $submission);

        $this->workflow->approve($submission, $request->user(), $request->string('comments')->toString() ?: null);

        return back()->with('status', 'Approved.');
    }

    public function reject(Request $request, SubventionSubmission $submission): RedirectResponse
    {
        $this->authorize('review', $submission);

        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $this->workflow->reject($submission, $request->user(), $data['reason']);

        return back()->with('status', 'Rejected.');
    }
}

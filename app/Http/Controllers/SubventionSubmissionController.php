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

        $user = $request->user();

        // §21/§14 parish scoping + registrar oversight:
        //
        //  - The church owner (registrar) sees EVERY submission from every
        //    parish — they are the one who receives and approves them.
        //  - A pastor attached to a parish sees only their own parish's
        //    submissions.
        //  - A pastor attached to MAIN sees only Main — the main church is a
        //    unit in its own right, not an umbrella that shows it every branch.
        //  - A church-wide user with no parish sees the whole church's, the
        //    same as today.
        $seesWholeChurch = $user->seesWholeChurchSubventions();

        // SEARCH. The filter names are the brief's own words — parish, month,
        // year — and each one is applied ONLY when the person may use it:
        //
        //  - `parish` (a unit id) is honoured for a whole-church viewer and is
        //    ignored for a parish-scoped one, whose own-parish rule below is
        //    applied unconditionally and can never be widened by a query string;
        //  - `month`/`year` read the PERIOD the figures belong to, and match the
        //    month the period COVERS rather than only its first day: a period
        //    running 2026-10-18..2026-10-24 is an October period, a quarter
        //    running 2026-07-01..2026-09-30 is found under July, August AND
        //    September, and a period for "October WK 5" that starts on 2026-11-01
        //    is still listed under October. A draft is found by the month it is
        //    FOR, which is what a person means by "October" — not by when they
        //    happened to save it.
        $filterParish = $seesWholeChurch ? $request->integer('parish') : null;
        $filterMonth = $request->integer('month');
        $filterYear = $request->integer('year');

        $query = SubventionSubmission::query()
            ->when(! $seesWholeChurch && $user->organizational_unit_id !== null, function ($q) use ($user) {
                $q->where('organizational_unit_id', $user->organizational_unit_id);
            })
            ->when($filterParish, fn ($q) => $q->where('organizational_unit_id', $filterParish))
            ->when($filterMonth >= 1 && $filterMonth <= 12, fn ($q) => $q->whereHas(
                'period', fn ($p) => $p->whereMonth('period_start', '<=', $filterMonth)
                    ->whereMonth('period_end', '>=', $filterMonth)
                    ->when($filterYear >= 1900 && $filterYear <= 2200, fn ($p) => $p
                        ->where('period_start', '<=', sprintf('%04d-%02d-31', $filterYear, $filterMonth))
                        ->where('period_end', '>=', sprintf('%04d-%02d-01', $filterYear, $filterMonth)))
            ))
            ->when($filterMonth < 1 && $filterYear >= 1900 && $filterYear <= 2200, fn ($q) => $q->whereHas(
                'period', fn ($p) => $p->where(fn ($w) => $w
                    ->whereYear('period_start', $filterYear)
                    ->orWhereYear('period_end', $filterYear))
            ))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->with(['organizationalUnit', 'period', 'latestCalculation']);

        // A SEARCH result is listed by the month it is FOR — newest period first —
        // because a person filtering to "October" is reading a month, not an
        // entry order. The unfiltered list keeps its original newest-first
        // ordering, unchanged.
        $isSearching = request()->hasAny(['parish', 'month', 'year', 'status']);

        if ($isSearching) {
            $query->orderByDesc(
                \App\Models\SubventionPeriod::query()
                    ->select('period_end')
                    ->whereColumn('subvention_periods.id', 'subvention_submissions.subvention_period_id')
                    ->limit(1)
            )->latest('id');
        } else {
            $query->latest();
        }

        $submissions = $query->paginate(25)->withQueryString();

        return view('subvention.submissions.index', [
            'submissions' => $submissions,
            'isRegistrar' => $user->can('review', new SubventionSubmission),
            'isParishScoped' => ! $seesWholeChurch && $user->organizational_unit_id !== null,
            // The parish picker is only offered to someone who may look beyond
            // their own parish; everyone else searches by month and year.
            'parishFilter' => $seesWholeChurch
                ? \App\Models\OrganizationalUnit::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
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
            // latestCalculation is eager-loaded because the page reads its
            // shortfall direction and loan payment: without it every visit
            // would run those queries a second time after the load.
            'submission' => $submission->load(['ruleSet.rules', 'calculations', 'latestCalculation.loanPayment', 'approval']),
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

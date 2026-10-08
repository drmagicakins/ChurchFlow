<?php

namespace App\Http\Controllers;

use App\Domains\PastoralCare\Services\PastoralCareService;
use App\Models\Member;
use App\Models\PastoralCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PastoralCaseController extends Controller
{
    public function __construct(private readonly PastoralCareService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PastoralCase::class);

        $cases = PastoralCase::query()
            ->visibleTo($request->user())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(25);

        return view('pastoral.cases.index', compact('cases'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PastoralCase::class);

        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'type' => ['required', 'in:counseling,welfare,hospital_visit,new_member_followup,other'],
            'description' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $member = Member::findOrFail($data['member_id']);
        $assignTo = !empty($data['assigned_to']) ? \App\Models\User::find($data['assigned_to']) : null;

        $case = $this->service->openCase($member, $data['type'], $request->user(), $data['description'] ?? null, $assignTo);

        return redirect()->route('pastoral.cases.show', $case);
    }

    public function show(PastoralCase $case): View
    {
        $this->authorize('view', $case);

        return view('pastoral.cases.show', ['case' => $case->load('notes', 'appointments')]);
    }

    public function addNote(Request $request, PastoralCase $case): RedirectResponse
    {
        $this->authorize('update', $case);

        $data = $request->validate(['note' => ['required', 'string', 'max:5000']]);
        $this->service->addNote($case, $request->user(), $data['note']);

        return back()->with('status', 'Note added.');
    }

    public function close(Request $request, PastoralCase $case): RedirectResponse
    {
        $this->authorize('update', $case);

        $data = $request->validate(['closing_note' => ['nullable', 'string', 'max:5000']]);
        $case->close($data['closing_note'] ?? null, $request->user());

        return redirect()->route('pastoral.cases.index')->with('status', 'Case closed.');
    }
}

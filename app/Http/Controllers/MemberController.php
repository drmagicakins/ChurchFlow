<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Member::class);

        $members = Member::query()
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('status'), fn ($q) => $q->where('membership_status', $request->string('status')))
            ->when($request->filled('organizational_unit_id'), fn ($q) => $q->where('organizational_unit_id', $request->integer('organizational_unit_id')))
            ->when($request->filled('department_id'), function ($q) use ($request) {
                $q->whereHas('departments', fn ($d) => $d->where('departments.id', $request->integer('department_id')));
            })
            ->orderBy($request->string('sort', 'full_name')->toString(), $request->string('direction', 'asc')->toString())
            ->paginate(25)
            ->withQueryString();

        return view('members.index', compact('members'));
    }

    public function store(StoreMemberRequest $request): RedirectResponse
    {
        $member = Member::create($request->validated());

        return redirect()->route('members.show', $member)->with('status', 'Member added.');
    }

    public function show(Member $member): View
    {
        $this->authorize('view', $member);

        return view('members.show', compact('member'));
    }

    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    {
        $member->update($request->validated());

        return redirect()->route('members.show', $member)->with('status', 'Member updated.');
    }

    public function destroy(Member $member): RedirectResponse
    {
        $this->authorize('delete', $member);
        $member->delete();

        return redirect()->route('members.index')->with('status', 'Member removed.');
    }

    /**
     * Bulk action (§10 "bulk actions"). Kept to destroy/status-change only
     * for now — anything destructive re-checks the policy per row rather
     * than trusting the request's id list wholesale, since a bulk request
     * is exactly the kind of place a stray cross-tenant ID would do the
     * most damage if the policy check were skipped "for performance".
     */
    public function bulkUpdateStatus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'membership_status' => ['required', 'in:active,inactive,visitor,transferred,deceased'],
        ]);

        $members = Member::whereIn('id', $data['ids'])->get(); // tenant-scoped already

        foreach ($members as $member) {
            $this->authorize('update', $member);
            $member->update(['membership_status' => $data['membership_status']]);
        }

        return back()->with('status', count($members).' member(s) updated.');
    }
}

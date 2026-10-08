<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Group::class);
        $groups = Group::withCount('members')->paginate(25);

        return view('groups.index', ['groups' => $groups]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Group::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $group = Group::create($data);

        return redirect()->route('groups.show', $group);
    }

    public function show(Group $group): View
    {
        $this->authorize('view', $group);

        return view('groups.show', ['group' => $group]);
    }

    public function attachMember(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('update', $group);

        $data = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
        ]);

        $member = Member::findOrFail($data['member_id']);
        abort_unless($member->church_id === $group->church_id, 403);

        $group->members()->syncWithoutDetaching([$member->id]);

        return back()->with('status', ucfirst('group').' updated.');
    }

    public function destroy(Group $group): RedirectResponse
    {
        $this->authorize('delete', $group);
        $group->delete();

        return redirect()->route('groups.index');
    }
}

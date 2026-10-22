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
        ], [
            'name.required' => 'Give the group a name.',
        ]);

        // The database enforces UNIQUE(church_id, name), but a soft-deleted
        // group still occupies that name in MySQL/Postgres. Without this check
        // creating a group with the name of a removed one would reach the
        // unique index and fail as an unhandled 500 — which reads to the user
        // as "adding a group doesn't work" rather than "that name is taken".
        // The row is restored instead of duplicated, keeping its member links.
        $existing = Group::withTrashed()
            ->where('name', $data['name'])
            ->first();

        if ($existing) {
            if (! $existing->trashed()) {
                return back()
                    ->withErrors(['name' => 'You already have a group called "'.$existing->name.'".'])
                    ->withInput();
            }

            $existing->restore();
            $existing->update(['description' => $data['description'] ?? $existing->description]);

            return redirect()->route('groups.show', $existing)
                ->with('status', 'Restored the existing "'.$existing->name.'" group.');
        }

        $group = Group::create($data);

        return redirect()->route('groups.show', $group)
            ->with('status', '"'.$group->name.'" created. Add members to it below.');
    }

    public function show(Group $group): View
    {
        $this->authorize('view', $group);

        // The show page is where a group is actually staffed — it needs the
        // members NOT already attached, so the picker can never suggest a
        // duplicate link. Tenant-scoped by the global scope on Member, so this
        // never reaches another church's people.
        $attachedIds = $group->members()->pluck('members.id')->all();
        $availableMembers = Member::query()
            ->when($attachedIds !== [], fn ($q) => $q->whereNotIn('id', $attachedIds))
            ->orderBy('full_name')
            ->limit(500)
            ->get();

        return view('groups.show', [
            'group' => $group->load('members'),
            'availableMembers' => $availableMembers,
        ]);
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

        return back()->with('status', "{$member->full_name} added to {$group->name}.");
    }

    /** Remove one member from a group without touching their own record. */
    public function detachMember(Group $group, Member $member): RedirectResponse
    {
        $this->authorize('update', $group);
        abort_unless($member->church_id === $group->church_id, 403);

        $group->members()->detach($member->id);

        return back()->with('status', "{$member->full_name} removed from {$group->name}.");
    }

    public function destroy(Group $group): RedirectResponse
    {
        $this->authorize('delete', $group);
        $group->delete();

        return redirect()->route('groups.index')
            ->with('status', '"'.$group->name.'" removed.');
    }
}

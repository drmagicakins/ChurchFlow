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
        // A parish pastor sees their own parish's groups only; a church-wide
        // user (owner / registrar) still sees every group in the church.
        $groups = Group::query()
            ->visibleTo(auth()->user())
            ->withCount('members')
            ->paginate(25);

        return view('groups.index', ['groups' => $groups]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Group::class);

        // A parish pastor's group belongs to their parish. A church-wide user
        // may file the group against a parish by choosing one; leaving it blank
        // keeps it church-wide, which is what every group used to be.
        $request->merge([
            'organizational_unit_id' => Group::mayAssignTo($request->user())
                ?? $request->input('organizational_unit_id'),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'organizational_unit_id' => ['nullable', 'integer', 'exists:organizational_units,id'],
        ], [
            'name.required' => 'Give the group a name.',
        ]);

        // `exists` proves the parish exists, not that it is ours. Looked up
        // without the tenant scope on purpose: another church's id must be
        // REFUSED (403), not silently reported as "not found" (404).
        if (! empty($data['organizational_unit_id'])) {
            $unit = \App\Models\OrganizationalUnit::withoutGlobalScopes()
                ->find($data['organizational_unit_id']);
            abort_unless($unit !== null && $unit->church_id === $request->user()->church_id, 403);
        } else {
            $data['organizational_unit_id'] = Group::mayAssignTo($request->user());
        }

        // The database enforces UNIQUE(church_id, name), but a soft-deleted
        // group still occupies that name in MySQL/Postgres. Without this check
        // creating a group with the name of a removed one would reach the
        // unique index and fail as an unhandled 500 — which reads to the user
        // as "adding a group doesn't work" rather than "that name is taken".
        // The row is restored instead of duplicated, keeping its member links.
        $existing = Group::withTrashed()
            ->where('name', $data['name'])
            ->when(
                Group::mayAssignTo($request->user()) !== null,
                fn ($query) => $query->where('organizational_unit_id', Group::mayAssignTo($request->user())),
            )
            ->first();

        if ($existing) {
            if (! $existing->trashed()) {
                return back()
                    ->withErrors(['name' => 'You already have a group called "'.$existing->name.'".'])
                    ->withInput();
            }

            $existing->restore();
            $existing->update([
                'description' => $data['description'] ?? $existing->description,
                'organizational_unit_id' => $existing->organizational_unit_id ?? $data['organizational_unit_id'],
            ]);

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

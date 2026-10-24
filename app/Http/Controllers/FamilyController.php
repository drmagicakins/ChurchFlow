<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FamilyController extends Controller
{
    /**
     * Families, plus the church's member directory.
     *
     * A family can legitimately be empty — people are attached to a household
     * later, sometimes much later — while the members themselves are very much
     * part of the church. That made "Members / All families member" read zero
     * even though the members table was full, and it hid every uploaded member
     * from the Families screen entirely. So the page now carries the complete
     * member list (tenant-scoped by the global scope on Member, so never another
     * church's people) and paginates it UNDER the family filter, rather than
     * counting only people already linked to a household.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Family::class);
        $this->authorize('viewAny', Member::class);

        $families = Family::with('members')
            ->withCount('members')
            ->paginate(25);

        // "all" is the default and deliberately so: the question people come to
        // this screen with is "who do we have?", not "which households exist?".
        $familyId = $request->filled('family') ? $request->integer('family') : null;

        $members = Member::query()
            ->with(['families', 'organizationalUnit'])
            ->withCount('departments')
            ->when($familyId !== null, fn ($q) => $q->whereHas('families', fn ($f) => $f->where('families.id', $familyId)))
            ->when($request->filled('q'), fn ($q) => $q->search($request->string('q')->toString()))
            ->orderBy('full_name')
            ->paginate(25, ['*'], 'members_page')
            ->withQueryString();

        return view('families.index', [
            'families' => $families,
            'members' => $members,
            'selectedFamilyId' => $familyId,
            // Real totals, so the line above the table can state the whole
            // church's membership instead of describing only this page.
            'totalMembers' => Member::query()->count(),
            'unassignedMembers' => Member::query()->doesntHave('families')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Family::class);
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $family = Family::create($data);

        return redirect()->route('families.show', $family);
    }

    public function show(Family $family): View
    {
        $this->authorize('view', $family);

        // §11: the show page is where a family is actually built — attaching
        // members and labelling their relationship. It needs the members who
        // are NOT already in this family to offer as candidates, so the
        // dropdown cannot suggest a duplicate link. Tenant-scoped by the global
        // scope on Member, so this is never another church's people.
        $attachedIds = $family->members()->pluck('members.id')->all();
        $availableMembers = \App\Models\Member::query()
            ->when($attachedIds !== [], fn ($q) => $q->whereNotIn('id', $attachedIds))
            ->orderBy('full_name')
            ->limit(500)
            ->get();

        return view('families.show', [
            'family' => $family->load('members'),
            'availableMembers' => $availableMembers,
        ]);
    }

    /** Attach a member to a family with a relationship label (§11). */
    public function attachMember(Request $request, Family $family): RedirectResponse
    {
        $this->authorize('update', $family);

        $data = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'relationship' => ['required', 'in:head,spouse,child,dependent,other'],
        ]);

        // exists:members,id above only checks the row exists at all;
        // TenantScope on Member already keeps that lookup within this
        // church, but we re-assert it here explicitly since a family and
        // its members must never straddle two churches.
        $member = \App\Models\Member::findOrFail($data['member_id']);
        abort_unless($member->church_id === $family->church_id, 403);

        $family->members()->syncWithoutDetaching([
            $member->id => ['relationship' => $data['relationship']],
        ]);

        return back()->with('status', 'Family updated.');
    }

    public function destroy(Family $family): RedirectResponse
    {
        $this->authorize('delete', $family);
        $family->delete();

        return redirect()->route('families.index');
    }

    /** §11: remove one member from a household, keeping the member's own record. */
    public function detachMember(Family $family, \App\Models\Member $member): RedirectResponse
    {
        $this->authorize('update', $family);

        // The relation is many-to-many; detach only removes the pivot row.
        // Re-assert tenancy explicitly for the same reason attachMember does —
        // the exists/route binding must never let two churches' records meet.
        abort_unless($member->church_id === $family->church_id, 403);

        $family->members()->detach($member->id);

        return back()->with('status', "{$member->full_name} removed from the household.");
    }
}

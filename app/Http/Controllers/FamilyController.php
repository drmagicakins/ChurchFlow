<?php

namespace App\Http\Controllers;

use App\Models\Family;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FamilyController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Family::class);
        $families = Family::with('members')->paginate(25);

        return view('families.index', compact('families'));
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

        return view('families.show', compact('family'));
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
}

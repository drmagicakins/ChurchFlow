<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Department::class);
        $departments = Department::withCount('members')->paginate(25);

        return view('departments.index', ['departments' => $departments]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Department::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $department = Department::create($data);

        return redirect()->route('departments.show', $department);
    }

    public function show(Department $department): View
    {
        $this->authorize('view', $department);

        // The show page is where a department is staffed — it needs the members
        // NOT already attached, so the picker can never suggest a duplicate.
        $attachedIds = $department->members()->pluck('members.id')->all();
        $availableMembers = \App\Models\Member::query()
            ->when($attachedIds !== [], fn ($q) => $q->whereNotIn('id', $attachedIds))
            ->orderBy('full_name')
            ->limit(500)
            ->get();

        return view('departments.show', [
            'department' => $department->load('members', 'organizationalUnit'),
            'availableMembers' => $availableMembers,
            'branches' => \App\Models\OrganizationalUnit::query()->orderBy('name')->get(),
        ]);
    }

    public function attachMember(Request $request, Department $department): RedirectResponse
    {
        $this->authorize('update', $department);

        $data = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
        ]);

        $member = Member::findOrFail($data['member_id']);
        abort_unless($member->church_id === $department->church_id, 403);

        $department->members()->syncWithoutDetaching([$member->id]);

        return back()->with('status', ucfirst('department').' updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->authorize('delete', $department);
        $department->delete();

        return redirect()->route('departments.index');
    }

    /** §12: remove one member from a department without touching their record. */
    public function detachMember(Department $department, Member $member): RedirectResponse
    {
        $this->authorize('update', $department);
        abort_unless($member->church_id === $department->church_id, 403);

        $department->members()->detach($member->id);

        return back()->with('status', "{$member->full_name} removed from the department.");
    }
}

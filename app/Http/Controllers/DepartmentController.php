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
        // visibleTo() keeps whole-church departments (stored with a NULL parish)
        // that TenantScope on its own would hide from a parish pastor.
        $departments = Department::query()
            ->visibleTo(auth()->user())
            ->withCount('members')
            ->paginate(25);

        return view('departments.index', ['departments' => $departments]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Department::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255',
                // The unique index is (church_id, organizational_unit_id, name),
                // so the same NAME is legal in two different parishes. Validating
                // that shape here turns a duplicate into a field error on the form
                // instead of a 500 from the database.
                \Illuminate\Validation\Rule::unique('departments', 'name')
                    ->where('church_id', $request->user()->church_id)
                    ->where('organizational_unit_id', $request->input('organizational_unit_id'))
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            // Optional on purpose: leaving it blank files the department against
            // the WHOLE church rather than a branch. Without it, a church with
            // two parishes could only ever staff one of them. A parish-scoped
            // user never sees this field and the TenantScope fills it in.
            'organizational_unit_id' => ['nullable', 'integer', 'exists:organizational_units,id'],
        ]);

        if (! empty($data['organizational_unit_id'])) {
            $unit = \App\Models\OrganizationalUnit::findOrFail($data['organizational_unit_id']);
            abort_unless($unit->church_id === ($request->user()->church_id), 403);
        }

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
            'canManage' => auth()->user()->hasPermission('departments.manage'),
        ]);
    }

    /**
     * Rename / re-describe / re-file a department.
     *
     * The create form was the only way to set any of this, so a typo in a
     * department's name was permanent. Kept to the three fields the store
     * accepts (plus the branch it belongs to), so the two forms cannot drift.
     */
    public function update(Request $request, Department $department): RedirectResponse
    {
        $this->authorize('update', $department);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'organizational_unit_id' => ['nullable', 'integer', 'exists:organizational_units,id'],
        ]);

        // A department may not be moved to another church's branch by posting
        // an id directly — exists:organizational_units,id only proves the row
        // exists, not that it is ours.
        if (! empty($data['organizational_unit_id'])) {
            $unit = \App\Models\OrganizationalUnit::findOrFail($data['organizational_unit_id']);
            abort_unless($unit->church_id === $department->church_id, 403);
        }

        $department->update($data);

        return redirect()->route('departments.show', $department)
            ->with('status', 'Department updated.');
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

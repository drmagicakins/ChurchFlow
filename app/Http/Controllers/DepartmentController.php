<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Member;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Department::class);

        // The LIST is strictly parish-owned. A parish pastor opened /departments
        // and was shown the church's whole-church departments as well, which are
        // the owner's, not theirs. TenantScope already narrows to their parish;
        // it is applied explicitly here so the list cannot depend on the tenant
        // binding having been registered. The church owner (registrar) is not
        // parish-scoped and keeps seeing every department.
        //
        // NOTE: visibleTo() is deliberately NOT used here — it is the scope that
        // ADDS whole-church departments. It is still used for route binding and
        // for the SMS/attendance pickers, where a church-wide department must
        // stay reachable.
        $user = auth()->user();
        $departments = Department::query()
            ->when(
                $user->isParishScoped(),
                fn ($query) => $query->where('departments.organizational_unit_id', $user->organizational_unit_id),
            )
            ->withCount('members')
            ->paginate(25);

        return view('departments.index', ['departments' => $departments]);
    }

    /**
     * The "this name is already taken" rule, shared by store and update.
     *
     * The old rule compared the raw name with `where(... NULL)` for a
     * whole-church department. Both halves were wrong:
     *   - `where('organizational_unit_id', null)` compiles to
     *     `organizational_unit_id = NULL`, which is never true in SQL, so the
     *     condition silently did nothing;
     *   - even in a parish, "Choir" and "choir" are different strings and
     *     both got through.
     * Names are now compared in normalised form against the branch the row is
     * actually stored in (WHOLE_CHURCH = 0, not NULL), which is the same pair
     * the new unique index enforces.
     */
    private function nameIsFree(Request $request, ?Department $ignore = null): Closure
    {
        return function (string $attribute, $value, Closure $fail) use ($request, $ignore): void {
            // Where the branch foreign key is still enforced, a whole-church
            // department is stored as NULL; where the sentinel migration has
            // run it is stored as 0. "Is it filed here?" therefore has to ask
            // for both spellings of the SAME branch — never for a branch id OR
            // the whole-church spelling, which would report any whole-church
            // "Choir" as taken inside every branch.
            $branch = Department::unitIdFor($request->input('organizational_unit_id'));

            $taken = Department::withTrashed()
                ->where('church_id', $request->user()->church_id)
                ->where(fn ($query) => $branch !== null
                    ? $query->where('organizational_unit_id', $branch)
                    : $query->whereNull('organizational_unit_id')->orWhere('organizational_unit_id', Department::WHOLE_CHURCH))
                // Matched in PHP against normaliseName() rather than with a
                // lower(trim()) predicate, because squish() also collapses
                // internal double spaces — "Youth  Choir" and "Youth Choir"
                // are one department, and only PHP can say so for both
                // SQLite and MySQL.
                ->get(['id', 'name'])
                ->contains(fn (Department $existing) => Department::normaliseName($existing->name) === Department::normaliseName($value)
                    && ($ignore === null || (int) $existing->getKey() !== (int) $ignore->getKey()));

            if ($taken) {
                $fail('A department with this name already exists in the same branch. Choose a different name, or add members to the existing one.');
            }
        };
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Department::class);

        // A branch-scoped pastor never sees the Branch field, so the branch is
        // taken from their own account. Church-wide users keep the form choice.
        $request->merge([
            'organizational_unit_id' => Department::mayAssignTo($request->user())
                ?? $request->input('organizational_unit_id'),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->nameIsFree($request)],
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

    /**
     * Resolve `{department}` the way the index lists it.
     *
     * The route binding already applies this (see
     * Department::resolveRouteBindingQuery), and it stays here as the same
     * guard the index uses: a parish pastor may open a whole-church department
     * (stored with a NULL parish) as well as their own parish's, and nothing
     * else. TenantScope is replaced, not widened, for the same reason the
     * index replaces it.
     */
    private function boundDepartment(Request $request, Department $department): Department
    {
        $visible = Department::query()
            ->visibleTo($request->user())
            ->whereKey($department->getKey())
            ->first();

        abort_if($visible === null, 404);

        return $visible;
    }

    public function show(Request $request, Department $department): View
    {
        $department = $this->boundDepartment($request, $department);
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
        $department = $this->boundDepartment($request, $department);
        $this->authorize('update', $department);

        // A parish pastor may re-file a department they own only into their own
        // parish; the Branch select is hidden from them, but a hand-crafted
        // request could still say "whole church".
        $request->merge([
            'organizational_unit_id' => Department::mayAssignTo($request->user())
                ?? $request->input('organizational_unit_id'),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->nameIsFree($request, $department)],
            'description' => ['nullable', 'string', 'max:2000'],
            'organizational_unit_id' => ['nullable', 'integer', 'exists:organizational_units,id'],
        ]);

        // Where a whole-church department is stored as NULL, "Whole church"
        // has to be written as NULL — the cast to int (0) would be read as a
        // branch id and refused by the foreign key. Only a BLANK branch is
        // rewritten: a branch id stays the int it already is.
        if (empty($data['organizational_unit_id'])) {
            $data['organizational_unit_id'] = Department::unitIdFor(null);
        }

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
        $department = $this->boundDepartment(request(), $department);
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

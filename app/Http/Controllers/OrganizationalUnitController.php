<?php

namespace App\Http\Controllers;

use App\Models\OrganizationalUnit;
use App\Models\UnitType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Branches — §4/§14. A church's organisational structure is deliberately
 * generic: a "branch" is an OrganizationalUnit at any level of a tree the
 * church names itself (Province, Diocese, Region, Parish...), configured
 * through UnitType. This controller is the missing create/list UI: the model
 * and the plan limit (max_branches) already existed, but nothing let a church
 * owner actually add a branch, so the plan limit could never be hit and
 * every branch picker elsewhere (events, announcements, attendance, finance)
 * had nothing to offer.
 *
 * The first unit type ("Branch" by default) is created on demand the first
 * time a church adds a branch, so a brand-new church is never blocked by an
 * empty dropdown it has no obvious way to fill.
 */
class OrganizationalUnitController extends Controller
{
    /** The label a church sees for its top-level unit, from its own terminology. */
    private function topLevelLabel(): string
    {
        return auth()->user()?->church?->label('branch', 'Branch') ?? 'Branch';
    }

    public function index(): View
    {
        $this->authorize('viewAny', OrganizationalUnit::class);

        $units = OrganizationalUnit::query()
            ->with('unitType')
            ->withCount(['members', 'departments'])
            ->orderBy('name')
            ->paginate(25);

        $unitTypes = UnitType::query()->orderBy('level')->orderBy('name')->get();

        return view('branches.index', [
            'units' => $units,
            'unitTypes' => $unitTypes,
            'label' => $this->topLevelLabel(),
            'canManage' => auth()->user()->hasPermission('settings.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', OrganizationalUnit::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit_type_id' => ['nullable', 'integer', 'exists:unit_types,id'],
            'parent_id' => ['nullable', 'integer', 'exists:organizational_units,id'],
            'code' => ['nullable', 'string', 'max:60'],
        ]);

        // Create the default type on first use, so an empty UnitType list — the
        // normal state of a new church — is not a dead end.
        $unitTypeId = $data['unit_type_id'] ?? UnitType::firstOrCreate(
            ['church_id' => auth()->user()->church_id, 'name' => 'Branch'],
            ['level' => 0],
        )->id;

        $unit = OrganizationalUnit::create([
            'name' => $data['name'],
            'unit_type_id' => $unitTypeId,
            'parent_id' => $data['parent_id'] ?? null,
            'code' => $data['code'] ?? null,
        ]);

        // §14 max_branches is enforced inside OrganizationalUnit::booted(), so
        // hitting the plan cap aborts with a 422 that names the limit. We do
        // not catch it — the message is exactly what the owner needs to see,
        // and swallowing it would hide the upgrade path.
        return redirect()->route('branches.index')
            ->with('status', "{$this->topLevelLabel()} “{$unit->name}” created.");
    }

    public function destroy(OrganizationalUnit $branch): RedirectResponse
    {
        $this->authorize('delete', $branch);

        $name = $branch->name;
        $branch->delete();

        return redirect()->route('branches.index')
            ->with('status', "{$name} removed.");
    }
}

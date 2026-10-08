<?php

namespace App\Domains\Members\Filters;

use App\Models\Member;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The one place that turns grid query params into a Member query. Used by
 * both MemberController::index() and the CSV export, so "export respects
 * the grid's current filters" (§33) is structural — the export can't drift
 * from what the grid shows because they share this method, not two copies
 * of the same five `when()` clauses.
 */
class MemberFilters
{
    public const SORTABLE = ['full_name', 'membership_status', 'date_joined', 'membership_number'];

    public static function apply(Request $request): Builder
    {
        $sort = $request->string('sort', 'full_name')->toString();
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'full_name';
        $direction = $request->string('direction', 'asc')->toString() === 'desc' ? 'desc' : 'asc';

        return Member::query()
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('status'), fn ($q) => $q->where('membership_status', $request->string('status')))
            ->when($request->filled('organizational_unit_id'), fn ($q) => $q->where('organizational_unit_id', $request->integer('organizational_unit_id')))
            ->when($request->filled('department_id'), function ($q) use ($request) {
                $q->whereHas('departments', fn ($d) => $d->where('departments.id', $request->integer('department_id')));
            })
            ->orderBy($sort, $direction);
    }
}

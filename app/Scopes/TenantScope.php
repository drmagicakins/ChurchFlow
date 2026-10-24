<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Applied automatically to every model using the BelongsToTenant trait.
 *
 * This is the structural guard against the classic "forgot the
 * WHERE church_id = ?" bug: it is not opt-in per query, it is bound into
 * every query the model builder produces, including relationship queries.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $churchId = app()->bound('tenant.church_id')
            ? app('tenant.church_id')
            : null;

        // Platform-admin routes explicitly disable tenancy (see
        // IdentifyTenant middleware) — in that case no scope is applied,
        // and platform-admin controllers are trusted to filter deliberately
        // when they need to. Everywhere else, no bound tenant means no rows:
        // fail closed, never fail open.
        if (app()->bound('tenant.disabled') && app('tenant.disabled') === true) {
            return;
        }

        if ($churchId === null) {
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->where($model->getTable().'.church_id', $churchId);

        // §14/§37 parish scoping. When a parish is bound for this request (the
        // signed-in user belongs to one and is not the registrar), any model
        // that carries an organizational_unit_id column is narrowed to that
        // parish. Models with no such column (transactions, families, ...) are
        // unaffected — they are church-wide records, not parish-owned ones.
        //
        // Known exception: departments.organizational_unit_id is NULLABLE, and
        // NULL there means "belongs to the WHOLE church" rather than "belongs
        // to no parish". This filter alone therefore hid every whole-church
        // department from every parish pastor. Department::visibleTo() is the
        // companion scope that keeps those rows and drops other parishes'.
        $parishId = app()->bound('tenant.organizational_unit_id')
            ? app('tenant.organizational_unit_id')
            : null;

        if ($parishId !== null && $this->hasColumn($model, 'organizational_unit_id')) {
            $builder->where($model->getTable().'.organizational_unit_id', $parishId);
        }
    }

    /**
     * Does this model's table carry the given column?
     *
     * Resolved once per table and cached for the request, because the answer
     * cannot change mid-request and the schema lookup is not free. This keeps
     * parish scoping generic: a model opts in simply by having the column,
     * with no per-model registration to forget.
     */
    private function hasColumn(Model $model, string $column): bool
    {
        $table = $model->getTable();
        $cacheKey = 'tenant.columns.'.$table;

        if (! app()->bound($cacheKey)) {
            app()->instance($cacheKey, \Illuminate\Support\Facades\Schema::hasColumn($table, $column));
        }

        return app($cacheKey);
    }
}

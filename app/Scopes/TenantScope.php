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
    }
}

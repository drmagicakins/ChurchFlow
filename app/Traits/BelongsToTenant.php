<?php

namespace App\Traits;

use App\Models\Church;
use App\Scopes\TenantScope;

/**
 * Every tenant-owned model (Member, Department, FinancialAccount,
 * SubventionSubmission, ...) uses this trait. It:
 *
 *  1. Registers the global TenantScope so reads are automatically scoped.
 *  2. Auto-fills church_id on create from the currently bound tenant, so
 *     application code never has to pass it explicitly (and can't forget to).
 *  3. Exposes a church() relation for the (rare) cases something needs it
 *     explicitly.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model): void {
            if (empty($model->church_id) && app()->bound('tenant.church_id')) {
                $model->church_id = app('tenant.church_id');
            }
        });
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }
}

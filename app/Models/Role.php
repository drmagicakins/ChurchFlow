<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Role deliberately does NOT use the BelongsToTenant trait. Its scoping
 * rule is different from a normal tenant-owned model: a role is visible if
 * it belongs to the current church OR is a system default (church_id null),
 * whereas BelongsToTenant/TenantScope treats a null tenant as "show
 * nothing" (fail closed) for models where that null should never happen.
 * Keeping this divergent rule local to Role, rather than adding a flag to
 * the shared trait, keeps the tenant-isolation guarantee simple to audit
 * for every other model.
 */
class Role extends Model
{
    protected $fillable = ['church_id', 'name', 'is_system_default'];

    protected $casts = ['is_system_default' => 'boolean'];

    protected static function booted(): void
    {
        static::addGlobalScope('visibleToTenant', function (Builder $builder) {
            if (app()->bound('tenant.disabled') && app('tenant.disabled') === true) {
                return;
            }

            $churchId = app()->bound('tenant.church_id') ? app('tenant.church_id') : null;

            $builder->where(function (Builder $q) use ($churchId) {
                $q->whereNull('church_id');
                if ($churchId !== null) {
                    $q->orWhere('church_id', $churchId);
                }
            });
        });
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}

<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizationalUnit extends Model
{
    use BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = ['church_id', 'unit_type_id', 'parent_id', 'name', 'code'];

    protected static function booted(): void
    {
        static::creating(function (OrganizationalUnit $unit) {
            // §14 max_branches. Deliberately counts every organizational
            // unit, not just leaf-level "branches" — this platform's
            // hierarchy is generic (§4), so a church's Province/Area/Parish
            // rows all count the same way a dedicated "branches" table
            // would elsewhere. Refine to a level-aware count if a real
            // deployment needs branches and higher organizational levels
            // limited separately.
            if ($unit->church_id) {
                app(\App\Domains\Subscriptions\Services\PlanLimitService::class)->assertCanAdd(
                    Church::findOrFail($unit->church_id),
                    'branches',
                    static::withoutGlobalScopes()->where('church_id', $unit->church_id)->count(),
                );
            }
        });
    }

    public function unitType()
    {
        return $this->belongsTo(UnitType::class);
    }

    public function parent()
    {
        return $this->belongsTo(OrganizationalUnit::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(OrganizationalUnit::class, 'parent_id');
    }
}

<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = ['church_id', 'organizational_unit_id', 'name', 'description'];

    /**
     * Departments are listed alphabetically.
     *
     * Nothing was ordering them, so SQLite handed them back in DEPARTMENT table
     * order and MySQL in whatever its index chose. A department created later
     * could therefore appear at the top of the list, which is how a new one
     * looked like it had "disappeared" — it was simply above the fold. Ordering
     * at the model means every screen and picker agrees.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('alphabetical', fn ($query) => $query->orderBy('name'));
    }

    /**
     * Only the departments this user should see.
     *
     * TenantScope narrows a parish-scoped user to `organizational_unit_id =
     * their parish`, and a department filed against the WHOLE church (the
     * column is nullable and that is a supported choice) has NULL there — so
     * every church-wide department was invisible to every parish pastor, even
     * though the rest of their church structure was not. Like TenantScope, this
     * is a scope rather than a controller rule so no screen can forget it.
     *
     * The church-wide administrator (owner, or anyone with no parish) is
     * unaffected. Use it where a parish pastor is choosing which departments to
     * act on; it REPLACES TenantScope's parish filter with the wider one above,
     * and keeps the church filter.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isParishScoped()) {
            return $query;
        }

        // The global TenantScope has already ANDed `organizational_unit_id = ?`
        // onto this query. Adding an OR to it would still be ANDed with that
        // equality, so NULL rows would remain excluded — the filter has to be
        // replaced, not widened. The church id stays: that is the tenant guard,
        // and it is what keeps another church's rows out.
        return $query
            ->withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('departments.church_id', $user->church_id)
            ->where(fn (Builder $inner) => $inner
                ->whereNull('departments.organizational_unit_id')
                ->orWhere('departments.organizational_unit_id', $user->organizational_unit_id));
    }

    public function organizationalUnit()
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function members()
    {
        return $this->belongsToMany(Member::class, 'department_member')
            ->withPivot('role')
            ->withTimestamps();
    }
}

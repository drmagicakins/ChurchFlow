<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = ['church_id', 'organizational_unit_id', 'name', 'description'];

    public function members()
    {
        return $this->belongsToMany(Member::class, 'group_member')->withTimestamps();
    }

    /**
     * The parish a group belongs to, if it belongs to one.
     *
     * NULL means "whole church" — the shape every group had before the parish
     * column existed.
     */
    public function organizationalUnit()
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    /**
     * Only the groups this user may work with.
     *
     * A parish-scoped pastor sees the groups filed against THEIR parish and
     * nothing else — groups belonging to another parish, and church-wide
     * legacy groups (organizational_unit_id NULL), are not theirs to see.
     * TenantScope replaces the filter rather than widening it, for the same
     * reason Department::visibleTo() does: the church guard must stay.
     *
     * The church-wide administrator (owner / registrar / no parish) is
     * unaffected and keeps seeing every group.
     */
    /**
     * The parish a new group must be filed against for this user.
     *
     * A parish-scoped pastor may only create groups for their own parish; a
     * church-wide user returns null and keeps the form's choice.
     */
    public static function mayAssignTo(User $user): int|null
    {
        return $user->isParishScoped() ? (int) $user->organizational_unit_id : null;
    }

    /**
     * Only the groups this user may work with.
     *
     * A parish-scoped pastor sees the groups filed against THEIR parish and
     * nothing else — groups belonging to another parish, and church-wide
     * legacy groups (organizational_unit_id NULL), are not theirs to see.
     * TenantScope replaces the filter rather than widening it, for the same
     * reason Department::visibleTo() does: the church guard must stay.
     *
     * The church-wide administrator (owner / registrar / no parish) is
     * unaffected and keeps seeing every group.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isParishScoped()) {
            return $query;
        }

        return $query
            ->withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('groups.church_id', $user->church_id)
            ->where('groups.organizational_unit_id', $user->organizational_unit_id);
    }

    /**
     * Resolve `{group}` with the same rule the index lists with, so a pastor
     * cannot open another parish's group (or a whole-church one) by id.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $user = auth()->user();

        if ($user instanceof User && $user->church_id !== null) {
            $query = $query->visibleTo($user);
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }
}

<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = [
        'church_id', 'created_by', 'title', 'body', 'audience_type',
        'organizational_unit_id', 'department_id', 'group_id', 'publish_at',
    ];

    protected function casts(): array
    {
        return ['publish_at' => 'datetime'];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The branch this announcement targets, when audience_type = branch. */
    public function organizationalUnit()
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    /** The department this announcement targets, when audience_type = department. */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /** The group this announcement targets, when audience_type = group. */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function scopePublished($query)
    {
        return $query->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()));
    }

    /**
     * Only the announcements this user should see.
     *
     * TenantScope narrows a parish-scoped user to `organizational_unit_id =
     * their parish`, because this table carries that column. But a CHURCH-WIDE
     * announcement stores NULL there (it is not filed against any branch), so
     * every church-wide announcement was invisible to every parish pastor —
     * and, worse, the redirect to `announcements.show` after posting one 404'd,
     * because route-model binding could not find the row it had just created.
     *
     * This is the same shape as Department::visibleTo(), and for the same
     * reason: the parish filter has to be REPLACED, not widened, because the
     * global scope has already ANDed an equality onto the query. The church id
     * stays — that is the tenant guard, and it is what keeps another church's
     * announcements out.
     *
     * A parish pastor sees: church-wide announcements, announcements for their
     * own branch, and department/group announcements (those carry no branch of
     * their own — the audience is the department or group, which is already
     * scoped by its own model). The church-wide administrator is unaffected.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if (! $user->isParishScoped()) {
            return $query;
        }

        return $query
            ->withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('announcements.church_id', $user->church_id)
            ->where(fn ($inner) => $inner
                ->whereNull('announcements.organizational_unit_id')
                ->orWhere('announcements.organizational_unit_id', $user->organizational_unit_id));
    }

    /**
     * Resolve `{announcement}` for route-model binding with the same rule as
     * the index list.
     *
     * Without this, posting a church-wide announcement as a parish pastor
     * redirected to a 404: the row was created (church_id set, branch NULL),
     * but the binding query carried TenantScope's `organizational_unit_id = ?`
     * and could not see it. Doing it here rather than in the controller means
     * no route can forget it, and TenantScope itself stays untouched.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $user = auth()->user();

        if ($user instanceof User && $user->church_id !== null) {
            $query = $query->visibleTo($user);
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }

    /**
     * §19 targeting: is this announcement meant for the given member, given
     * their department/group memberships and organizational unit?
     */
    public function isVisibleToMember(Member $member): bool
    {
        return match ($this->audience_type) {
            // 'church_wide' is the value the announcements form has always
            // posted (and the column default is the older 'church'), so both
            // spellings mean "everyone" here. Recognising only one of them
            // silently dropped church-wide announcements from every member's
            // feed — the announcement existed and emailed nobody.
            'church', 'church_wide' => true,
            'branch' => $member->organizational_unit_id === $this->organizational_unit_id,
            'department' => $member->departments()->where('departments.id', $this->department_id)->exists(),
            'group' => $member->groups()->where('groups.id', $this->group_id)->exists(),
            default => false,
        };
    }
}

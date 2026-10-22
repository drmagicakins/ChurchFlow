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

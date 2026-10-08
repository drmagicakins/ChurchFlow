<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrayerRequest extends Model
{
    use BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = [
        'church_id', 'member_id', 'submitted_by_name', 'request',
        'is_confidential', 'status', 'assigned_to',
    ];

    protected $casts = ['is_confidential' => 'boolean'];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function submitterName(): string
    {
        return $this->member?->full_name ?? $this->submitted_by_name ?? 'Anonymous';
    }

    /**
     * §21: pastoral information is NOT visible to ordinary administrators.
     * A user sees every prayer request only with pastoral.manage; otherwise
     * they see only the ones assigned to them.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasPermission('pastoral.manage')) {
            return $query;
        }

        return $query->where('assigned_to', $user->id);
    }
}

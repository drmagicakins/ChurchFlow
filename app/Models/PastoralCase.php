<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PastoralCase extends Model
{
    use BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = [
        'church_id', 'member_id', 'type', 'description', 'status', 'priority',
        'assigned_to', 'opened_by', 'closed_at',
    ];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function notes()
    {
        return $this->hasMany(PastoralCaseNote::class)->latest('id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    /** §21: same strict-visibility rule as PrayerRequest — see the comment there. */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasPermission('pastoral.manage')) {
            return $query;
        }

        return $query->where('assigned_to', $user->id);
    }

    public function close(?string $closingNote = null, ?User $closedBy = null): void
    {
        $this->update(['status' => 'closed', 'closed_at' => now()]);

        if ($closingNote) {
            $this->notes()->create(['author_id' => $closedBy?->id, 'note' => $closingNote]);
        }
    }
}

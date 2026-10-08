<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = [
        'church_id', 'member_id', 'pastoral_case_id', 'pastor_id',
        'title', 'scheduled_at', 'duration_minutes', 'location', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function pastoralCase()
    {
        return $this->belongsTo(PastoralCase::class);
    }

    public function pastor()
    {
        return $this->belongsTo(User::class, 'pastor_id');
    }

    /**
     * §21: an appointment tied to a pastoral case inherits that case's
     * strict visibility; a plain appointment (no case — e.g. a routine
     * meeting) is visible to anyone who can see appointments at all, since
     * it carries no sensitive content by itself.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasPermission('pastoral.manage')) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('pastor_id', $user->id)
                ->orWhereNull('pastoral_case_id');
        });
    }
}

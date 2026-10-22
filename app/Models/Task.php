<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = [
        'church_id', 'assigned_to', 'created_by', 'event_id',
        'title', 'description', 'due_date', 'remind_at', 'reminder_sent_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'remind_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    /**
     * §24: a reminder is "due to fire" when it has a remind_at in the past,
     * has not already been shown, and the task is still open. Used by the
     * dashboard/reminder endpoint to decide what to pop up.
     */
    public function scopeReminderDue($query)
    {
        return $query->whereNotNull('remind_at')
            ->whereNull('reminder_sent_at')
            ->where('remind_at', '<=', now())
            ->whereNotIn('status', ['done', 'cancelled']);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}

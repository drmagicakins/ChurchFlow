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
        'title', 'description', 'due_date', 'status',
    ];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
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

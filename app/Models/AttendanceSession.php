<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AttendanceSession extends Model
{
    use BelongsToTenant, Auditable;

    protected $fillable = [
        'church_id', 'organizational_unit_id', 'event_id', 'department_id', 'group_id',
        'name', 'type', 'session_date',
    ];

    protected function casts(): array
    {
        return ['session_date' => 'date'];
    }

    public function organizationalUnit()
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function records()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function presentCount(): int
    {
        return $this->records()->whereIn('status', ['present', 'late'])->count();
    }
}

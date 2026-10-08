<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = [
        'church_id', 'organizational_unit_id', 'title', 'description', 'cover_image_path',
        'venue', 'starts_at', 'ends_at', 'capacity', 'speakers', 'organizer_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'speakers' => 'array',
        ];
    }

    public function organizationalUnit()
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function registrations()
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function attendanceSessions()
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function registeredMembers()
    {
        return $this->belongsToMany(Member::class, 'event_registrations')
            ->wherePivot('status', 'registered')
            ->withPivot('status', 'registered_at')
            ->withTimestamps();
    }

    public function confirmedCount(): int
    {
        return $this->registrations()->where('status', 'registered')->count();
    }

    public function hasCapacityFor(int $additional = 1): bool
    {
        if ($this->capacity === null) {
            return true;
        }

        return ($this->confirmedCount() + $additional) <= $this->capacity;
    }
}

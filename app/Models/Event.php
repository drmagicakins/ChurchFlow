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
        'public_slug', 'registration_open',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'speakers' => 'array',
            'registration_open' => 'boolean',
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

    /**
     * Visitors who signed up through the PUBLIC form (no account, no member
     * record). Kept apart from registrations() because those rows point at a
     * member; this relation is what the organiser's "everyone who filled the
     * form" table and the reminder mailer read.
     */
    public function visitorRegistrations()
    {
        return $this->hasMany(EventRegistrationSubmission::class);
    }

    /**
     * The public registration link, or null when the event has no slug yet.
     * A slug is minted lazily (see publicSlug()) so an event created before
     * this feature existed — or by a route that predates it — still gets one
     * the first time somebody asks to share it.
     */
    public function publicSlug(): ?string
    {
        return $this->public_slug;
    }

    public function hasPublicForm(): bool
    {
        return $this->public_slug !== null && $this->registration_open;
    }

    /**
     * Total people expected: members registered in-app plus visitors who used
     * the public form, each visitor with their guests. Capacity is checked
     * against this, so a public sign-up cannot quietly oversubscribe an event
     * that the in-app roster already filled.
     */
    public function totalExpected(): int
    {
        $visitors = $this->visitorRegistrations()
            ->where('status', 'registered')
            ->sum("guests");

        return $this->confirmedCount()
            + $this->visitorRegistrations()->where('status', 'registered')->count()
            + (int) $visitors;
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

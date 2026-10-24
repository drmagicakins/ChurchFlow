<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A visitor who signed up through an event's PUBLIC registration form.
 *
 * Deliberately a different row from EventRegistration: that one points at a
 * member on the church roll, and a visitor arriving from a shared link has no
 * member record. Keeping them separate means an import of visitors can never
 * silently create CRM members, while the event page still shows one combined
 * list of everyone attending.
 */
class EventRegistrationSubmission extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'church_id', 'event_id', 'name', 'email', 'phone', 'organization',
        'guests', 'notes', 'status', 'ip_address', 'registered_at', 'source',
        'email_reminded_at', 'sms_reminded_at',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'email_reminded_at' => 'datetime',
        'sms_reminded_at' => 'datetime',
        'guests' => 'integer',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /** How many people this one submission brings (the visitor + their guests). */
    public function headcount(): int
    {
        return 1 + max((int) $this->guests, 0);
    }

    protected static function booted(): void
    {
        static::creating(function (self $row) {
            $row->name = trim((string) $row->name);
            $row->email = $row->email !== null ? mb_strtolower(trim((string) $row->email)) : null;
            $row->phone = $row->phone !== null ? trim((string) $row->phone) : null;
        });
    }
}

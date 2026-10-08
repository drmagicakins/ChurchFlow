<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Full member CRM record (§10). Phase 1 shipped a stub of this table just
 * to prove tenant scoping; Phase 2 fills it out.
 */
class Member extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = [
        'church_id', 'organizational_unit_id', 'full_name', 'email', 'photo_path', 'photo_upload_id',
        'gender', 'date_of_birth', 'phone', 'address',
        'emergency_contact_name', 'emergency_contact_phone',
        'membership_status', 'date_joined', 'baptism_date', 'is_worker', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'date_joined' => 'date',
            'baptism_date' => 'date',
            'is_worker' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Member $member) {
            if (empty($member->membership_number)) {
                $member->membership_number = static::nextMembershipNumber($member->church_id);
            }
            if (empty($member->membership_status)) {
                $member->membership_status = 'active';
            }

            // §14: the plan's member allowance is enforced HERE rather than in
            // every path that can create a Member (MemberController::store,
            // ImportMembersFromCsv, and any future one) — one choke point that
            // a new caller cannot accidentally bypass.
            if ($member->church_id) {
                app(\App\Domains\Subscriptions\Services\PlanLimitService::class)->assertCanAdd(
                    \App\Models\Church::findOrFail($member->church_id),
                    'members',
                    static::withoutGlobalScopes()->where('church_id', $member->church_id)->count(),
                );
            }
        });
    }

    /**
     * Generates e.g. "MB-2026-0007" scoped per church. Uses a per-church
     * count rather than a global auto-increment so numbers stay meaningful
     * and don't leak how many members other churches have.
     *
     * NOTE: under high concurrent write load this count-then-format
     * approach can race; Phase 4+ (once a dedicated members write path
     * exists) should move this into a DB-level sequence or a
     * SELECT ... FOR UPDATE counter row per church if that becomes a
     * real bottleneck. Fine for expected church-admin-driven write volume.
     */
    public static function nextMembershipNumber(int $churchId): string
    {
        $year = now()->year;
        $count = static::withTrashed()
            ->withoutGlobalScopes()
            ->where('church_id', $churchId)
            ->whereYear('created_at', $year)
            ->count();

        return sprintf('MB-%d-%04d', $year, $count + 1);
    }

    public function organizationalUnit()
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function families()
    {
        return $this->belongsToMany(Family::class, 'family_member')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_member')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_member')->withTimestamps();
    }

    public function customFieldValues()
    {
        return $this->hasMany(MemberCustomFieldValue::class);
    }

    /**
     * Phase 10: the UploadedFile backing this member's photo. A signed URL
     * expires, so it must never be stored permanently — this FK is, and
     * photoUrl() mints a fresh one on every render.
     */
    public function photoUpload()
    {
        return $this->belongsTo(UploadedFile::class, 'photo_upload_id');
    }

    /**
     * Returns a signed, time-limited URL to the member's photo, or null when
     * no photo has been uploaded. Falls back to the legacy public
     * `photo_path` (Phase 2 placeholder) if it happens to be set and no
     * uploaded file is linked, so old data still renders.
     */
    public function photoUrl(): ?string
    {
        if ($this->photo_upload_id && $this->photoUpload) {
            return $this->photoUpload->signedUrl();
        }

        return $this->photo_path ?: null;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (!$term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('full_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('membership_number', 'like', "%{$term}%");
        });
    }
}

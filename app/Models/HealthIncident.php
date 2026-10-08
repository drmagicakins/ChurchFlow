<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per distinct platform problem — see the migration for why this is
 * per-problem rather than per-occurrence.
 *
 * NOT tenant-scoped, deliberately and for the same reason `users.is_platform_admin`
 * is not: platform health is about the platform, not about any one church. A
 * database that is down is down for everyone, and a per-church row would mean
 * a thousand copies of one outage and no way to see the outage.
 */
class HealthIncident extends Model
{
    protected $fillable = [
        'check_name', 'severity', 'fingerprint', 'message', 'context',
        'first_seen_at', 'last_seen_at', 'occurrences',
        'resolved_at', 'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
            'occurrences' => 'integer',
        ];
    }

    /** The definition of "currently broken" — see the migration. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereNotNull('resolved_at');
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }

    /** How long the platform has been (or was) in this state. */
    public function duration(): \Illuminate\Support\Carbon
    {
        return $this->first_seen_at->diffAsCarbonInterval($this->resolved_at ?? now());
    }

    public function severityRank(): int
    {
        return match ($this->severity) {
            'critical' => 4,
            'error' => 3,
            'warning' => 2,
            default => 1,
        };
    }
}

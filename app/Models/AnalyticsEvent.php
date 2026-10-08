<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Deliberately NOT tenant-scoped via the usual BelongsToTenant trait: the
 * platform's own onboarding-analytics dashboard (a genuine future need per
 * §55) has to aggregate across every church, which is exactly the
 * cross-tenant read Phase 8's platform-admin screens already established a
 * safe pattern for (query under PlatformAdminOnly, which flips
 * 'tenant.disabled'). A church-scoped view of its own events, if ever
 * needed, filters by church_id explicitly instead.
 */
class AnalyticsEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['church_id', 'user_id', 'event_name', 'properties'];

    protected function casts(): array
    {
        return ['properties' => 'array', 'created_at' => 'datetime'];
    }
}

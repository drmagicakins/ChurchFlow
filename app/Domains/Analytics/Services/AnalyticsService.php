<?php

namespace App\Domains\Analytics\Services;

use App\Models\AnalyticsEvent;
use App\Models\Church;
use App\Models\User;

/**
 * §55: track product-usage facts only — which tour, which step, which
 * feature — never free text or anything identifying beyond the user/church
 * ids the rest of the app already has. `track()` never throws: a broken
 * analytics call must not break the user-facing action it's attached to.
 */
class AnalyticsService
{
    public function track(string $eventName, ?User $user = null, ?Church $church = null, array $properties = []): void
    {
        try {
            AnalyticsEvent::create([
                'church_id' => $church?->id ?? $user?->church_id,
                'user_id' => $user?->id,
                'event_name' => $eventName,
                'properties' => $properties,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

<?php

namespace App\Domains\Onboarding\Listeners;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Subscriptions\Events\ChurchActivated;

/**
 * Same decoupling pattern Phase 7 established for Subvention→Communication:
 * Billing (Phase 8) has no idea Onboarding exists. It just fires
 * ChurchActivated; this listener (registered in AppServiceProvider) is what
 * turns that into the first onboarding-analytics event.
 */
class TrackOnboardingStarted
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function handle(ChurchActivated $event): void
    {
        $this->analytics->track('onboarding_started', null, $event->church);
    }
}

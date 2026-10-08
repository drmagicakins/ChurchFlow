<?php

namespace App\Domains\Onboarding\Services;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Models\Tour;
use App\Models\User;
use App\Models\UserTourProgress;

/**
 * §31: "The guided tour system must not be hard-coded only for onboarding
 * ... must be reusable across the application." One engine, used for the
 * first-login welcome tour AND for "✨ New Feature" prompts — the only
 * difference between them is which Tour row is being shown.
 */
class TourEngineService
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    /**
     * §31: should this tour be offered right now? No if the user
     * permanently dismissed it ("don't show again"), or already completed
     * THIS version of it. A version bump makes a previously-completed tour
     * eligible again without disturbing anyone currently mid-tour on the
     * version they started.
     */
    public function shouldShow(User $user, Tour $tour): bool
    {
        if (!$tour->is_active) {
            return false;
        }

        $progress = UserTourProgress::where('user_id', $user->id)->where('tour_id', $tour->id)->first();

        if (!$progress) {
            return true;
        }

        if ($progress->dont_show_again) {
            return false;
        }

        if ($progress->completed_at && $progress->tour_version_seen >= $tour->version) {
            return false;
        }

        return true;
    }

    public function start(User $user, Tour $tour): UserTourProgress
    {
        $progress = UserTourProgress::updateOrCreate(
            ['user_id' => $user->id, 'tour_id' => $tour->id],
            ['current_step' => 0, 'tour_version_seen' => $tour->version, 'completed_at' => null, 'dismissed_at' => null],
        );

        $this->analytics->track('tour_started', $user, $user->church, ['tour' => $tour->slug, 'version' => $tour->version]);

        return $progress;
    }

    public function next(User $user, Tour $tour): UserTourProgress
    {
        $progress = $this->requireProgress($user, $tour);
        $totalSteps = $tour->steps()->count();

        if ($progress->current_step + 1 >= $totalSteps) {
            return $this->finish($user, $tour);
        }

        $progress->update(['current_step' => $progress->current_step + 1]);

        return $progress;
    }

    public function previous(User $user, Tour $tour): UserTourProgress
    {
        $progress = $this->requireProgress($user, $tour);
        $progress->update(['current_step' => max($progress->current_step - 1, 0)]);

        return $progress;
    }

    public function finish(User $user, Tour $tour): UserTourProgress
    {
        $progress = $this->requireProgress($user, $tour);
        $progress->update(['completed_at' => now(), 'tour_version_seen' => $tour->version]);

        $this->analytics->track('tour_completed', $user, $user->church, ['tour' => $tour->slug, 'version' => $tour->version]);

        return $progress;
    }

    public function skip(User $user, Tour $tour): UserTourProgress
    {
        $progress = $this->requireProgress($user, $tour);
        $progress->update(['dismissed_at' => now()]);

        $this->analytics->track('tour_skipped', $user, $user->church, ['tour' => $tour->slug, 'at_step' => $progress->current_step]);

        return $progress;
    }

    /** §31: "Don't show again" — unlike skip(), this one survives a version bump too. */
    public function dismissPermanently(User $user, Tour $tour): UserTourProgress
    {
        $progress = $this->requireProgress($user, $tour);
        $progress->update(['dismissed_at' => now(), 'dont_show_again' => true]);

        return $progress;
    }

    public function restart(User $user, Tour $tour): UserTourProgress
    {
        return $this->start($user, $tour); // same operation — starting over IS a fresh start
    }

    private function requireProgress(User $user, Tour $tour): UserTourProgress
    {
        return UserTourProgress::firstOrCreate(
            ['user_id' => $user->id, 'tour_id' => $tour->id],
            ['tour_version_seen' => $tour->version],
        );
    }
}

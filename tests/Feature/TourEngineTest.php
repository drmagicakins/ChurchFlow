<?php

namespace Tests\Feature;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Onboarding\Services\TourEngineService;
use App\Models\Church;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourEngineTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TourEngineService
    {
        return new TourEngineService(new AnalyticsService());
    }

    private function tourWithSteps(int $stepCount = 3): Tour
    {
        $tour = Tour::factory()->create();
        for ($i = 1; $i <= $stepCount; $i++) {
            $tour->steps()->create(['title' => "Step {$i}", 'sort_order' => $i]);
        }

        return $tour;
    }

    public function test_a_tour_never_seen_before_should_show(): void
    {
        $user = User::factory()->create();
        $tour = $this->tourWithSteps();

        $this->assertTrue($this->service()->shouldShow($user, $tour));
    }

    public function test_next_advances_through_every_step_then_finishes_on_the_last_one(): void
    {
        $user = User::factory()->create();
        $tour = $this->tourWithSteps(3);
        $service = $this->service();

        $service->start($user, $tour);
        $p1 = $service->next($user, $tour);
        $this->assertSame(1, $p1->current_step);
        $this->assertNull($p1->completed_at);

        $p2 = $service->next($user, $tour);
        $this->assertSame(2, $p2->current_step);

        // Advancing past the last step (index 2 of 3) finishes the tour.
        $p3 = $service->next($user, $tour);
        $this->assertNotNull($p3->completed_at);
    }

    public function test_a_completed_tour_at_the_current_version_should_not_show_again(): void
    {
        $user = User::factory()->create();
        $tour = $this->tourWithSteps();
        $service = $this->service();

        $service->start($user, $tour);
        $service->finish($user, $tour);

        $this->assertFalse($service->shouldShow($user, $tour));
    }

    public function test_bumping_the_tour_version_makes_a_completed_tour_eligible_again(): void
    {
        $user = User::factory()->create();
        $tour = $this->tourWithSteps();
        $service = $this->service();

        $service->start($user, $tour);
        $service->finish($user, $tour);
        $this->assertFalse($service->shouldShow($user, $tour));

        $tour->update(['version' => 2]);

        $this->assertTrue($service->shouldShow($user->fresh(), $tour->fresh()), 'A version bump must make a previously-completed tour eligible again.');
    }

    public function test_dont_show_again_survives_a_version_bump_unlike_a_plain_skip(): void
    {
        $user = User::factory()->create();
        $tour = $this->tourWithSteps();
        $service = $this->service();

        $service->start($user, $tour);
        $service->dismissPermanently($user, $tour);
        $tour->update(['version' => 2]);

        $this->assertFalse($service->shouldShow($user->fresh(), $tour->fresh()), '"Don\'t show again" must survive a version bump.');
    }

    public function test_a_plain_skip_still_shows_again_on_next_check_unlike_dont_show_again(): void
    {
        $user = User::factory()->create();
        $tour = $this->tourWithSteps();
        $service = $this->service();

        $service->start($user, $tour);
        $service->skip($user, $tour);

        // A plain skip is a dismissal for this session, not a permanent
        // "never again" — shouldShow() only hard-blocks on dont_show_again
        // or a completed current version, neither of which applies here.
        $this->assertTrue($service->shouldShow($user->fresh(), $tour->fresh()));
    }

    public function test_an_inactive_tour_never_shows(): void
    {
        $user = User::factory()->create();
        $tour = $this->tourWithSteps();
        $tour->update(['is_active' => false]);

        $this->assertFalse($this->service()->shouldShow($user, $tour));
    }

    public function test_restarting_resets_progress_to_the_first_step(): void
    {
        $user = User::factory()->create();
        $tour = $this->tourWithSteps(3);
        $service = $this->service();

        $service->start($user, $tour);
        $service->next($user, $tour);
        $service->finish($user, $tour);

        $restarted = $service->restart($user, $tour);

        $this->assertSame(0, $restarted->current_step);
        $this->assertNull($restarted->completed_at);
    }

    public function test_tour_progress_is_personal_and_does_not_leak_between_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $tour = $this->tourWithSteps();
        $service = $this->service();

        $service->start($userA, $tour);
        $service->finish($userA, $tour);

        $this->assertFalse($service->shouldShow($userA, $tour));
        $this->assertTrue($service->shouldShow($userB, $tour));
    }
}

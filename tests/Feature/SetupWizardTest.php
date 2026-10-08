<?php

namespace Tests\Feature;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Onboarding\Services\SetupWizardService;
use App\Models\AnalyticsEvent;
use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SetupWizardService
    {
        return new SetupWizardService(new AnalyticsService());
    }

    public function test_percent_complete_reflects_how_many_steps_are_done(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);
        $service = $this->service();

        $this->assertSame(0, $service->percentComplete($church));

        $totalSteps = count($service->steps());
        $service->markStepComplete($church, 'church_profile', $user);

        $this->assertSame((int) round(100 / $totalSteps), $service->percentComplete($church));
    }

    public function test_marking_every_step_complete_fires_onboarding_completed_exactly_once(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);
        $service = $this->service();

        $steps = array_column($service->steps(), 'key');

        foreach ($steps as $i => $key) {
            $service->markStepComplete($church, $key, $user);
        }

        $this->assertTrue($service->isComplete($church));

        $completedEvents = AnalyticsEvent::where('event_name', 'onboarding_completed')
            ->where('church_id', $church->id)->count();
        $this->assertSame(1, $completedEvents, 'onboarding_completed must fire exactly once, on the step that completes the checklist.');

        // Re-marking an already-complete step must not fire it again.
        $service->markStepComplete($church, $steps[0], $user);
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'onboarding_completed')->where('church_id', $church->id)->count());
    }

    public function test_marking_an_unknown_step_is_rejected(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service()->markStepComplete($church, 'not-a-real-step', $user);
    }

    public function test_skipping_a_step_logs_an_event_but_does_not_mark_it_complete(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);
        $service = $this->service();

        $service->skipStep($church, 'members', $user);

        $this->assertFalse(collect($service->progressFor($church))->firstWhere('key', 'members')['completed']);
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'setup_step_skipped']);
    }

    public function test_a_church_cannot_see_another_churchs_setup_progress(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        $userA = User::factory()->create(['church_id' => $churchA->id]);
        $service = $this->service();

        $service->markStepComplete($churchA, 'church_profile', $userA);

        $this->assertSame(0, $service->percentComplete($churchB));
    }
}

<?php

namespace Tests\Feature;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Onboarding\Listeners\TrackOnboardingStarted;
use App\Domains\Subscriptions\Events\ChurchActivated;
use App\Models\AnalyticsEvent;
use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_an_event_records_church_and_user_context(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);

        (new AnalyticsService())->track('feature_used', $user, $church, ['feature' => 'members']);

        $event = AnalyticsEvent::first();
        $this->assertSame('feature_used', $event->event_name);
        $this->assertSame($church->id, $event->church_id);
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('members', $event->properties['feature']);
    }

    public function test_church_activated_fires_onboarding_started(): void
    {
        $church = Church::factory()->create();

        (new TrackOnboardingStarted(new AnalyticsService()))->handle(new ChurchActivated($church));

        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'onboarding_started',
            'church_id' => $church->id,
        ]);
    }

    public function test_the_client_tracking_endpoint_only_accepts_allow_listed_event_names(): void
    {
        $church = Church::factory()->create(['status' => 'active']);
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user)
            ->postJson(route('analytics.track'), ['event' => 'feature_discovered', 'feature' => 'budgeting'])
            ->assertOk();

        $this->assertDatabaseHas('analytics_events', ['event_name' => 'feature_discovered']);

        $this->actingAs($user)
            ->postJson(route('analytics.track'), ['event' => 'anything_i_want'])
            ->assertStatus(422);
    }
}

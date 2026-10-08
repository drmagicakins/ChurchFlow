<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_is_publicly_reachable_without_authentication(): void
    {
        $this->get('/')->assertOk()->assertSee('ChurchFlow');
    }

    public function test_it_renders_live_plan_data_not_hardcoded_prices(): void
    {
        Plan::factory()->create([
            'name' => 'Trailblazer', 'slug' => 'trailblazer', 'monthly_price' => 44400,
            'is_active' => true, 'sort_order' => 1, 'max_members' => 77,
        ]);

        $response = $this->get('/');

        $response->assertSee('Trailblazer');
        $response->assertSee('44,400');
        $response->assertSee('77');
    }

    public function test_inactive_plans_are_never_shown(): void
    {
        Plan::factory()->create(['name' => 'Retired Plan', 'is_active' => false]);

        $this->get('/')->assertDontSee('Retired Plan');
    }

    public function test_an_empty_plan_catalog_shows_a_graceful_fallback_instead_of_an_empty_grid(): void
    {
        $this->get('/')->assertSee('being finalized');
    }

    public function test_a_guest_choosing_a_plan_is_sent_to_register(): void
    {
        $plan = Plan::factory()->create(['is_active' => true]);

        $response = $this->get('/');

        $response->assertSee(route('register'), false);
    }

    public function test_a_signed_in_user_with_no_church_yet_is_sent_straight_to_checkout_for_that_plan(): void
    {
        $plan = Plan::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['church_id' => null]);

        $response = $this->actingAs($user)->get('/');

        $response->assertSee(route('checkout.review', $plan), false);
    }

    public function test_a_signed_in_user_who_already_has_a_church_still_gets_a_register_link_not_a_checkout_loop(): void
    {
        $church = Church::factory()->create();
        $plan = Plan::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['church_id' => $church->id]);

        $response = $this->actingAs($user)->get('/');

        // Someone who already has a church shouldn't be funneled into a
        // new checkout for a second one via the landing page.
        $response->assertDontSee(route('checkout.review', $plan), false);
    }

    public function test_the_billing_toggle_only_renders_when_a_plan_actually_has_a_yearly_price(): void
    {
        Plan::factory()->create(['is_active' => true, 'yearly_price' => null]);
        $this->get('/')->assertDontSee('id="toggle-yearly"', false);

        Plan::query()->delete();
        Plan::factory()->create(['is_active' => true, 'yearly_price' => 100000]);
        $this->get('/')->assertSee('id="toggle-yearly"', false);
    }
}

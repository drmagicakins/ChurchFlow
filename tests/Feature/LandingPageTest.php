<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public landing page.
 *
 * PHASE 12 CHANGED WHAT THIS PAGE READS. It used to iterate
 * `Plan::where('is_active', true)` directly, which meant the four advertised
 * tiers and the purchasable plans were the same list — so Enterprise, which
 * has no self-serve price, could not be shown at all. It now reads
 * PlanCatalog, which lists every marketing tier (Starter, Growth,
 * Denomination, Enterprise) and resolves each one's price from the `plans`
 * row when one exists, falling back to config('billing.plans').
 *
 * These tests assert that contract rather than the old one: the DATABASE
 * still wins for price (that is what checkout charges), the CONFIG supplies
 * the tier list, and a tier with no purchasable row is advertised but not
 * sold.
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_is_publicly_reachable_without_authentication(): void
    {
        $this->get('/')->assertOk()->assertSee('ChurchFlow');
    }

    public function test_it_shows_all_four_tiers_with_prices_stated(): void
    {
        $this->seed(PlanSeeder::class);

        $response = $this->get('/');

        // Every advertised tier is present...
        $response->assertSee('Starter');
        $response->assertSee('Growth');
        $response->assertSee('Denomination');
        $response->assertSee('Enterprise');

        // ...and each one states a price, in Naira, rather than saying
        // "Price on request" while the checkout charges a real amount.
        $response->assertSee('₦10,000');   // Starter
        $response->assertSee('₦25,000');   // Growth
        $response->assertSee('₦60,000');   // Denomination

        // Enterprise is quoted individually, so it says Custom — not a number,
        // and specifically not ₦0.
        $response->assertSee('Custom');
        $response->assertDontSee('Price on request');
    }

    public function test_a_price_edited_in_the_database_wins_over_the_config_default(): void
    {
        $this->seed(PlanSeeder::class);

        // A platform admin changing a price at runtime must change what the
        // marketing page shows. The database is what checkout charges, so a
        // page showing the config default here would quote one number and
        // bill another.
        Plan::where('slug', 'growth')->update(['monthly_price' => 44400]);

        $response = $this->get('/');

        $response->assertSee('₦44,400');
        $response->assertDontSee('₦25,000');
    }

    public function test_annual_prices_are_stated_when_a_tier_has_them(): void
    {
        $this->seed(PlanSeeder::class);

        $response = $this->get('/');

        $response->assertSee('₦100,000');  // Starter yearly
        $response->assertSee('Two months free on annual billing');
    }

    public function test_enterprise_is_advertised_but_not_self_serve_purchasable(): void
    {
        $this->seed(PlanSeeder::class);

        $enterprise = Plan::where('slug', 'enterprise')->first();

        // It exists as a row so the pricing page can resolve it...
        $this->assertNotNull($enterprise);
        // ...but it is inactive, so it never appears in a checkout plan picker.
        $this->assertFalse((bool) $enterprise->is_active);
        $this->assertNull($enterprise->monthly_price);
        $this->assertFalse($enterprise->isSelfServe());
        $this->assertSame('Custom', $enterprise->displayPrice('monthly'));

        // And the landing page sends it to a conversation, not a payment page.
        $this->get('/')->assertSee(route('contact'), false);
    }

    public function test_a_guest_choosing_a_plan_is_sent_to_register(): void
    {
        $this->seed(PlanSeeder::class);

        $this->get('/')->assertSee(route('register'), false);
    }

    public function test_a_signed_in_user_with_no_church_yet_is_sent_straight_to_checkout_for_that_plan(): void
    {
        $this->seed(PlanSeeder::class);

        $plan = Plan::where('slug', 'growth')->firstOrFail();
        $user = User::factory()->create(['church_id' => null]);

        $this->actingAs($user)->get('/')->assertSee(route('checkout.review', $plan), false);
    }

    public function test_a_signed_in_user_who_already_has_a_church_still_gets_a_register_link_not_a_checkout_loop(): void
    {
        $this->seed(PlanSeeder::class);

        $church = Church::factory()->create();
        $plan = Plan::where('slug', 'growth')->firstOrFail();
        $user = User::factory()->create(['church_id' => $church->id]);

        // Someone who already has a church shouldn't be funneled into a new
        // checkout for a second one via the landing page.
        $this->actingAs($user)->get('/')->assertDontSee(route('checkout.review', $plan), false);
    }

    public function test_the_billing_toggle_only_renders_when_a_plan_actually_has_a_yearly_price(): void
    {
        // Denomination and Growth ship with yearly prices, so the toggle shows.
        $this->seed(PlanSeeder::class);
        $this->get('/')->assertSee('id="toggle-yearly"', false);

        // With every yearly price removed, a dead toggle must not render.
        Plan::query()->update(['yearly_price' => null]);
        $this->get('/')->assertDontSee('id="toggle-yearly"', false);
    }

    public function test_the_trial_promise_is_stated_on_the_landing_page(): void
    {
        $this->seed(PlanSeeder::class);

        $this->get('/')->assertSee('14-day trial');
    }
}

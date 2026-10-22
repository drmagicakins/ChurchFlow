<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration smoke test for the Phase 12 merge:
 *
 *  - prices resolve on the public plans page and the landing page (they had
 *    gone blank because the `plans` table was empty and PlanCatalog only read
 *    the database);
 *  - every support screen renders for the role that owns it, so a missing
 *    variable in a view is caught here rather than in a browser.
 */
class IntegrationSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed the plan catalogue the same way a fresh install does.
        $this->seed(\Database\Seeders\PlanSeeder::class);
    }

    public function test_the_public_plans_page_shows_prices(): void
    {
        $response = $this->get(route('plans.index'));

        $response->assertOk();
        $response->assertSee('₦10,000');   // Starter
        $response->assertSee('₦25,000');   // Growth
        $response->assertSee('₦60,000');   // Denomination
    }

    public function test_the_landing_page_shows_prices(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('₦10,000');
        $response->assertSee('₦25,000');
    }

    public function test_the_plan_catalog_states_a_price_for_every_listed_tier(): void
    {
        $catalog = app(\App\Domains\Subscriptions\Services\PlanCatalog::class)->forDisplay();

        $this->assertNotEmpty($catalog);

        foreach ($catalog as $tier) {
            // "Custom" is only acceptable for a tier with no monthly price
            // (Enterprise) — every other tier must carry a real figure.
            if ($tier['price'] === 'Custom') {
                $this->assertSame('enterprise', $tier['key'], "Only Enterprise may be quoted as Custom, got {$tier['key']}.");
                continue;
            }

            $this->assertMatchesRegularExpression('/^₦[\d,]+$/', $tier['price'], "Plan {$tier['key']} has no resolved price.");
        }
    }

    public function test_a_church_user_can_open_the_support_screens(): void
    {
        $church = Church::factory()->create(['status' => 'active']);
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user)->get(route('support.index'))->assertOk()->assertSee('Open a ticket');

        $ticket = SupportTicket::factory()->for($church, 'church')->create(['created_by' => $user->id]);

        $this->actingAs($user)->get(route('support.show', $ticket))->assertOk()->assertSee($ticket->subject);
    }

    public function test_a_church_user_can_submit_a_support_ticket(): void
    {
        $church = Church::factory()->create(['status' => 'active']);
        $user = User::factory()->create(['church_id' => $church->id]);

        $response = $this->actingAs($user)->post(route('support.store'), [
            'subject' => 'Export is empty',
            'category' => 'technical',
            'description' => 'The members CSV downloads with no rows.',
            'priority' => 'high',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('support_tickets', [
            'church_id' => $church->id,
            'created_by' => $user->id,
            'subject' => 'Export is empty',
            'status' => 'open',
        ]);
    }

    /**
     * Every layout that renders a form must also render the submit-loader
     * overlay, or that form submits with no busy state and invites a
     * double-click. This asserts the overlay markup is present on the guest,
     * marketing and authenticated shells — a layout that silently drops the
     * `@include('partials.loader')` would otherwise only be noticed in a
     * browser.
     */
    public function test_every_form_bearing_layout_renders_the_submit_loader(): void
    {
        // Guest (auth) shell.
        $this->get(route('register'))->assertOk()->assertSee('cf-loader-overlay', false);
        $this->get(route('login'))->assertOk()->assertSee('cf-loader-overlay', false);

        // Marketing shell (the public plans page).
        $this->get(route('plans.index'))->assertOk()->assertSee('cf-loader-overlay', false);

        // Authenticated app shell.
        $church = Church::factory()->create(['status' => 'active']);
        $user = User::factory()->create(['church_id' => $church->id]);
        $this->actingAs($user)->get(route('support.index'))->assertOk()->assertSee('cf-loader-overlay', false);
    }

    public function test_a_platform_admin_can_open_the_support_queue(): void
    {
        $church = Church::factory()->create();
        $admin = User::factory()->create(['church_id' => null, 'is_platform_admin' => true]);
        SupportTicket::factory()->for($church, 'church')->create(['subject' => 'Cannot log in']);

        $this->actingAs($admin)->get(route('platform-admin.support.index'))
            ->assertOk()
            ->assertSee('Cannot log in');

        $this->actingAs($admin)->get(route('platform-admin.dashboard'))
            ->assertOk()
            ->assertSee('open support ticket');
    }

    /**
     * Route-model binding for a TENANT-OWNED model on a platform-admin route.
     *
     * This is the regression guard for a real 404: SupportTicket uses
     * BelongsToTenant, and its global scope reads 'tenant.disabled' — which
     * PlatformAdminOnly sets. If PlatformAdminOnly is not ordered before
     * SubstituteBindings, the {ticket} binding runs while the tenant scope is
     * still active, no tenant is bound, the scope emits `where 1 = 0`, and the
     * detail page 404s even though the ticket is right there in the queue.
     */
    public function test_a_platform_admin_can_open_a_ticket_detail_page(): void
    {
        $church = Church::factory()->create();
        $admin = User::factory()->create(['church_id' => null, 'is_platform_admin' => true]);
        $ticket = SupportTicket::factory()->for($church, 'church')->create(['subject' => 'Detail page must resolve']);

        $this->actingAs($admin)
            ->get(route('platform-admin.support.show', $ticket))
            ->assertOk()
            ->assertSee('Detail page must resolve');
    }
}

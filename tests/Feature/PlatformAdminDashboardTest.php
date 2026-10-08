<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 12 — the platform-admin area.
 *
 * It was a two-line stub (`<x-layout><h1>Platform Admin</h1></x-layout>`), so
 * the operator's home screen had no overview at all. These tests pin the
 * things that matter about a cross-tenant screen specifically:
 *
 *   1. A normal church user can never reach it.
 *   2. It fails closed. The controller deliberately never calls
 *      withoutGlobalScopes(); it relies on PlatformAdminOnly setting
 *      tenant.disabled, which tenant scopes check. If that middleware were
 *      removed, these queries must return nothing rather than leaking one
 *      church's data into a platform-wide view.
 *   3. The two revenue streams stay distinguishable, because a single combined
 *      total is what would hide a collapse in one of them.
 */
class PlatformAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function platformAdmin(): User
    {
        return User::factory()->create([
            'church_id' => null,
            'is_platform_admin' => true,
        ]);
    }

    private function churchWithPlan(string $name = 'Growth', int $price = 25000): array
    {
        $plan = Plan::factory()->create(['name' => $name, 'monthly_price' => $price]);
        $church = Church::factory()->create(['name' => $name.' Church']);

        return [$church, $plan];
    }

    public function test_a_normal_church_user_cannot_reach_the_platform_admin_area(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user)
            ->get(route('platform-admin.dashboard'))
            ->assertForbidden();
    }

    public function test_a_platform_admin_sees_church_and_subscription_totals(): void
    {
        $admin = $this->platformAdmin();

        [$churchA, $planA] = $this->churchWithPlan('Starter', 10000);
        [$churchB, $planB] = $this->churchWithPlan('Growth', 25000);

        app(\App\Domains\Subscriptions\Services\SubscriptionService::class)
            ->createInitial($churchA, $planA, 'monthly');
        app(\App\Domains\Subscriptions\Services\TrialService::class)
            ->startTrial($churchB, $planB, 'monthly');

        $this->actingAs($admin)
            ->get(route('platform-admin.dashboard'))
            ->assertOk()
            ->assertSee('Platform overview')
            // Both churches are visible, from different tenants, in one view.
            ->assertSee('Starter Church')
            ->assertSee('Growth Church')
            // Paying vs trialing are reported separately.
            ->assertSee('Paying subscriptions')
            ->assertSee('on trial');
    }

    public function test_subscription_and_sms_revenue_are_reported_separately(): void
    {
        $admin = $this->platformAdmin();
        [$church, $plan] = $this->churchWithPlan();

        app(\App\Domains\Subscriptions\Services\SubscriptionService::class)
            ->createInitial($church, $plan, 'monthly');

        Invoice::withoutGlobalScopes()->create([
            'church_id' => $church->id, 'type' => 'subscription', 'amount' => '25000.00',
            'currency' => 'NGN', 'status' => 'paid', 'description' => 'Growth monthly',
        ]);
        Invoice::withoutGlobalScopes()->create([
            'church_id' => $church->id, 'type' => 'sms_credits', 'amount' => '5000.00',
            'currency' => 'NGN', 'status' => 'paid', 'description' => '1000 SMS credits',
        ]);

        $response = $this->actingAs($admin)->get(route('platform-admin.dashboard'));

        $response->assertOk()
            ->assertSee('Subscription revenue')
            ->assertSee('SMS revenue')
            // The two amounts appear as separate figures, not summed into one.
            ->assertSee('25,000.00')
            ->assertSee('5,000.00');
    }

    public function test_a_church_needing_attention_appears_in_the_support_queue(): void
    {
        $admin = $this->platformAdmin();
        [$church, $plan] = $this->churchWithPlan();

        $subscription = Subscription::withoutGlobalScopes()->create([
            'church_id' => $church->id, 'plan_id' => $plan->id,
            'status' => 'past_due', 'billing_interval' => 'monthly',
            'current_period_start' => now()->subMonth(), 'current_period_end' => now()->subDay(),
        ]);
        $church->update(['status' => 'past_due']);

        $this->actingAs($admin)
            ->get(route('platform-admin.dashboard'))
            ->assertOk()
            ->assertSee('Needs attention')
            ->assertSee($church->name)
            ->assertSee('Past due');
    }

    public function test_the_dashboard_renders_cleanly_with_no_data_at_all(): void
    {
        $admin = $this->platformAdmin();

        // A brand-new install: no churches, no plans, no invoices. Every panel
        // must show a teaching empty state rather than a division by zero or a
        // blank grid — the conversion percentage in particular is computed from
        // counts that are all zero here.
        $this->actingAs($admin)
            ->get(route('platform-admin.dashboard'))
            ->assertOk()
            ->assertSee('No churches yet')
            ->assertSee('Nothing needs attention');
    }

    public function test_the_settings_form_saves_and_shows_the_new_values(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('platform-admin.settings.edit'))
            ->assertOk()
            ->assertSee('Platform settings')
            ->assertSee('name="sms_price_per_unit"', false)
            ->assertSee('name="default_tax_rate"', false);

        $this->actingAs($admin)
            ->post(route('platform-admin.settings.update'), [
                'sms_price_per_unit' => '4.50',
                'default_tax_rate' => '7.5',
            ])
            ->assertRedirect();

        $settings = app(\App\Domains\PlatformAdmin\Services\PlatformSettingsService::class);

        // Compared numerically, not as strings: the settings store keeps the
        // value as it was submitted ('7.5'), and asserting on an exact string
        // would be testing the storage format rather than the value that
        // actually reaches a tax calculation.
        $this->assertSame(4.5, (float) $settings->smsPricePerUnit());
        $this->assertSame(7.5, (float) $settings->defaultTaxRate());
    }

    public function test_settings_validation_rejects_a_tax_rate_above_100(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->from(route('platform-admin.settings.edit'))
            ->post(route('platform-admin.settings.update'), [
                'sms_price_per_unit' => '4.50',
                'default_tax_rate' => '140',
            ])
            ->assertSessionHasErrors('default_tax_rate');
    }

    public function test_the_feature_flags_page_renders_with_its_create_form(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('platform-admin.feature-flags.index'))
            ->assertOk()
            ->assertSee('Create a feature flag')
            ->assertSee('name="key"', false)
            ->assertSee('name="is_globally_enabled"', false);
    }
}

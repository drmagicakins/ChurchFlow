<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_normal_church_user_cannot_reach_the_platform_admin_billing_screen(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user)->get(route('platform-admin.billing.index'))->assertForbidden();
    }

    public function test_a_platform_admin_sees_revenue_and_churches_across_every_tenant(): void
    {
        $admin = User::factory()->create(['church_id' => null, 'is_platform_admin' => true]);

        $churchA = Church::factory()->create(['name' => 'Church A']);
        $churchB = Church::factory()->create(['name' => 'Church B']);
        $plan = Plan::factory()->create();

        Subscription::create([
            'church_id' => $churchA->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_interval' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
        Subscription::create([
            'church_id' => $churchB->id, 'plan_id' => $plan->id, 'status' => 'past_due',
            'billing_interval' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        Invoice::create(['church_id' => $churchA->id, 'type' => 'subscription', 'amount' => '10000.00', 'status' => 'paid', 'provider_reference' => 'a1']);
        Invoice::create(['church_id' => $churchB->id, 'type' => 'sms_credits', 'amount' => '2000.00', 'status' => 'paid', 'provider_reference' => 'b1']);

        $response = $this->actingAs($admin)->get(route('platform-admin.billing.index'));

        $response->assertOk();
        $response->assertSee('Church A');
        $response->assertSee('Church B');
    }

    public function test_platform_admin_can_suspend_and_reactivate_a_church(): void
    {
        $admin = User::factory()->create(['church_id' => null, 'is_platform_admin' => true]);
        $church = Church::factory()->create(['status' => 'active']);
        $plan = Plan::factory()->create();
        Subscription::create([
            'church_id' => $church->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_interval' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        $this->actingAs($admin)->post(route('platform-admin.billing.suspend', $church))->assertRedirect();
        $this->assertSame('suspended', $church->fresh()->status);

        $this->actingAs($admin)->post(route('platform-admin.billing.reactivate', $church))->assertRedirect();
        $this->assertSame('active', $church->fresh()->status);
    }

    public function test_platform_admin_settings_control_sms_pricing_used_at_checkout(): void
    {
        $admin = User::factory()->create(['church_id' => null, 'is_platform_admin' => true]);

        $this->actingAs($admin)->post(route('platform-admin.settings.update'), [
            'sms_price_per_unit' => '5.50',
            'default_tax_rate' => '7.5',
        ])->assertRedirect();

        $settings = app(\App\Domains\PlatformAdmin\Services\PlatformSettingsService::class);
        $this->assertSame('5.50', $settings->smsPricePerUnit());
        $this->assertSame('7.5', $settings->defaultTaxRate());
    }
}

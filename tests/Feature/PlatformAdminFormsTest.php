<?php

namespace Tests\Feature;

use App\Domains\Subscriptions\Services\SubscriptionService;
use App\Models\Church;
use App\Models\FeatureFlag;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The platform-admin SCREENS and their forms.
 *
 * The 403 fix (IdentifyTenant redirecting a platform admin rather than
 * aborting) makes these routes reachable; this proves the forms on them
 * actually work — a form whose field names disagree with its controller's
 * validation rules is a bug no route test would catch.
 */
class PlatformAdminFormsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['church_id' => null, 'is_platform_admin' => true]);
    }

    private function churchWithSubscription(): array
    {
        $church = Church::factory()->create(['status' => 'active']);
        $plan = Plan::create([
            'name' => 'Growth', 'slug' => 'growth', 'monthly_price' => 25000,
            'yearly_price' => 250000, 'currency' => 'NGN',
            'max_members' => null, 'max_branches' => null, 'max_admins' => null, 'storage_mb' => 10240,
        ]);
        app(SubscriptionService::class)->createInitial($church, $plan, 'monthly');

        return [$church, Subscription::withoutGlobalScopes()->where('church_id', $church->id)->first()];
    }

    public function test_the_platform_admin_overview_renders(): void
    {
        $this->actingAs($this->admin())
            ->get(route('platform-admin.dashboard'))
            ->assertOk()
            ->assertSee('Platform overview');
    }

    public function test_the_billing_overview_renders(): void
    {
        $this->churchWithSubscription();

        $this->actingAs($this->admin())
            ->get(route('platform-admin.billing.index'))
            ->assertOk()
            ->assertSee('Billing');
    }

    public function test_the_church_billing_detail_renders_with_its_subscription(): void
    {
        [$church] = $this->churchWithSubscription();

        $this->actingAs($this->admin())
            ->get(route('platform-admin.billing.church', $church))
            ->assertOk()
            ->assertSee($church->name)
            ->assertSee('Growth');
    }

    public function test_a_platform_admin_can_suspend_and_reactivate_a_church(): void
    {
        [$church] = $this->churchWithSubscription();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('platform-admin.billing.suspend', $church))
            ->assertRedirect();

        $this->assertSame('suspended', $church->fresh()->status);

        $this->actingAs($admin)
            ->post(route('platform-admin.billing.reactivate', $church))
            ->assertRedirect();

        $this->assertNotSame('suspended', $church->fresh()->status);
    }

    public function test_creating_a_feature_flag_through_the_form_uses_the_label_field(): void
    {
        // The view used to post name="name", which the controller validates as
        // 'label' — so the form failed with "label is required" for any input.
        $this->actingAs($this->admin())
            ->post(route('platform-admin.feature-flags.store'), [
                'key' => 'advanced_reporting',
                'label' => 'Advanced reporting',
                'description' => 'Extra report types.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('feature_flags', [
            'key' => 'advanced_reporting',
            'label' => 'Advanced reporting',
        ]);
    }

    public function test_toggling_a_flag_globally_works(): void
    {
        $admin = $this->admin();
        $flag = FeatureFlag::create([
            'key' => 'beta_dashboard', 'label' => 'Beta dashboard',
            'is_globally_enabled' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('platform-admin.feature-flags.toggle', $flag))
            ->assertRedirect();

        $this->assertTrue((bool) $flag->fresh()->is_globally_enabled);
    }

    public function test_setting_a_per_church_override_works(): void
    {
        [$church] = $this->churchWithSubscription();
        $flag = FeatureFlag::create([
            'key' => 'beta_dashboard', 'label' => 'Beta dashboard',
            'is_globally_enabled' => false,
        ]);

        $this->actingAs($this->admin())
            ->post(route('platform-admin.feature-flags.override', $flag), [
                'church_id' => $church->id,
                'enabled' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('feature_flag_church', [
            'feature_flag_id' => $flag->id,
            'church_id' => $church->id,
            'is_enabled' => true,
        ]);
    }

    public function test_updating_platform_settings_works(): void
    {
        $this->actingAs($this->admin())
            ->post(route('platform-admin.settings.update'), [
                'sms_price_per_unit' => 4.5,
                'default_tax_rate' => 7.5,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('platform_settings', ['key' => 'sms_price_per_unit', 'value' => '4.5']);
    }

    public function test_every_platform_admin_form_renders_the_submit_loader(): void
    {
        [$church] = $this->churchWithSubscription();
        $admin = $this->admin();

        foreach ([
            route('platform-admin.billing.index'),
            route('platform-admin.billing.church', $church),
            route('platform-admin.feature-flags.index'),
            route('platform-admin.settings.edit'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertSee('cf-loader-overlay', false);
        }
    }
}

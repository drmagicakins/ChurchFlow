<?php

namespace Tests\Feature;

use App\Domains\Subscriptions\Services\CheckoutService;
use App\Domains\Subscriptions\Services\SubscriptionService;
use App\Models\Church;
use App\Models\Member;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function churchOnPlan(array $planAttributes = [], string $status = 'active'): array
    {
        $church = Church::factory()->create(['status' => $status]);
        $plan = Plan::factory()->create($planAttributes);
        $subscription = Subscription::create([
            'church_id' => $church->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'billing_interval' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        return [$church, $plan, $subscription];
    }

    private function userWith(Church $church, array $permissions): User
    {
        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::create(['church_id' => $church->id, 'name' => 'R'.uniqid()]);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['group' => 'x', 'label' => $name]);
            $role->permissions()->attach($permission);
        }

        $user->roles()->attach($role);

        return $user;
    }

    // ---- webhook endpoint -------------------------------------------------

    public function test_webhook_endpoint_fails_closed_when_no_secret_is_configured(): void
    {
        config(['services.payments.webhook_secret' => null]);

        $this->postJson(route('api.v1.webhooks.payments'), [
            'event' => 'payment.success', 'provider' => 'null', 'provider_event_id' => 'x', 'reference' => 'y',
        ])->assertStatus(503);
    }

    public function test_webhook_endpoint_rejects_a_wrong_secret(): void
    {
        config(['services.payments.webhook_secret' => 'right-secret']);

        $this->postJson(route('api.v1.webhooks.payments'), [
            'event' => 'payment.success', 'provider' => 'null', 'provider_event_id' => 'x', 'reference' => 'y',
        ], ['X-Webhook-Secret' => 'wrong-secret'])->assertStatus(401);
    }

    public function test_webhook_endpoint_accepts_the_right_secret_and_a_duplicate_delivery_is_still_a_200(): void
    {
        config(['services.payments.webhook_secret' => 'right-secret']);
        $payload = ['event' => 'payment.success', 'provider' => 'null', 'provider_event_id' => 'same', 'reference' => 'unknown'];

        $this->postJson(route('api.v1.webhooks.payments'), $payload, ['X-Webhook-Secret' => 'right-secret'])->assertOk();
        $this->postJson(route('api.v1.webhooks.payments'), $payload, ['X-Webhook-Secret' => 'right-secret'])->assertOk();
    }

    // ---- plan limits ------------------------------------------------------

    public function test_a_church_at_its_member_limit_cannot_add_another_member(): void
    {
        [$church] = $this->churchOnPlan(['max_members' => 2]);
        app()->instance('tenant.church_id', $church->id);

        Member::factory()->for($church, 'church')->count(2)->create();

        try {
            Member::factory()->for($church, 'church')->create();
            $this->fail('Expected the third member to be refused.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertStringContainsString('Current: 2 / 2 members', $e->getMessage());
            $this->assertStringContainsString('Upgrade your plan', $e->getMessage());
        }

        $this->assertSame(2, Member::withoutGlobalScopes()->where('church_id', $church->id)->count());
    }

    public function test_the_member_limit_also_blocks_csv_import_because_it_is_enforced_on_the_model(): void
    {
        [$church] = $this->churchOnPlan(['max_members' => 1]);
        app()->instance('tenant.church_id', $church->id);

        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "full_name\nFirst\nSecond\n");

        $result = (new \App\Domains\Members\Actions\ImportMembersFromCsv($church->id))->handle($path);
        unlink($path);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['skipped']);
    }

    public function test_an_unlimited_plan_never_blocks(): void
    {
        [$church] = $this->churchOnPlan(['max_members' => null]);
        app()->instance('tenant.church_id', $church->id);

        Member::factory()->for($church, 'church')->count(5)->create();

        $this->assertSame(5, Member::count());
    }

    public function test_the_admin_limit_blocks_adding_another_staff_user(): void
    {
        [$church] = $this->churchOnPlan(['max_admins' => 1]);

        User::factory()->create(['church_id' => $church->id]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        User::factory()->create(['church_id' => $church->id]);
    }

    public function test_the_branch_limit_blocks_extra_organizational_units(): void
    {
        [$church] = $this->churchOnPlan(['max_branches' => 1]);
        $type = \App\Models\UnitType::create(['church_id' => $church->id, 'name' => 'Branch', 'level' => 0]);

        \App\Models\OrganizationalUnit::create(['church_id' => $church->id, 'unit_type_id' => $type->id, 'name' => 'Main']);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        \App\Models\OrganizationalUnit::create(['church_id' => $church->id, 'unit_type_id' => $type->id, 'name' => 'Second']);
    }

    // ---- subscription lifecycle & access ---------------------------------

    public function test_subscription_status_changes_keep_the_church_status_cache_in_sync(): void
    {
        [$church, , $subscription] = $this->churchOnPlan();
        $service = app(SubscriptionService::class);

        $service->markPastDue($subscription);
        $this->assertSame('past_due', $church->fresh()->status);

        $service->enterGracePeriod($subscription->fresh());
        $this->assertSame('grace_period', $church->fresh()->status);

        $service->expire($subscription->fresh());
        $this->assertSame('expired', $church->fresh()->status);

        $service->reactivate($subscription->fresh());
        $this->assertSame('active', $church->fresh()->status);
    }

    public function test_an_active_subscription_cannot_be_reactivated(): void
    {
        [, , $subscription] = $this->churchOnPlan();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(SubscriptionService::class)->reactivate($subscription);
    }

    public function test_expiring_a_subscription_never_deletes_the_churchs_data(): void
    {
        [$church, , $subscription] = $this->churchOnPlan(['max_members' => null]);
        app()->instance('tenant.church_id', $church->id);
        Member::factory()->for($church, 'church')->count(3)->create();

        app(SubscriptionService::class)->expire($subscription);

        $this->assertSame(3, Member::count(), 'Expiry restricts access; it must never remove data (§35).');
    }

    public function test_an_expired_church_is_locked_out_of_the_app_but_can_still_reach_billing(): void
    {
        [$church] = $this->churchOnPlan([], 'expired');
        $user = $this->userWith($church, ['members.view', 'billing.manage']);

        $this->actingAs($user)->get(route('members.index'))->assertForbidden();
        $this->actingAs($user)->get(route('billing.show'))->assertOk();
    }

    public function test_active_past_due_and_grace_period_churches_keep_normal_access(): void
    {
        foreach (['active', 'past_due', 'grace_period'] as $status) {
            [$church] = $this->churchOnPlan([], $status);
            $user = $this->userWith($church, ['members.view']);

            $this->actingAs($user)->get(route('members.index'))->assertOk();
        }
    }

    public function test_billing_pages_require_the_billing_permission(): void
    {
        [$church] = $this->churchOnPlan();
        $plainStaff = $this->userWith($church, ['members.view']);

        $this->actingAs($plainStaff)->get(route('billing.show'))->assertForbidden();
    }

    public function test_cancellation_is_recorded_but_access_continues_until_period_end(): void
    {
        [$church, , $subscription] = $this->churchOnPlan();
        $user = $this->userWith($church, ['billing.manage']);

        $this->actingAs($user)->post(route('billing.cancel'))->assertRedirect();

        $subscription = $subscription->fresh();
        $this->assertNotNull($subscription->cancel_requested_at);
        $this->assertSame('active', $subscription->status);
        $this->assertSame('active', $church->fresh()->status);
    }

    public function test_sms_credit_purchase_is_refused_when_the_platform_has_not_configured_pricing(): void
    {
        config(['billing.sms_price_per_unit' => null]);
        [$church] = $this->churchOnPlan();
        $user = $this->userWith($church, ['billing.manage']);

        $this->actingAs($user)->post(route('billing.sms-credits'), ['package' => '1000'])->assertStatus(503);
    }

    public function test_a_church_cannot_see_another_churchs_subscription_or_invoices(): void
    {
        [$churchA] = $this->churchOnPlan();
        [$churchB] = $this->churchOnPlan();

        \App\Models\Invoice::create([
            'church_id' => $churchB->id, 'type' => 'subscription', 'amount' => '100.00', 'provider_reference' => 'b-ref',
        ]);

        app()->instance('tenant.church_id', $churchA->id);

        $this->assertCount(1, Subscription::all());
        $this->assertCount(0, \App\Models\Invoice::all());
    }
}

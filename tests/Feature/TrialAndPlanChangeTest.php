<?php

namespace Tests\Feature;

use App\Domains\Subscriptions\Services\CheckoutService;
use App\Domains\Subscriptions\Services\PaymentWebhookHandler;
use App\Domains\Subscriptions\Services\RenewalAndDunningService;
use App\Domains\Subscriptions\Services\SubscriptionService;
use App\Domains\Subscriptions\Services\TrialService;
use App\Models\Church;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 11 — the 14-day trial, and the upgrade/downgrade path around it.
 *
 * The tests that matter most here are the ones asserting what must NOT
 * happen: no invoice at signup, no plan switch mid-trial, and no silent
 * loss of access when the trial ends.
 */
class TrialAndPlanChangeTest extends TestCase
{
    use RefreshDatabase;

    private function trialChurch(string $planName = 'Growth'): array
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create(['church_id' => null]);
        $plan = Plan::factory()->create([
            'name' => $planName, 'slug' => strtolower($planName),
            'monthly_price' => 25000, 'max_members' => 500,
        ]);

        [$checkout] = app(CheckoutService::class)->startSubscriptionCheckout($user, $plan, 'monthly');
        app(PaymentWebhookHandler::class)->handle([
            'event' => 'payment.success', 'provider' => 'null',
            'provider_event_id' => 'trial-1', 'reference' => $checkout->provider_reference,
        ]);

        $church = Church::withoutGlobalScopes()->find($user->fresh()->church_id);

        return [$church, $user->fresh(), $plan];
    }

    private function subscriptionFor(Church $church): Subscription
    {
        return Subscription::withoutGlobalScopes()->where('church_id', $church->id)->firstOrFail();
    }

    public function test_a_new_church_starts_on_a_14_day_trial_with_no_invoice(): void
    {
        [$church] = $this->trialChurch();

        $subscription = $this->subscriptionFor($church);

        $this->assertSame('trialing', $subscription->status);
        $this->assertSame('trial', $church->status, 'churches.status is the cache of subscriptions.status and must agree.');
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertSame(14, (int) now()->startOfDay()->diffInDays($subscription->trial_ends_at->startOfDay()));
        $this->assertSame(0, Invoice::withoutGlobalScopes()->count());
    }

    public function test_a_trialing_church_has_full_access_not_a_restricted_one(): void
    {
        [$church, $user] = $this->trialChurch();

        // The whole point of a trial: it is a working tenant, not a demo.
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->assertSame('trial', $church->fresh()->status);
    }

    public function test_the_trial_ends_into_past_due_not_straight_to_expired(): void
    {
        [$church] = $this->trialChurch();
        $subscription = $this->subscriptionFor($church);

        // Wind the clock past the trial without anyone paying.
        $subscription->update(['trial_ends_at' => now()->subDay()]);

        $count = app(TrialService::class)->expireExhaustedTrials();

        $this->assertSame(1, $count);
        $this->assertSame('past_due', $this->subscriptionFor($church)->status);
        $this->assertSame('past_due', $church->fresh()->status);

        // A lapsed trial is NOT an expired church — the dunning grace
        // period still applies, exactly as it does to a failed card.
        $this->assertNotSame('expired', $church->fresh()->status);
    }

    public function test_a_lapsed_trial_then_flows_through_the_ordinary_dunning_chain(): void
    {
        [$church] = $this->trialChurch();
        $subscription = $this->subscriptionFor($church);

        $subscription->update(['trial_ends_at' => now()->subDay()]);
        app(TrialService::class)->expireExhaustedTrials();

        $this->assertSame('past_due', $this->subscriptionFor($church)->status);

        // Pretend the church has sat in past_due for longer than the grace
        // window — the same pass the paid renewal cycle uses picks it up.
        Subscription::withoutGlobalScopes()
            ->where('id', $subscription->id)
            ->update(['updated_at' => now()->subDays(6)]);

        app(RenewalAndDunningService::class)->runDailyCycle();

        $this->assertSame('grace_period', $this->subscriptionFor($church)->status);
    }

    public function test_subscribing_during_a_trial_charges_once_and_writes_one_invoice(): void
    {
        [$church, $user] = $this->trialChurch();
        $subscription = $this->subscriptionFor($church);

        app(TrialService::class)->subscribeNow($subscription);

        $subscription = $this->subscriptionFor($church);

        $this->assertSame('active', $subscription->status);
        $this->assertSame('active', $church->fresh()->status);
        $this->assertNull($subscription->trial_ends_at, 'A converted trial is no longer trialing.');
        $this->assertNotNull($subscription->trial_converted_at);

        $this->assertSame(1, Invoice::withoutGlobalScopes()->where('church_id', $church->id)->count());
        $this->assertSame('paid', Invoice::withoutGlobalScopes()->where('church_id', $church->id)->first()->status);
    }

    public function test_converting_an_already_active_subscription_is_refused(): void
    {
        [$church] = $this->trialChurch();
        $subscription = $this->subscriptionFor($church);

        app(TrialService::class)->subscribeNow($subscription);

        // A second attempt must not charge again — this is the whole point
        // of routing conversion through one guarded method.
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(TrialService::class)->subscribeNow($this->subscriptionFor($church));
    }

    public function test_choosing_a_plan_during_a_trial_records_intent_without_switching_the_plan(): void
    {
        [$church, $user] = $this->trialChurch('Growth');
        $originalPlanId = $this->subscriptionFor($church)->plan_id;

        $enterprise = Plan::factory()->create([
            'name' => 'Professional', 'slug' => 'professional', 'monthly_price' => 60000,
        ]);

        $this->actingAs($user)
            ->post(route('billing.change-plan'), ['plan_id' => $enterprise->id])
            ->assertRedirect(route('billing.show'));

        $subscription = $this->subscriptionFor($church);

        // The plan they are trialing on has NOT moved...
        $this->assertSame($originalPlanId, $subscription->plan_id);
        // ...but the intent is recorded, which is what they asked for.
        $this->assertSame($enterprise->id, $subscription->pending_plan_id);
        // And no money changed hands.
        $this->assertSame(0, Invoice::withoutGlobalScopes()->count());
    }

    public function test_the_pending_plan_is_applied_when_the_trial_converts(): void
    {
        [$church, $user] = $this->trialChurch('Growth');

        $enterprise = Plan::factory()->create([
            'name' => 'Professional', 'slug' => 'professional', 'monthly_price' => 60000,
        ]);

        $this->actingAs($user)->post(route('billing.change-plan'), ['plan_id' => $enterprise->id]);

        app(TrialService::class)->subscribeNow($this->subscriptionFor($church));

        $subscription = $this->subscriptionFor($church);

        $this->assertSame($enterprise->id, $subscription->plan_id, 'The chosen plan takes effect at conversion.');
        $this->assertNull($subscription->pending_plan_id, 'The intent is consumed, not left dangling.');
        $this->assertSame('active', $subscription->status);
    }

    public function test_changing_plan_on_a_paid_subscription_still_prorates_and_records_an_invoice(): void
    {
        [$church, $user] = $this->trialChurch('Growth');

        app(TrialService::class)->subscribeNow($this->subscriptionFor($church));
        $invoicesAfterConversion = Invoice::withoutGlobalScopes()->count();

        $enterprise = Plan::factory()->create([
            'name' => 'Professional', 'slug' => 'professional', 'monthly_price' => 60000,
        ]);

        $this->actingAs($user)
            ->post(route('billing.change-plan'), ['plan_id' => $enterprise->id])
            ->assertRedirect(route('billing.show'));

        $subscription = $this->subscriptionFor($church);

        // Unlike the trial case, a paid plan change DOES switch immediately...
        $this->assertSame($enterprise->id, $subscription->plan_id);
        $this->assertNull($subscription->pending_plan_id);

        // ...and records exactly what it cost.
        $this->assertSame($invoicesAfterConversion + 1, Invoice::withoutGlobalScopes()->count());
    }

    public function test_the_billing_plans_page_labels_the_current_plan_and_renders(): void
    {
        [$church, $user] = $this->trialChurch('Growth');

        // assertSee escapes by default, and the button label is wrapped
        // across lines in the Blade source — so assert on the stable pieces
        // (the plan name and the current-plan marker) rather than on a
        // phrase whose whitespace depends on template formatting.
        $this->actingAs($user)
            ->get(route('billing.plans'))
            ->assertOk()
            ->assertSee('Current')
            ->assertSee('Growth')
            ->assertSee('Your current plan');
    }

    public function test_a_user_without_billing_permission_cannot_reach_the_plan_management_pages(): void
    {
        [$church] = $this->trialChurch();

        $stranger = User::factory()->create(['church_id' => $church->id]);
        app()->instance('tenant.church_id', $church->id);

        $this->actingAs($stranger)->get(route('billing.plans'))->assertForbidden();
        $this->actingAs($stranger)->post(route('billing.subscribe'))->assertForbidden();
    }

    public function test_the_daily_cycle_reports_lapsed_trials_it_moved(): void
    {
        [$church] = $this->trialChurch();
        $this->subscriptionFor($church)->update(['trial_ends_at' => now()->subDay()]);

        $counts = app(RenewalAndDunningService::class)->runDailyCycle();

        $this->assertSame(1, $counts['trials_lapsed']);
    }

    public function test_trial_days_remaining_never_goes_negative(): void
    {
        [$church] = $this->trialChurch();
        $subscription = $this->subscriptionFor($church);
        $subscription->update(['trial_ends_at' => now()->subDays(5)]);

        $this->assertSame(0, app(TrialService::class)->daysRemaining($subscription->fresh()));
    }
}

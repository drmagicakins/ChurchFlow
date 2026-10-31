<?php

namespace Tests\Feature;

use App\Domains\Subscriptions\Gateways\PaymentGatewayInterface;
use App\Domains\Subscriptions\Gateways\PaymentVerificationResult;
use App\Domains\Subscriptions\Services\RenewalAndDunningService;
use App\Domains\Subscriptions\Services\SubscriptionService;
use App\Models\Church;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenewalAndDunningTest extends TestCase
{
    use RefreshDatabase;

    private function subscriptionDueToday(string $status = 'active'): Subscription
    {
        $church = Church::factory()->create(['status' => $status === 'active' ? 'active' : $status]);
        $plan = Plan::factory()->create(['monthly_price' => 10000]);

        return Subscription::create([
            'church_id' => $church->id, 'plan_id' => $plan->id, 'status' => $status,
            'billing_interval' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(), // already due
        ]);
    }

    /**
     * Backdates `updated_at` — the dunning timer's proxy for "when did this
     * subscription enter its current status".
     *
     * `update(['updated_at' => ...])` does NOT work here: Eloquent overwrites
     * the timestamp with the current time during an ordinary save, so the row
     * always came back as "just now" and the >GRACE_PERIOD_DAYS query never
     * matched. Disabling timestamps and using forceFill is the same pattern
     * DemoDataSeeder already uses to backdate rows.
     */
    private function backdate(Subscription $subscription, \Illuminate\Support\Carbon $when): void
    {
        $subscription->timestamps = false;
        $subscription->forceFill(['updated_at' => $when])->save();
        $subscription->timestamps = true;
    }

    private function alwaysSucceedsGateway(): PaymentGatewayInterface
    {
        return new class implements PaymentGatewayInterface {
            public function initiate($checkout): string { return 'x'; }
            public function verify(string $ref): PaymentVerificationResult { return new PaymentVerificationResult(true, $ref); }
            public function chargeRecurring($subscription): PaymentVerificationResult
            {
                return new PaymentVerificationResult(true, 'charge-ref');
            }
        };
    }

    private function alwaysFailsGateway(): PaymentGatewayInterface
    {
        return new class implements PaymentGatewayInterface {
            public function initiate($checkout): string { return 'x'; }
            public function verify(string $ref): PaymentVerificationResult { return new PaymentVerificationResult(false, $ref); }
            public function chargeRecurring($subscription): PaymentVerificationResult
            {
                return new PaymentVerificationResult(false, 'n/a', 'Card declined');
            }
        };
    }

    public function test_a_successful_renewal_charge_extends_the_period_and_creates_an_invoice(): void
    {
        $subscription = $this->subscriptionDueToday();
        $oldPeriodEnd = $subscription->current_period_end;

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysSucceedsGateway(), new \App\Domains\Subscriptions\Services\TrialService(new SubscriptionService(), $this->alwaysSucceedsGateway()));
        $counts = $service->runDailyCycle();

        $subscription = $subscription->fresh();
        $this->assertSame(1, $counts['renewed']);
        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->current_period_end->greaterThan($oldPeriodEnd));
        $this->assertSame(1, Invoice::withoutGlobalScopes()->where('church_id', $subscription->church_id)->count());
    }

    public function test_a_failed_renewal_charge_marks_the_subscription_past_due_and_creates_no_invoice(): void
    {
        $subscription = $this->subscriptionDueToday();

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysFailsGateway(), new \App\Domains\Subscriptions\Services\TrialService(new SubscriptionService(), $this->alwaysFailsGateway()));
        $counts = $service->runDailyCycle();

        $subscription = $subscription->fresh();
        $this->assertSame(1, $counts['past_due']);
        $this->assertSame('past_due', $subscription->status);
        $this->assertSame('past_due', $subscription->church->status);
        $this->assertSame(0, Invoice::withoutGlobalScopes()->where('church_id', $subscription->church_id)->count());
    }

    public function test_a_subscription_not_yet_due_is_left_alone(): void
    {
        $church = Church::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = Subscription::create([
            'church_id' => $church->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_interval' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addWeeks(2), // not due yet
        ]);

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysFailsGateway(), new \App\Domains\Subscriptions\Services\TrialService(new SubscriptionService(), $this->alwaysFailsGateway()));
        $counts = $service->runDailyCycle();

        $this->assertSame(0, $counts['past_due']);
        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_a_long_overdue_past_due_subscription_advances_to_grace_period(): void
    {
        $subscription = $this->subscriptionDueToday('past_due');
        $this->backdate($subscription, now()->subDays(6));

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysSucceedsGateway(), new \App\Domains\Subscriptions\Services\TrialService(new SubscriptionService(), $this->alwaysSucceedsGateway()));
        $counts = $service->runDailyCycle();

        $this->assertSame(1, $counts['grace_period']);
        $this->assertSame('grace_period', $subscription->fresh()->status);
    }

    public function test_a_long_overdue_grace_period_subscription_expires(): void
    {
        $subscription = $this->subscriptionDueToday('grace_period');
        $this->backdate($subscription, now()->subDays(11));

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysSucceedsGateway(), new \App\Domains\Subscriptions\Services\TrialService(new SubscriptionService(), $this->alwaysSucceedsGateway()));
        $counts = $service->runDailyCycle();

        $this->assertSame(1, $counts['expired']);
        $subscription = $subscription->fresh();
        $this->assertSame('expired', $subscription->status);
        $this->assertSame('expired', $subscription->church->status);
    }

    public function test_a_recently_entered_grace_period_subscription_is_not_yet_expired(): void
    {
        $subscription = $this->subscriptionDueToday('grace_period');
        $this->backdate($subscription, now()->subDays(2));

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysSucceedsGateway(), new \App\Domains\Subscriptions\Services\TrialService(new SubscriptionService(), $this->alwaysSucceedsGateway()));
        $counts = $service->runDailyCycle();

        $this->assertSame(0, $counts['expired']);
        $this->assertSame('grace_period', $subscription->fresh()->status);
    }

    public function test_a_cancellation_requested_subscription_is_cancelled_once_its_period_ends(): void
    {
        $subscription = $this->subscriptionDueToday();
        $subscription->update(['cancel_requested_at' => now()->subDays(3)]);

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysSucceedsGateway(), new \App\Domains\Subscriptions\Services\TrialService(new SubscriptionService(), $this->alwaysSucceedsGateway()));
        $counts = $service->runDailyCycle();

        $this->assertSame(1, $counts['cancelled']);
        $subscription = $subscription->fresh();
        $this->assertSame('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        // A cancellation-requested subscription must never also attempt a
        // renewal charge — cancelled takes priority, no invoice created.
        $this->assertSame(0, Invoice::withoutGlobalScopes()->where('church_id', $subscription->church_id)->count());
    }

    public function test_a_cancellation_requested_subscription_still_active_until_period_end_is_left_alone(): void
    {
        $church = Church::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = Subscription::create([
            'church_id' => $church->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_interval' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addWeeks(2),
            'cancel_requested_at' => now(),
        ]);

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysSucceedsGateway(), new \App\Domains\Subscriptions\Services\TrialService(new SubscriptionService(), $this->alwaysSucceedsGateway()));
        $counts = $service->runDailyCycle();

        $this->assertSame(0, $counts['cancelled']);
        $this->assertSame('active', $subscription->fresh()->status);
    }

    /**
     * The bug this migration exists to fix.
     *
     * A subscription that failed its charge 8 days ago (past the 5-day grace
     * threshold) must NOT be pushed into grace period merely because something
     * unrelated touched its row today. Before the explicit `past_due_at`
     * column the dunning query read `updated_at`, so that touch reset the
     * clock and extended the church's access.
     *
     * The backdate MUST exceed GRACE_PERIOD_DAYS (5), otherwise the buggy
     * query also refuses to match and this test would pass with the bug still
     * present — verified by reverting the fix and watching it fail.
     */
    public function test_an_unrelated_update_does_not_reset_the_past_due_clock(): void
    {
        $subscription = $this->subscriptionDueToday('past_due');
        $subscription->timestamps = false;
        $subscription->forceFill([
            'past_due_at' => now()->subDays(8),  // beyond the 5-day grace threshold
            'updated_at' => now()->subDays(8),
        ])->save();
        $subscription->timestamps = true;

        // Something unrelated writes to the row — this is what used to extend grace.
        $subscription->timestamps = false;
        $subscription->forceFill(['updated_at' => now()])->save();
        $subscription->timestamps = true;

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysSucceedsGateway(), new \App\Domains\Subscriptions\Services\TrialService(new SubscriptionService(), $this->alwaysSucceedsGateway()));
        $counts = $service->runDailyCycle();

        $this->assertSame(1, $counts['grace_period'], 'An 8-day-old past_due subscription must advance despite a fresh updated_at.');
        $this->assertSame('grace_period', $subscription->fresh()->status);
    }

    /**
     * The counterpart: an unrelated update must not reset the GRACE clock
     * either, and a genuinely overdue grace period must still expire.
     */
    public function test_an_unrelated_update_does_not_reset_the_grace_period_clock(): void
    {
        $subscription = $this->subscriptionDueToday('grace_period');
        $subscription->timestamps = false;
        $subscription->forceFill([
            'past_due_at' => now()->subDays(20),
            'grace_period_started_at' => now()->subDays(12),
            'updated_at' => now(),
        ])->save();
        $subscription->timestamps = true;

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysSucceedsGateway(), new \App\Domains\Subscriptions\Services\TrialService(new SubscriptionService(), $this->alwaysSucceedsGateway()));
        $counts = $service->runDailyCycle();

        $this->assertSame(1, $counts['expired'], 'A 12-day-old grace period must expire even though updated_at is fresh.');
        $this->assertSame('expired', $subscription->fresh()->status);
    }

    /** A legacy row with no explicit timestamp still gets the old behaviour. */
    public function test_a_legacy_row_without_dunning_timestamps_still_advances_by_updated_at(): void
    {
        $subscription = $this->subscriptionDueToday('past_due');
        $this->backdate($subscription, now()->subDays(6));
        $this->assertNull($subscription->fresh()->past_due_at);

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysSucceedsGateway(), new \App\Domains\Subscriptions\Services\TrialService(new SubscriptionService(), $this->alwaysSucceedsGateway()));
        $counts = $service->runDailyCycle();

        $this->assertSame(1, $counts['grace_period']);
        $this->assertSame('grace_period', $subscription->fresh()->status);
        $this->assertNotNull($subscription->fresh()->grace_period_started_at);
    }

    /** Recovering clears the clocks so the next lapse starts a fresh count. */
    public function test_a_renewal_clears_the_dunning_timestamps(): void
    {
        $subscription = $this->subscriptionDueToday();
        $subscription->forceFill([
            'past_due_at' => now()->subDays(3),
            'grace_period_started_at' => now()->subDays(1),
        ])->save();

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysSucceedsGateway(), new \App\Domains\Subscriptions\Services\TrialService(new SubscriptionService(), $this->alwaysSucceedsGateway()));
        $service->runDailyCycle();

        $subscription = $subscription->fresh();
        $this->assertSame('active', $subscription->status);
        $this->assertNull($subscription->past_due_at);
        $this->assertNull($subscription->grace_period_started_at);
    }
}

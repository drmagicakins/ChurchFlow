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

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysSucceedsGateway());
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

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysFailsGateway());
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

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysFailsGateway());
        $counts = $service->runDailyCycle();

        $this->assertSame(0, $counts['past_due']);
        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_a_long_overdue_past_due_subscription_advances_to_grace_period(): void
    {
        $subscription = $this->subscriptionDueToday('past_due');
        $this->backdate($subscription, now()->subDays(6));

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysSucceedsGateway());
        $counts = $service->runDailyCycle();

        $this->assertSame(1, $counts['grace_period']);
        $this->assertSame('grace_period', $subscription->fresh()->status);
    }

    public function test_a_long_overdue_grace_period_subscription_expires(): void
    {
        $subscription = $this->subscriptionDueToday('grace_period');
        $this->backdate($subscription, now()->subDays(11));

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysSucceedsGateway());
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

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysSucceedsGateway());
        $counts = $service->runDailyCycle();

        $this->assertSame(0, $counts['expired']);
        $this->assertSame('grace_period', $subscription->fresh()->status);
    }

    public function test_a_cancellation_requested_subscription_is_cancelled_once_its_period_ends(): void
    {
        $subscription = $this->subscriptionDueToday();
        $subscription->update(['cancel_requested_at' => now()->subDays(3)]);

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysSucceedsGateway());
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

        $service = new RenewalAndDunningService(new SubscriptionService(), $this->alwaysSucceedsGateway());
        $counts = $service->runDailyCycle();

        $this->assertSame(0, $counts['cancelled']);
        $this->assertSame('active', $subscription->fresh()->status);
    }
}

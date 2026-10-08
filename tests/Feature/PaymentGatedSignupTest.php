<?php

namespace Tests\Feature;

use App\Domains\Subscriptions\Services\CheckoutService;
use App\Domains\Subscriptions\Services\PaymentWebhookHandler;
use App\Models\Checkout;
use App\Models\Church;
use App\Models\Invoice;
use App\Models\OrganizationalUnit;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentGatedSignupTest extends TestCase
{
    use RefreshDatabase;

    private function successEvent(Checkout $checkout, string $eventId = 'evt-1'): array
    {
        return [
            'event' => 'payment.success',
            'provider' => 'null',
            'provider_event_id' => $eventId,
            'reference' => $checkout->provider_reference,
        ];
    }

    private function startCheckout(?User $user = null): Checkout
    {
        $user ??= User::factory()->create(['church_id' => null]);
        $plan = Plan::factory()->create(['name' => 'Growth', 'max_members' => 500]);

        [$checkout] = app(CheckoutService::class)->startSubscriptionCheckout($user, $plan, 'monthly');

        return $checkout;
    }

    public function test_registering_creates_a_user_but_never_a_church(): void
    {
        $this->post('/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'a-strong-password-1',
            'password_confirmation' => 'a-strong-password-1',
        ])->assertRedirect(route('plans.index'));

        $user = User::where('email', 'john@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->church_id);
        $this->assertSame(0, Church::count(), 'Registration alone must never create a church (payment comes first).');
    }

    public function test_a_registered_but_unpaid_user_is_sent_to_plan_selection_instead_of_the_dashboard(): void
    {
        $user = User::factory()->create(['church_id' => null]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('plans.index'));
    }

    public function test_starting_a_checkout_creates_a_pending_record_and_no_church(): void
    {
        $checkout = $this->startCheckout();

        $this->assertSame('pending', $checkout->status);
        $this->assertNotNull($checkout->provider_reference);
        $this->assertSame(0, Church::count());
        $this->assertSame(0, Subscription::withoutGlobalScopes()->count());
    }

    public function test_verified_payment_creates_church_owner_and_starts_a_14_day_trial(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['church_id' => null]);
        $checkout = $this->startCheckout($user);

        $this->actingAs($user)->get(route('checkout.verify', $checkout))->assertRedirect(route('dashboard'));

        $user = $user->fresh();
        $this->assertNotNull($user->church_id);

        $church = Church::find($user->church_id);

        // Phase 11: a new church starts on a 14-day trial, NOT on a paid
        // subscription — signup takes no money.
        $this->assertSame('trial', $church->status);

        $subscription = Subscription::withoutGlobalScopes()->where('church_id', $church->id)->first();
        $this->assertSame('trialing', $subscription->status);
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertSame(14, (int) now()->startOfDay()->diffInDays($subscription->trial_ends_at->startOfDay()));

        // The defining guarantee of the trial: nothing was charged, so no
        // invoice exists. An invoice here would be a false financial record.
        $this->assertSame(0, Invoice::withoutGlobalScopes()->where('church_id', $church->id)->count(),
            'Starting a trial must not create an invoice — no payment has happened.');

        $this->assertSame('completed', $checkout->fresh()->status);
        $this->assertSame(1, OrganizationalUnit::withoutGlobalScopes()->where('church_id', $church->id)->count());

        // Permission checks happen inside a tenant context in the real app
        // (IdentifyTenant binds it); do the same here, since Role's global
        // scope only exposes a church's own roles once its tenant is bound.
        app()->instance('tenant.church_id', $user->church_id);
        $this->assertTrue($user->hasPermission('billing.manage'), 'The new owner must actually hold the cloned owner permissions.');
    }

    public function test_the_same_webhook_delivered_twice_activates_exactly_once(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $checkout = $this->startCheckout();
        $handler = app(PaymentWebhookHandler::class);

        $handler->handle($this->successEvent($checkout, 'evt-dup'));
        $handler->handle($this->successEvent($checkout, 'evt-dup'));

        $this->assertSame(1, Church::count());
        $this->assertSame(1, Subscription::withoutGlobalScopes()->count());
        $this->assertSame(0, Invoice::withoutGlobalScopes()->count(), 'A trial creates no invoice.');
    }

    public function test_two_different_events_racing_for_one_checkout_still_activate_exactly_once(): void
    {
        // e.g. the browser "verify on return" path and the real provider
        // webhook arriving with different event ids for the same payment.
        $this->seed(RolePermissionSeeder::class);
        $checkout = $this->startCheckout();
        $handler = app(PaymentWebhookHandler::class);

        $handler->handle($this->successEvent($checkout, 'verify:abc:ok'));
        $handler->handle($this->successEvent($checkout, 'provider-evt-99'));

        $this->assertSame(1, Church::count());
        $this->assertSame(1, Subscription::withoutGlobalScopes()->count());
        $this->assertSame(0, Invoice::withoutGlobalScopes()->count(), 'A trial creates no invoice.');
    }

    public function test_a_failed_payment_creates_nothing_and_leaves_the_checkout_retryable(): void
    {
        $checkout = $this->startCheckout();

        app(PaymentWebhookHandler::class)->handle([
            'event' => 'payment.failed',
            'provider' => 'null',
            'provider_event_id' => 'evt-fail',
            'reference' => $checkout->provider_reference,
        ]);

        $this->assertSame(0, Church::count());
        $this->assertSame('pending', $checkout->fresh()->status);
    }

    public function test_a_successful_payment_after_an_earlier_failure_still_activates(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $checkout = $this->startCheckout();
        $handler = app(PaymentWebhookHandler::class);

        $handler->handle(['event' => 'payment.failed', 'provider' => 'null', 'provider_event_id' => 'e1', 'reference' => $checkout->provider_reference]);
        $handler->handle($this->successEvent($checkout, 'e2'));

        $this->assertSame(1, Church::count());
    }

    public function test_an_unrecognized_reference_is_logged_and_changes_nothing(): void
    {
        app(PaymentWebhookHandler::class)->handle([
            'event' => 'payment.success', 'provider' => 'null', 'provider_event_id' => 'e-x', 'reference' => 'does-not-exist',
        ]);

        $this->assertSame(0, Church::count());
        $this->assertDatabaseHas('payment_webhook_events', ['provider_event_id' => 'e-x']);
    }

    public function test_someone_cannot_verify_another_users_checkout(): void
    {
        $checkout = $this->startCheckout();
        $stranger = User::factory()->create(['church_id' => null]);

        $this->actingAs($stranger)->get(route('checkout.verify', $checkout))->assertForbidden();
        $this->assertSame(0, Church::count());
    }

    public function test_sms_credit_purchase_credits_the_wallet_exactly_once_even_if_the_webhook_repeats(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);

        [$checkout] = app(CheckoutService::class)->startSmsCreditsCheckout($user, $church, 1000, '5000.00');
        $handler = app(PaymentWebhookHandler::class);

        $handler->handle($this->successEvent($checkout, 'sms-1'));
        $handler->handle($this->successEvent($checkout, 'sms-1'));
        $handler->handle($this->successEvent($checkout, 'sms-2')); // different event id, same payment

        $wallet = \App\Models\SmsWallet::withoutGlobalScopes()->where('church_id', $church->id)->first();
        $this->assertSame(1000, $wallet->balance_units);
        $this->assertSame(1, Invoice::withoutGlobalScopes()->where('type', 'sms_credits')->count());
    }

    public function test_subscription_and_sms_revenue_stay_distinguishable_by_invoice_type(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $subCheckout = $this->startCheckout();
        app(PaymentWebhookHandler::class)->handle($this->successEvent($subCheckout, 'a'));

        $church = Church::first();
        $user = User::where('church_id', $church->id)->first();

        // Phase 11: the subscription checkout above started a TRIAL and so
        // wrote no subscription invoice. Convert it to a paid subscription
        // first, so this test still compares two real revenue streams rather
        // than accidentally asserting that a trial produced one.
        app(\App\Domains\Subscriptions\Services\TrialService::class)
            ->subscribeNow(Subscription::withoutGlobalScopes()->where('church_id', $church->id)->firstOrFail());

        [$smsCheckout] = app(CheckoutService::class)->startSmsCreditsCheckout($user, $church, 500, '2500.00');
        app(PaymentWebhookHandler::class)->handle($this->successEvent($smsCheckout, 'b'));

        $this->assertSame(1, Invoice::withoutGlobalScopes()->where('type', 'subscription')->count());
        $this->assertSame(1, Invoice::withoutGlobalScopes()->where('type', 'sms_credits')->count());
    }
}

<?php

namespace Tests\Feature;

use App\Domains\Subscriptions\Gateways\NullPaymentGateway;
use App\Domains\Subscriptions\Gateways\PaystackGateway;
use App\Domains\Subscriptions\Gateways\PaymentGatewayInterface;
use App\Models\Checkout;
use App\Models\Church;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P0-1: the payment gateway.
 *
 * What this file can and cannot prove, stated plainly:
 *
 *  - It CAN prove the integration's shape without a network: the endpoint we
 *    call, the bearer token we send, the amount we convert, which HTTP
 *    outcomes we accept as paid, and that a failure never marks a checkout
 *    paid. `Http::fake()` stands in for Paystack, so every branch is real
 *    code being executed against a real request/response cycle.
 *  - It CANNOT prove a real sandbox payment. That needs a live key and the
 *    Paystack dashboard, and no test here may pretend otherwise.
 *
 * The rule the assertions protect: money and success are the same thing. A
 * verification that did not come back `success` must never be reported as
 * paid, because that is the one mistake that gives a church a subscription
 * it never bought.
 */
class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    private function checkout(array $attributes = []): Checkout
    {
        $church = Church::factory()->create(['status' => 'active']);
        app()->instance('tenant.church_id', $church->id);

        $user = User::factory()->create(['church_id' => $church->id]);

        $plan = Plan::create([
            'name' => 'Growth', 'slug' => 'growth-'.$user->id,
            'monthly_price' => 25000, 'yearly_price' => 250000, 'currency' => 'NGN',
            'max_members' => 1000, 'max_branches' => 5, 'max_admins' => 10, 'storage_mb' => 10240,
        ]);

        return Checkout::create(array_merge([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'purpose' => 'subscription',
            'billing_interval' => 'monthly',
            'subtotal' => '1500.00',
            'tax_amount' => '0.00',
            'amount' => '1500.00',
            'currency' => 'NGN',
            'status' => 'pending',
        ], $attributes));
    }

    // --- initiate -----------------------------------------------------------

    public function test_initiate_asks_paystack_for_an_authorization_url_and_records_the_reference(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/abc123',
                    'access_code' => 'abc123',
                    'reference' => 'CF-TEST',
                ],
            ], 200),
        ]);

        $checkout = $this->checkout();
        $url = (new PaystackGateway('sk_test_fake'))->initiate($checkout);

        $this->assertSame('https://checkout.paystack.com/abc123', $url);

        $checkout->refresh();
        $this->assertSame('paystack', $checkout->provider);
        $this->assertNotEmpty($checkout->provider_reference, 'No reference was stored, so the payment could never be verified.');

        Http::assertSent(function ($request) use ($checkout) {
            return $request->url() === 'https://api.paystack.co/transaction/initialize'
                && $request->hasHeader('Authorization', 'Bearer sk_test_fake')
                // 1500.00 NGN must be sent as 150000 kobo, not 1500.
                && $request['amount'] === 150000
                && $request['currency'] === 'NGN'
                && $request['email'] === $checkout->user->email
                && $request['metadata']['checkout_id'] === $checkout->id;
        });
    }

    public function test_initiate_sends_no_reference_when_paystack_refuses(): void
    {
        // A refused initialise must leave NOTHING to verify. Storing a
        // reference here would let a later verify() look up a payment that was
        // never started.
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => false,
                'message' => 'Invalid key',
            ], 401),
        ]);

        $checkout = $this->checkout();

        try {
            (new PaystackGateway('sk_test_fake'))->initiate($checkout);
            $this->fail('A refused initialise was accepted as success.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Invalid key', $e->getMessage(), 'Paystack\'s own message should be surfaced.');
        }

        $this->assertNull($checkout->fresh()->provider_reference);
    }

    public function test_a_gateway_without_a_secret_key_refuses_rather_than_faking_success(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/PAYSTACK_SECRET_KEY/');

        (new PaystackGateway(null))->initiate($this->checkout());
    }

    // --- verify -------------------------------------------------------------

    public function test_verify_reports_success_only_when_paystack_says_success(): void
    {
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'reference' => 'CF-TEST'],
            ], 200),
        ]);

        $result = (new PaystackGateway('sk_test_fake'))->verify('CF-TEST');

        $this->assertTrue($result->success);
        $this->assertSame('CF-TEST', $result->providerReference);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.paystack.co/transaction/verify/CF-TEST'
            && $request->hasHeader('Authorization', 'Bearer sk_test_fake'));
    }

    public function test_verify_refuses_a_transaction_paystack_did_not_complete(): void
    {
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'abandoned', 'reference' => 'CF-TEST'],
            ], 200),
        ]);

        $result = (new PaystackGateway('sk_test_fake'))->verify('CF-TEST');

        $this->assertFalse($result->success, 'An abandoned payment was reported as paid.');
        $this->assertStringContainsString('abandoned', (string) $result->failureReason);
    }

    public function test_verify_treats_a_browser_return_with_no_payment_as_a_failure(): void
    {
        // The case that matters most: the customer came back to our site, but
        // Paystack says the transaction does not exist / did not settle.
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => false,
                'message' => 'Transaction reference not found',
            ], 404),
        ]);

        $result = (new PaystackGateway('sk_test_fake'))->verify('CF-NOTREAL');

        $this->assertFalse($result->success);
        $this->assertSame('Transaction reference not found', $result->failureReason);
    }

    public function test_verify_reports_a_network_failure_as_unsuccessful_instead_of_throwing(): void
    {
        // A dropped connection is NOT a payment. It must not 500 on a paying
        // customer, and it must not be mistaken for success.
        Http::fake([
            'api.paystack.co/*' => function () {
                throw new ConnectionException('Could not resolve host');
            },
        ]);

        $result = (new PaystackGateway('sk_test_fake'))->verify('CF-TEST');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Could not reach Paystack', (string) $result->failureReason);
    }

    // --- the seam -----------------------------------------------------------

    public function test_recurring_charging_refuses_rather_than_reporting_an_unpaid_renewal_as_paid(): void
    {
        // The schema decision behind provider_subscription_reference is a
        // product call. Until it is made, this must throw — never return
        // success, which would record a renewal nobody paid for.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/provider_subscription_reference/');

        (new PaystackGateway('sk_test_fake'))->chargeRecurring(new \App\Models\Subscription);
    }

    public function test_the_app_still_uses_the_network_free_gateway_by_default(): void
    {
        // The suite, local development and every existing test must keep the
        // null gateway unless someone explicitly switches to a real provider.
        $this->assertInstanceOf(NullPaymentGateway::class, app(PaymentGatewayInterface::class));

        config(['services.payments.gateway' => 'paystack', 'services.payments.paystack.secret_key' => 'sk_test_x']);
        $this->assertInstanceOf(PaystackGateway::class, app(PaymentGatewayInterface::class));
    }

    public function test_a_real_gateway_selected_without_a_key_still_fails_loudly(): void
    {
        // Selecting Paystack but forgetting the key must not silently fall back
        // to the always-successful null gateway.
        config(['services.payments.gateway' => 'paystack', 'services.payments.paystack.secret_key' => null]);

        $this->expectException(\RuntimeException::class);

        app(PaymentGatewayInterface::class)->initiate($this->checkout());
    }
}

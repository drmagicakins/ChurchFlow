<?php

namespace Tests\Feature;

use App\Domains\Subscriptions\Gateways\PaystackGateway;
use App\Models\Checkout;
use App\Models\Church;
use App\Models\PaymentWebhookEvent;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Paystack webhook URL (`POST /api/v1/webhooks/paystack`).
 *
 * What this proves: the endpoint authenticates a Paystack delivery the way
 * Paystack actually signs it (HMAC-SHA512 of the RAW body, in
 * `x-paystack-signature`), refuses everything else, and translates a
 * charge event into the normalized shape PaymentWebhookHandler already
 * understands — idempotently.
 *
 * What it cannot prove: that a real charge happened at Paystack. That is
 * `verify()`'s job (server-side, on the browser return) and needs a real
 * sandbox payment. The webhook and the return path are deliberately two
 * independent confirmations of the same fact.
 */
class PaystackWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_webhook_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.payments.gateway' => 'paystack',
            'services.payments.paystack.secret_key' => self::SECRET,
            'services.payments.paystack.webhook_secret' => null,
        ]);
    }

    /** Posts the raw JSON body with a correctly computed signature. */
    private function deliver(array $payload, ?string $signWith = null, bool $withHeader = true)
    {
        $body = json_encode($payload);
        $headers = ['Content-Type' => 'application/json'];

        if ($withHeader) {
            $headers['x-paystack-signature'] = hash_hmac('sha512', $body, $signWith ?? self::SECRET);
        }

        return $this->call('POST', route('api.v1.webhooks.paystack'), [], [], [], $this->transformHeadersToServerVars($headers), $body);
    }

    private function payload(string $event = 'charge.success', string $reference = 'CF-UNKNOWN', array $data = []): array
    {
        return [
            'event' => $event,
            'data' => array_merge(['id' => 1234567, 'reference' => $reference, 'status' => 'success'], $data),
        ];
    }

    // --- authentication -----------------------------------------------------

    public function test_it_fails_closed_when_paystack_is_not_configured(): void
    {
        config(['services.payments.paystack.secret_key' => null]);

        $this->deliver($this->payload())->assertStatus(503);
    }

    public function test_a_missing_signature_is_rejected(): void
    {
        $this->deliver($this->payload(), withHeader: false)->assertStatus(401);
    }

    public function test_a_wrong_signature_is_rejected(): void
    {
        $this->deliver($this->payload(), signWith: 'not-the-secret')->assertStatus(401);

        $this->assertSame(0, PaymentWebhookEvent::count(), 'An unsigned delivery must not be logged as processed.');
    }

    public function test_the_signature_must_be_over_the_raw_body(): void
    {
        // Signing the decoded/re-encoded array (different key order) must NOT
        // pass — the bytes Paystack signed are the bytes that must be checked.
        $body = '{"event":"charge.success","data":{"id":1,"reference":"CF-X"}}';
        $reencoded = json_encode(json_decode($body, true), JSON_PRETTY_PRINT);

        $this->assertNotSame($body, $reencoded);

        $this->call('POST', route('api.v1.webhooks.paystack'), [], [], [], $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            'x-paystack-signature' => hash_hmac('sha512', $reencoded, self::SECRET),
        ]), $body)->assertStatus(401);
    }

    public function test_a_webhook_secret_override_is_used_when_set(): void
    {
        config(['services.payments.paystack.webhook_secret' => 'separate-webhook-key']);

        // Signed with the secret key only => refused.
        $this->deliver($this->payload())->assertStatus(401);

        // Signed with the override => accepted.
        $this->deliver($this->payload(), signWith: 'separate-webhook-key')->assertOk();
    }

    // --- behaviour ----------------------------------------------------------

    public function test_a_charge_success_is_translated_and_logged_idempotently(): void
    {
        $payload = $this->payload(reference: 'CF-NOT-OURS');

        $this->deliver($payload)->assertOk();
        $this->deliver($payload)->assertOk();

        $events = PaymentWebhookEvent::where('provider', 'paystack')->get();

        $this->assertCount(1, $events, 'A redelivery must be ignored, not processed twice.');
        $this->assertSame('1234567', $events->first()->provider_event_id);
        $this->assertSame('payment.success', $events->first()->event_type);
    }

    public function test_charge_failed_is_acknowledged_but_changes_nothing(): void
    {
        $checkout = $this->checkout();

        $this->deliver($this->payload('charge.failed', $checkout->provider_reference))->assertOk();

        $this->assertSame('pending', $checkout->fresh()->status, 'A failed charge must leave the checkout retryable.');
    }

    public function test_unrelated_events_are_acknowledged_without_being_stored(): void
    {
        $this->deliver($this->payload('transfer.success'))->assertOk();

        $this->assertSame(0, PaymentWebhookEvent::count());
    }

    public function test_a_body_without_a_reference_is_rejected(): void
    {
        $this->deliver(['event' => 'charge.success', 'data' => ['id' => 9]])->assertStatus(400);
    }

    // --- the gateway's own signature helper --------------------------------

    public function test_the_gateway_signature_helper_matches_paystacks_scheme(): void
    {
        $gateway = new PaystackGateway(self::SECRET);
        $body = '{"event":"charge.success"}';
        $signature = hash_hmac('sha512', $body, self::SECRET);

        $this->assertTrue($gateway->verifyWebhookSignature($body, $signature));
        $this->assertFalse($gateway->verifyWebhookSignature($body, str_repeat('0', 128)));
        $this->assertFalse($gateway->verifyWebhookSignature($body, null));
        $this->assertFalse((new PaystackGateway(null))->verifyWebhookSignature($body, $signature), 'No key must mean no trust.');
    }

    // --- callback ------------------------------------------------------------

    public function test_the_checkout_callback_defaults_to_this_checkouts_verify_route(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/abc', 'access_code' => 'abc'],
            ]),
        ]);

        $checkout = $this->checkout();
        (new PaystackGateway(self::SECRET))->initiate($checkout);

        Http::assertSent(fn ($request) => $request['callback_url'] === route('checkout.verify', $checkout));
    }

    public function test_a_configured_callback_url_is_sent_instead(): void
    {
        config(['services.payments.paystack.callback_url' => 'https://churchflow.com.ng/payment/return']);

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/abc', 'access_code' => 'abc'],
            ]),
        ]);

        (new PaystackGateway(self::SECRET))->initiate($this->checkout());

        Http::assertSent(fn ($request) => $request['callback_url'] === 'https://churchflow.com.ng/payment/return');
    }

    private function checkout(): Checkout
    {
        $church = Church::factory()->create(['status' => 'active']);
        app()->instance('tenant.church_id', $church->id);

        $user = User::factory()->create(['church_id' => $church->id]);

        $plan = Plan::create([
            'name' => 'Growth', 'slug' => 'growth-'.$user->id,
            'monthly_price' => 25000, 'yearly_price' => 250000, 'currency' => 'NGN',
            'max_members' => 1000, 'max_branches' => 5, 'max_admins' => 10, 'storage_mb' => 10240,
        ]);

        return Checkout::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'purpose' => 'subscription',
            'billing_interval' => 'monthly',
            'subtotal' => '1500.00',
            'tax_amount' => '0.00',
            'amount' => '1500.00',
            'currency' => 'NGN',
            'status' => 'pending',
            'provider' => 'paystack',
            'provider_reference' => 'CF-'.uniqid(),
        ]);
    }
}

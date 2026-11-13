<?php

namespace App\Domains\Subscriptions\Gateways;

use App\Models\Checkout;
use App\Models\Subscription;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Real integration point for Paystack (§5/§6). Secret keys are platform
 * config, injected, never hard-coded and never committed (§5/§28's SMS
 * equivalent).
 *
 * Everything goes through Laravel's Http client, which has two consequences
 * that matter:
 *
 *  1. It can be TESTED without a network. `Http::fake()` stands in for
 *     Paystack, so the request we build, the headers we send and the way we
 *     read the reply are all exercised by the suite - the part that used to
 *     be a bare `throw` and was therefore proven by nothing.
 *  2. It still needs a real secret key to do anything useful. Without one it
 *     throws a clear error rather than pretending to work: a silent success
 *     here would mark a church paid when no money moved.
 *
 * Only the SECRET key is used, and only server-side; the public key belongs
 * in the browser and is not needed by this class.
 *
 * Amounts: Paystack takes the smallest currency unit (kobo for NGN, cents for
 * ZAR/GHS), so "1500.00" is sent as 150000. The conversion uses bcmath, not an
 * (int) float cast, so "0.29" cannot become 28 through binary rounding.
 */
class PaystackGateway implements PaymentGatewayInterface
{
    private const BASE_URL = 'https://api.paystack.co';

    public function __construct(
        private readonly ?string $secretKey = null,
        /** Where Paystack sends the browser back to; defaults to our own verify route. */
        private readonly ?string $callbackUrl = null,
        /** Key used to verify Paystack's webhook signature (x-paystack-signature). */
        private readonly ?string $webhookSecret = null,
    ) {}

    public function initiate(Checkout $checkout): string
    {
        $this->assertConfigured();

        // A reference must be unique per attempt, so a retried checkout gets a
        // NEW one rather than colliding with the previous attempt's.
        $reference = $checkout->provider_reference ?: 'CF-'.Str::upper(Str::random(12));

        $response = Http::withToken($this->key())
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post(self::BASE_URL.'/transaction/initialize', [
                'email' => $this->emailFor($checkout),
                'amount' => $this->toMinorUnits((string) $checkout->amount),
                'currency' => strtoupper((string) $checkout->currency),
                'reference' => $reference,
                // Where Paystack sends the browser after payment. An explicit
                // PAYSTACK_CALLBACK_URL wins, otherwise the signed-in verify
                // route for THIS checkout (the historical default). The return
                // only triggers server-side verification — never trust alone.
                'callback_url' => $this->callbackUrl
                    ?: config('services.payments.paystack.callback_url')
                    ?: route('checkout.verify', $checkout),
                'metadata' => [
                    'checkout_id' => $checkout->id,
                    'purpose' => $checkout->purpose,
                    'plan_id' => $checkout->plan_id,
                ],
            ]);

        $body = $response->json();

        if (! $response->successful() || ! ($body['status'] ?? false) || empty($body['data']['authorization_url'])) {
            // Paystack's own words where it gave any - worth more than a
            // generic "payment failed".
            throw new \RuntimeException(
                'Paystack could not start the checkout: '.($body['message'] ?? 'HTTP '.$response->status())
            );
        }

        // Recorded ONLY after Paystack confirms it accepted the request, so a
        // failed initialise leaves the checkout with no reference to verify.
        $checkout->update([
            'provider' => 'paystack',
            'provider_reference' => $reference,
        ]);

        return $body['data']['authorization_url'];
    }

    /**
     * Server-side verification (§6) - never trust the browser redirect alone.
     *
     * A redirect back to us only says the browser came back; it does not say
     * money moved. This asks Paystack directly and treats anything other than
     * a successful, completed transaction as a failure.
     */
    public function verify(string $providerReference): PaymentVerificationResult
    {
        $this->assertConfigured();

        try {
            $response = Http::withToken($this->key())
                ->acceptJson()
                ->timeout(20)
                ->get(self::BASE_URL.'/transaction/verify/'.rawurlencode($providerReference));
        } catch (ConnectionException $e) {
            // Network trouble is NOT a payment. Report it as unsuccessful so the
            // checkout stays pending and can be retried, instead of throwing an
            // exception that would reach a paying customer as a 500.
            return new PaymentVerificationResult(
                success: false,
                providerReference: $providerReference,
                failureReason: 'Could not reach Paystack to verify this payment.',
            );
        }

        $body = $response->json();
        $data = $body['data'] ?? [];

        if (! $response->successful() || ! ($body['status'] ?? false)) {
            return new PaymentVerificationResult(
                success: false,
                providerReference: $providerReference,
                failureReason: $body['message'] ?? 'Paystack rejected the verification request.',
            );
        }

        if (($data['status'] ?? null) !== 'success') {
            return new PaymentVerificationResult(
                success: false,
                providerReference: $providerReference,
                failureReason: 'Paystack reports this payment as '.($data['status'] ?? 'not completed').'.',
            );
        }

        return new PaymentVerificationResult(success: true, providerReference: $providerReference);
    }

    /**
     * Automatic renewal against a stored agreement
     * (`provider_subscription_reference`).
     *
     * NOT IMPLEMENTED, deliberately: that column does not exist yet, and the
     * schema decision behind it is a product one - a Paystack PLAN CODE plus
     * the customer's authorization code, or a manually re-charged
     * authorization, are different models with different failure behaviour.
     * Guessing here would be inventing a billing contract.
     *
     * It throws rather than returning success, so a renewal can never be
     * recorded as paid while nothing was charged.
     */
    public function chargeRecurring(Subscription $subscription): PaymentVerificationResult
    {
        throw new \RuntimeException(
            'PaystackGateway::chargeRecurring() is not implemented: recurring billing needs a '
            .'provider_subscription_reference schema decision (Paystack plan code + authorization code) '
            .'before it can be written safely.'
        );
    }

    /**
     * Paystack's webhook authenticity check (HMAC-SHA512 over the RAW body,
     * keyed with the secret key, sent in `x-paystack-signature`).
     *
     * The raw bytes are what was signed, so the caller must pass the request
     * body exactly as received — never a re-encoded array. Comparison is
     * constant-time and fails closed: no secret key, no header, or a mismatch
     * all mean "not Paystack".
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        // Paystack signs with the account's SECRET key. PAYSTACK_WEBHOOK_SECRET
        // is accepted as an override so a deployment can rotate the webhook key
        // independently; when it is blank the secret key is used, which is the
        // normal, dashboard-default arrangement.
        $key = $this->webhookSecret ?: $this->secretKey;

        if (blank($key) || blank($signatureHeader)) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawBody, (string) $key), (string) $signatureHeader);
    }

    private function key(): string
    {
        return (string) $this->secretKey;
    }

    private function assertConfigured(): void
    {
        if (blank($this->secretKey)) {
            throw new \RuntimeException(
                'Paystack is not configured: set PAYSTACK_SECRET_KEY in .env (a sandbox key is fine). '
                .'Without it a checkout could only be marked paid without money moving.'
            );
        }
    }

    /** Paystack charges the smallest currency unit (kobo/cents), as an integer. */
    private function toMinorUnits(string $amount): int
    {
        return (int) bcmul($amount, '100', 0);
    }

    /** The payer's email - Paystack requires one. */
    private function emailFor(Checkout $checkout): string
    {
        $email = $checkout->user?->email;

        if (blank($email)) {
            throw new \RuntimeException('This checkout has no payer email, so Paystack cannot start it.');
        }

        return $email;
    }
}

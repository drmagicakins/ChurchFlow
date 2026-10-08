<?php

namespace App\Domains\Subscriptions\Gateways;

use App\Models\Checkout;
use App\Models\Subscription;

/**
 * Real integration point for Paystack. This sandbox has no network access
 * to Paystack, and secret keys must never be hard-coded here (§5/§28's SMS
 * equivalent) — they're platform config, injected, never committed.
 */
class PaystackGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly ?string $secretKey = null) {}

    public function initiate(Checkout $checkout): string
    {
        throw new \RuntimeException('PaystackGateway is not configured for network access in this environment.');
    }

    public function chargeRecurring(Subscription $subscription): PaymentVerificationResult
    {
        throw new \RuntimeException('PaystackGateway is not configured for network access in this environment.');
    }

    public function verify(string $providerReference): PaymentVerificationResult
    {
        throw new \RuntimeException('PaystackGateway is not configured for network access in this environment.');
    }
}

<?php

namespace App\Domains\Subscriptions\Gateways;

use App\Models\Checkout;
use App\Models\Subscription;

class FlutterwaveGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly ?string $secretKey = null) {}

    public function initiate(Checkout $checkout): string
    {
        throw new \RuntimeException('FlutterwaveGateway is not configured for network access in this environment.');
    }

    public function chargeRecurring(Subscription $subscription): PaymentVerificationResult
    {
        throw new \RuntimeException('FlutterwaveGateway is not configured for network access in this environment.');
    }

    public function verify(string $providerReference): PaymentVerificationResult
    {
        throw new \RuntimeException('FlutterwaveGateway is not configured for network access in this environment.');
    }
}

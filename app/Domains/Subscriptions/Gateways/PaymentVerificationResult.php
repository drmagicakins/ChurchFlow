<?php

namespace App\Domains\Subscriptions\Gateways;

final class PaymentVerificationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $providerReference,
        public readonly ?string $failureReason = null,
    ) {}
}

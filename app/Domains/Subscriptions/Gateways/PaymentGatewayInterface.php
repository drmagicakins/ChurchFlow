<?php

namespace App\Domains\Subscriptions\Gateways;

use App\Models\Checkout;
use App\Models\Subscription;

/**
 * §5: the platform must support Paystack/Flutterwave without the rest of
 * the app ever depending on either by name — same shape as
 * SmsProviderInterface in Phase 7. Nothing outside Domains/Subscriptions/
 * Gateways refers to a specific provider.
 */
interface PaymentGatewayInterface
{
    /** Starts a hosted checkout session; returns the URL to redirect the person to. */
    public function initiate(Checkout $checkout): string;

    /** Server-side verification (§6) — never trust a browser redirect alone. */
    public function verify(string $providerReference): PaymentVerificationResult;

    /**
     * Attempts an automatic renewal charge against the subscription's
     * stored recurring-billing agreement (`provider_subscription_reference`
     * — a real gateway's own subscription/authorization object, never a
     * raw card number stored by this app). Used by the daily billing-cycle
     * command; see RenewalAndDunningService.
     */
    public function chargeRecurring(Subscription $subscription): PaymentVerificationResult;
}

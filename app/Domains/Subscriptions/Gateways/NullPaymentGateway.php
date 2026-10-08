<?php

namespace App\Domains\Subscriptions\Gateways;

use App\Models\Checkout;
use App\Models\Subscription;
use Illuminate\Support\Str;

/**
 * Used by default in this scaffold, in tests, and in local development —
 * never touches a network. "Paying" always succeeds. Swap the container
 * binding (see AppServiceProvider) for PaystackGateway/FlutterwaveGateway
 * once real credentials exist.
 */
class NullPaymentGateway implements PaymentGatewayInterface
{
    public function initiate(Checkout $checkout): string
    {
        $reference = (string) Str::uuid();
        $checkout->update(['provider' => 'null', 'provider_reference' => $reference]);

        // Points back at THIS application's own verify endpoint rather than a
        // fake external host. A real gateway hands the browser off to a hosted
        // payment page; the null gateway has no such page, and sending the
        // browser to an unreachable https://example.test/... was a dead end
        // that made the whole signup flow impossible to walk in development or
        // in a browser check. Routing to verify instead exercises the exact
        // same server-side verification path (CheckoutController::verify -> the
        // server-to-server check -> the idempotent webhook handler) a real
        // gateway's return trip would use, which is the part worth having
        // working locally.
        return route('checkout.verify', $checkout);
    }

    public function verify(string $providerReference): PaymentVerificationResult
    {
        return new PaymentVerificationResult(success: true, providerReference: $providerReference);
    }

    public function chargeRecurring(Subscription $subscription): PaymentVerificationResult
    {
        return new PaymentVerificationResult(success: true, providerReference: (string) Str::uuid());
    }
}

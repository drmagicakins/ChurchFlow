<?php

namespace App\Domains\Subscriptions\Services;

use App\Domains\Subscriptions\Gateways\PaymentGatewayInterface;
use App\Models\Checkout;
use App\Models\Church;
use App\Models\Plan;
use App\Models\User;

class CheckoutService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly TaxCalculator $tax,
    ) {}

    /** §4/§7/§8: starting a subscription checkout never creates a church — see ActivateChurchFromCheckout. */
    public function startSubscriptionCheckout(User $user, Plan $plan, string $billingInterval): array
    {
        abort_unless(in_array($billingInterval, ['monthly', 'yearly'], true), 422, 'Invalid billing interval.');

        $subtotal = $plan->priceFor($billingInterval);
        $tax = $this->tax->calculate($subtotal);

        $checkout = Checkout::create([
            'user_id' => $user->id,
            'purpose' => 'subscription',
            'plan_id' => $plan->id,
            'billing_interval' => $billingInterval,
            'subtotal' => $subtotal,
            'tax_rate' => $tax['rate'],
            'tax_amount' => $tax['tax_amount'],
            'amount' => bcadd($subtotal, $tax['tax_amount'], 2), // grand total — what's actually charged (§4)
            'currency' => $plan->currency,
            'status' => 'pending',
            'expires_at' => now()->addHours(2),
        ]);

        $redirectUrl = $this->gateway->initiate($checkout);

        return [$checkout->fresh(), $redirectUrl];
    }

    /** §17-18: buying SMS credits for a church that already exists. */
    public function startSmsCreditsCheckout(User $user, Church $church, int $units, string $subtotal, string $currency = 'NGN'): array
    {
        abort_if($units <= 0, 422, 'Units must be positive.');

        $tax = $this->tax->calculate($subtotal);

        $checkout = Checkout::create([
            'user_id' => $user->id,
            'church_id' => $church->id,
            'purpose' => 'sms_credits',
            'sms_units' => $units,
            'subtotal' => $subtotal,
            'tax_rate' => $tax['rate'],
            'tax_amount' => $tax['tax_amount'],
            'amount' => bcadd($subtotal, $tax['tax_amount'], 2),
            'currency' => $currency,
            'status' => 'pending',
            'expires_at' => now()->addHours(2),
        ]);

        $redirectUrl = $this->gateway->initiate($checkout);

        return [$checkout->fresh(), $redirectUrl];
    }
}

<?php

namespace App\Domains\Subscriptions\Services;

use App\Domains\Subscriptions\Actions\ActivateChurchFromCheckout;
use App\Domains\Subscriptions\Actions\CreditSmsWalletFromCheckout;
use App\Models\Checkout;
use App\Models\PaymentWebhookEvent;

/**
 * §32: provider-specific webhook payloads (Paystack's shape, Flutterwave's
 * shape) are translated into this normalized event shape by a thin
 * controller/adapter BEFORE reaching this class — nothing here, and
 * nothing in ActivateChurchFromCheckout or CreditSmsWalletFromCheckout,
 * ever refers to a provider's own event names or payload structure. That
 * translation boundary is what makes adding a third provider later a
 * change to one adapter, not to this handler.
 *
 * §33: logs the webhook delivery itself (idempotent on provider +
 * provider_event_id) BEFORE doing anything else, so a redelivered webhook
 * is caught here even before the Checkout-level idempotency check in the
 * two actions this delegates to.
 */
class PaymentWebhookHandler
{
    public function __construct(
        private readonly ActivateChurchFromCheckout $activateChurch,
        private readonly CreditSmsWalletFromCheckout $creditSmsWallet,
    ) {}

    /**
     * @param array{event:string, provider:string, provider_event_id:string, reference:string} $normalizedEvent
     */
    public function handle(array $normalizedEvent): void
    {
        $log = PaymentWebhookEvent::firstOrCreate(
            ['provider' => $normalizedEvent['provider'], 'provider_event_id' => $normalizedEvent['provider_event_id']],
            ['event_type' => $normalizedEvent['event'], 'payload' => $normalizedEvent],
        );

        if (!$log->wasRecentlyCreated) {
            return; // already processed this exact delivery — see class docblock
        }

        $checkout = Checkout::where('provider_reference', $normalizedEvent['reference'])->first();

        if (!$checkout) {
            $log->update(['processed_at' => now()]);
            return; // nothing to do with an unrecognized reference — logged, not silently dropped
        }

        if ($normalizedEvent['event'] === 'payment.success') {
            match ($checkout->purpose) {
                'subscription' => $this->activateChurch->handle($checkout),
                'sms_credits' => $this->creditSmsWallet->handle($checkout),
                default => null,
            };
        }

        // payment.failed: deliberately no state change to the checkout —
        // it stays 'pending' so the person can retry without a new record
        // being created (§7 "no duplicate records").

        $log->update(['processed_at' => now()]);
    }
}

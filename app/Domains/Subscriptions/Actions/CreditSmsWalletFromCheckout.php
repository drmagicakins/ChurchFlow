<?php

namespace App\Domains\Subscriptions\Actions;

use App\Domains\Communication\Services\SmsWalletService;
use App\Models\Checkout;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Same idempotency discipline as ActivateChurchFromCheckout — a checkout
 * already 'completed' is a no-op, and the wallet credit + invoice write
 * happen in the same transaction as marking the checkout completed.
 */
class CreditSmsWalletFromCheckout
{
    public function __construct(private readonly SmsWalletService $wallets) {}

    public function handle(Checkout $checkout): void
    {
        abort_unless($checkout->purpose === 'sms_credits', 422, 'This checkout is not an SMS-credits checkout.');

        if ($checkout->status === 'completed') {
            return;
        }

        DB::transaction(function () use ($checkout) {
            $checkout = Checkout::whereKey($checkout->id)->lockForUpdate()->first();

            if ($checkout->status === 'completed') {
                return;
            }

            $wallet = $this->wallets->walletFor($checkout->church);

            $this->wallets->credit(
                $wallet,
                $checkout->sms_units,
                'purchase',
                "checkout:{$checkout->id}",
                "Purchased {$checkout->sms_units} SMS credits",
            );

            Invoice::create([
                'church_id' => $checkout->church_id,
                'checkout_id' => $checkout->id,
                'type' => 'sms_credits',
                'amount' => $checkout->subtotal,
                'tax_amount' => $checkout->tax_amount,
                'tax_rate' => $checkout->tax_rate,
                'currency' => $checkout->currency,
                'status' => 'paid',
                'description' => "{$checkout->sms_units} SMS credits",
                'provider' => $checkout->provider,
                'provider_reference' => $checkout->provider_reference,
                'paid_at' => now(),
            ]);

            $checkout->update(['status' => 'completed']);
        });
    }
}

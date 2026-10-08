<?php

namespace App\Http\Controllers;

use App\Domains\Subscriptions\Services\PaymentWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An unauthenticated endpoint that can activate paid churches and credit
 * SMS wallets is exactly the kind of route that must fail CLOSED: with no
 * webhook secret configured, every request is rejected — never accepted
 * "for now, until we add signature checking."
 *
 * In production, each real gateway gets its own thin adapter (verifying
 * that provider's actual signature scheme — Paystack's HMAC header,
 * Flutterwave's verif-hash — and translating its payload into the
 * normalized shape below) BEFORE calling PaymentWebhookHandler::handle().
 * This controller implements the generic shared-secret version, which is
 * also what the NullPaymentGateway-based dev/test flow uses.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(private readonly PaymentWebhookHandler $handler) {}

    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.payments.webhook_secret');

        abort_if(empty($secret), 503, 'Webhook secret is not configured.');
        abort_unless(hash_equals((string) $secret, (string) $request->header('X-Webhook-Secret')), 401);

        $data = $request->validate([
            'event' => ['required', 'in:payment.success,payment.failed'],
            'provider' => ['required', 'string'],
            'provider_event_id' => ['required', 'string'],
            'reference' => ['required', 'string'],
        ]);

        $this->handler->handle($data);

        // Always 200 for a well-formed, authenticated delivery — including
        // a redelivery we deliberately ignored — so the provider stops
        // retrying. Duplicate ≠ error.
        return response()->json(['received' => true]);
    }
}

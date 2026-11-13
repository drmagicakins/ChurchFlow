<?php

namespace App\Http\Controllers;

use App\Domains\Subscriptions\Gateways\PaystackGateway;
use App\Domains\Subscriptions\Services\PaymentWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Paystack-to-server endpoint (PAYSTACK webhook URL).
 *
 * This is the thin provider adapter §32 asks for, and it is deliberately a
 * SEPARATE route from the generic PAYMENT_WEBHOOK_SECRET endpoint: Paystack
 * does not send our shared secret, it signs the raw body with the account's
 * secret key (HMAC-SHA512, `x-paystack-signature`). Feeding a Paystack
 * delivery to the generic endpoint would therefore always be rejected, and
 * loosening the generic endpoint to accept it would remove the only check
 * standing between the open internet and "activate a church / credit a
 * wallet". Each provider keeps its own adapter and its own scheme; the
 * normalized event shape handed to PaymentWebhookHandler is shared.
 *
 * Failure policy is fail-CLOSED, in this order:
 *   - 503 when Paystack is not configured or has no secret key to verify
 *     against, so an unconfigured deployment never accepts a delivery;
 *   - 401 when the signature is absent or wrong (body is not read for
 *     meaning before this passes);
 *   - 200 for every authentic delivery, including one we ignore, so
 *     Paystack stops retrying. Duplicate != error (§33).
 */
class PaystackWebhookController extends Controller
{
    public function __construct(private readonly PaymentWebhookHandler $handler) {}

    public function handle(Request $request): JsonResponse
    {
        $secretKey = config('services.payments.paystack.secret_key');

        abort_if(blank($secretKey), 503, 'Paystack is not configured: PAYSTACK_SECRET_KEY is empty.');

        $gateway = new PaystackGateway(
            secretKey: $secretKey,
            webhookSecret: config('services.payments.paystack.webhook_secret'),
        );

        // Signature over the EXACT bytes Paystack sent. getContent() returns the
        // raw body; re-encoding the decoded array would reorder keys and break
        // the HMAC of a delivery that was genuinely signed.
        $rawBody = $request->getContent();
        $signature = $request->header('x-paystack-signature');

        abort_unless($gateway->verifyWebhookSignature($rawBody, $signature), 401, 'Invalid Paystack signature.');

        $payload = json_decode($rawBody, true);

        abort_unless(is_array($payload), 400, 'Payload is not valid JSON.');

        $event = (string) ($payload['event'] ?? '');

        // Only the events that can move money are acted on. Everything else is
        // acknowledged (200) so Paystack does not keep retrying it, but is not
        // stored: the webhook log is an audit of things we acted on, not a
        // dumping ground.
        if (! in_array($event, ['charge.success', 'charge.failed'], true)) {
            return response()->json(['received' => true, 'ignored' => $event]);
        }

        $data = $payload['data'] ?? [];
        $reference = (string) ($data['reference'] ?? '');

        abort_if($reference === '', 400, 'Payload carries no transaction reference.');

        // Paystack's own transaction id is the idempotency key, so a redelivery
        // of the same charge is caught by PaymentWebhookHandler's firstOrCreate
        // and can never activate the same church twice (§33).
        $providerEventId = (string) ($data['id'] ?? $reference);

        $this->handler->handle([
            'event' => $event === 'charge.success' ? 'payment.success' : 'payment.failed',
            'provider' => 'paystack',
            'provider_event_id' => $providerEventId,
            'reference' => $reference,
        ]);

        return response()->json(['received' => true]);
    }
}

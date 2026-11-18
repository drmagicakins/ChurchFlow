<?php

namespace App\Domains\Communication\Providers;

use App\Domains\Communication\Services\PhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Termii — the real SMS provider (§23/§28).
 *
 * WHAT THIS CLASS REPLACED. It used to be a stub whose `send()` threw
 * "not configured for network access", which was honest but meant no text could
 * ever leave the platform. This is the real thing: Termii's single-message
 * endpoint, mapped onto SmsSendResult so every caller (SmsSendingService,
 * SendEventReminderSmsJob) keeps its wallet/refund logic unchanged.
 *
 * THE CONTRACT (Termii, "Send message"):
 *
 *   POST {base_url}/api/sms/send          Content-Type: application/json
 *   {
 *     "api_key": "…", "to": "2348012345678", "from": "<approved sender id>",
 *     "sms": "<text>", "type": "plain", "channel": "dnd"
 *   }
 *
 *   success → { "code": "ok", "message_id": "…", "message": "Successfully Sent, …",
 *               "balance": …, "user": "…" }
 *   failure → a non-"ok" `code`/`message` (HTTP 4xx/5xx, or a 200 carrying a
 *             rejection — Termii does both), which is reported as unsuccessful.
 *
 * WHY EVERY FAILURE IS A RESULT AND NOT AN EXCEPTION. The callers charge a
 * wallet BEFORE sending and refund the individual person's units when a send
 * fails. An exception thrown out of here would abort the whole batch and leave
 * reserved units unaccounted for; a failed result refunds exactly that person.
 * The one exception is a MISCONFIGURATION (no key, no sender id), which is
 * raised before any unit is spent — see assertConfigured() and
 * AppServiceProvider, which refuses to bind this class at all without a key.
 *
 * NUMBERS ARE NORMALISED, NOT GUESSED. Termii wants an international number
 * without the leading "+". PhoneNumber is the app's single authority on what a
 * stored number means (and on the spreadsheet-damaged ones that must never be
 * sent to), so it is reused here rather than re-derived; a number it refuses is
 * refused here too, with the reason, and is never sent.
 */
class TermiiProvider implements SmsProviderInterface
{
    /** Path of the single-message endpoint, relative to the configured base. */
    public const SEND_PATH = '/api/sms/send';

    /** Termii's own success code. Anything else is a failure. */
    public const OK = 'ok';

    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $senderId = null,
        private readonly string $baseUrl = 'https://v4.api.termii.com',
        private readonly string $channel = 'dnd',
        private readonly string $type = 'plain',
        private readonly ?string $caBundle = null,
        private readonly ?PhoneNumber $phones = null,
    ) {}

    public function send(string $toPhone, string $message, ?string $from = null): SmsSendResult
    {
        $this->assertConfigured();

        $phones = $this->phones ?? new PhoneNumber;
        $normalised = $phones->normalise($toPhone);

        if ($normalised === null) {
            // A number PhoneNumber refuses (scientific notation, nonsense) must
            // not be handed to the provider: it would be charged for and fail,
            // or worse, delivered somewhere nobody intended.
            return new SmsSendResult(
                success: false,
                failureReason: 'Not a sendable phone number: '.($phones->problemWith($toPhone) ?? 'unrecognised'),
            );
        }

        if (trim($message) === '') {
            return new SmsSendResult(success: false, failureReason: 'The message is empty.');
        }

        $payload = [
            'api_key' => (string) $this->apiKey,
            'to' => ltrim($normalised, '+'),   // Termii: international, no "+"
            'from' => $this->senderFor($from),
            'sms' => $message,
            'type' => $this->type,
            'channel' => $this->channel,
        ];

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(20)
                ->withOptions($this->tlsOptions())
                ->post($this->endpoint(), $payload);
        } catch (ConnectionException $e) {
            // A network fault is not a delivery. Reported, never thrown: the
            // caller refunds this person and carries on with the batch.
            Log::warning('Termii send failed: could not reach the provider.', [
                'reason' => $e->getMessage(),
            ]);

            return new SmsSendResult(
                success: false,
                failureReason: 'Could not reach the SMS provider. Please try again shortly.',
            );
        }

        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $code = $body['code'] ?? null;

        if (! $response->successful() || $code !== self::OK) {
            $reason = $this->rejectionMessage($body, $response->status());

            Log::warning('Termii rejected an SMS.', [
                'status' => $response->status(),
                'code' => $code,
                'message' => $body['message'] ?? null,
            ]);

            return new SmsSendResult(success: false, failureReason: $reason);
        }

        // `message_id` is Termii's handle for the message; it is what a support
        // conversation or a delivery report is keyed on, so it is kept.
        $messageId = $body['message_id'] ?? null;

        return new SmsSendResult(
            success: true,
            providerMessageId: $messageId !== null ? (string) $messageId : null,
        );
    }

    /**
     * Why Termii refused, in words a church owner can act on.
     *
     * The provider's own `message` is passed through when it has one — it names
     * things like an unapproved sender id, which is exactly what an operator
     * needs — and only a generic sentence is invented when it does not.
     */
    private function rejectionMessage(array $body, int $status): string
    {
        $message = $body['message'] ?? null;

        if (is_string($message) && trim($message) !== '') {
            return 'The SMS provider refused this message: '.$message;
        }

        return 'The SMS provider refused this message (HTTP '.$status.').';
    }

    /**
     * The name the text is sent FROM.
     *
     * The church's requirement is that a message goes out under the church's
     * own name (or its parish pastor's) — the recipient is far more likely to
     * trust "Grace Chapel" than "ChurchFlow". So the caller's name wins; the
     * configured TERMII_SENDER_ID is the fallback for a church with no usable
     * name, and assertConfigured() guarantees at least one of them exists.
     *
     * The value is trimmed and collapsed to single spaces here: a trailing
     * newline pasted from a spreadsheet would otherwise be rejected by Termii
     * as an unknown sender, and the error would blame the configuration rather
     * than the invisible character.
     */
    private function senderFor(?string $from): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $from));

        return $name !== '' ? $name : (string) $this->senderId;
    }

    private function endpoint(): string
    {
        return rtrim((string) $this->baseUrl, '/').self::SEND_PATH;
    }

    /**
     * TLS trust options for every Termii request.
     *
     * Identical reasoning to PaystackGateway::tlsOptions(): a PHP build with no
     * `curl.cainfo` cannot verify the provider's chain, and every request dies
     * with "cURL error 60" — which, for SMS, would present as "provider
     * unreachable" on every campaign. `verify` is pointed at a REAL bundle and
     * is never `false`: turning TLS checking off on the request that carries a
     * church's messages would trade a visible error for an invisible
     * man-in-the-middle.
     *
     * @return array<string, mixed>
     */
    private function tlsOptions(): array
    {
        $candidates = array_filter([
            $this->caBundle,
            (string) ini_get('curl.cainfo'),
            (string) ini_get('openssl.cafile'),
            base_path('vendor/composer/ca-bundle/res/cacert.pem'),
        ]);

        foreach ($candidates as $bundle) {
            if (is_string($bundle) && is_file($bundle) && is_readable($bundle)) {
                return ['verify' => $bundle, 'connect_timeout' => 15];
            }
        }

        return ['verify' => true, 'connect_timeout' => 15];
    }

    /**
     * Refuse before spending a unit.
     *
     * Not a SmsSendResult: a missing key or sender id is a platform
     * misconfiguration, not one person's delivery failing, and the callers'
     * refund path must not be used to paper over it.
     */
    private function assertConfigured(): void
    {
        if (blank($this->apiKey)) {
            throw new \RuntimeException(
                'Termii is not configured: set TERMII_API_KEY in .env. '
                .'Without it no SMS can be sent, and a "success" would be fiction.'
            );
        }

        if (blank($this->senderId)) {
            throw new \RuntimeException(
                'Termii is not configured: set TERMII_SENDER_ID in .env to one of your APPROVED '
                .'sender ids (see the Termii dashboard, or GET /api/sender-id for the list and its '
                .'status). Termii refuses a send whose `from` is not approved, and guessing a '
                .'default would fail the church\'s text after its units were reserved.'
            );
        }
    }
}

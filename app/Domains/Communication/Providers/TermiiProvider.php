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
        $this->assertConfigured($from);

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
        } catch (ConnectionException|\Illuminate\Http\Client\RequestException $e) {
            // A network fault (a dropped connection or a TLS handshake that
            // could not be verified) is not a delivery. Reported, never thrown:
            // the caller refunds this person and carries on with the batch.
            Log::warning('Termii send failed: could not reach the provider.', [
                'reason' => $e->getMessage(),
            ]);

            return new SmsSendResult(
                success: false,
                failureReason: 'Could not reach the SMS provider. Please try again shortly.',
            );
        } catch (\Throwable $e) {
            // Last line of defence, for ONE person's text. Anything else the
            // transport can raise (a guzzle failure the two classes above do
            // not cover, a malformed URL) must not escape here: an exception
            // thrown out of send() aborts the whole campaign batch and leaves
            // the remaining recipients' reserved units unaccounted for. It is
            // reported as that one person's failure so the refund happens and
            // the rest of the church's messages still go out.
            report($e);

            return new SmsSendResult(
                success: false,
                failureReason: 'The SMS provider could not be reached. Please try again shortly.',
            );
        }

        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $code = $body['code'] ?? null;

        // A SENT TEXT IS A SENT TEXT, wherever the provider reports it.
        //
        // Termii's documented single-message answer carries `code: "ok"` in the
        // body, and that is what this integration was written against. Their
        // newer API wave also answers with the same success fields
        // (`message_id`, `message_id_str`, `balance`, "Successfully Sent") and no
        // `code` at all — measured against the account with a deliberately
        // invalid key on 2026-11-25, where a refused send came back with
        // `code: "unauthorized"` and no message id. Treating that shape as a
        // rejection would mark delivered texts "failed" and refund units the
        // provider HAD charged, so the success marker on the message id is
        // honoured too. A body with no success marker of any kind is still a
        // failure — nothing here trusts the HTTP status alone.
        $reportsSuccess = $response->successful() && ($code === self::OK || $this->carriesMessageId($body));

        if (! $reportsSuccess) {
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
        // `message_id_str` is the same handle in the newer response shape.
        $messageId = $body['message_id'] ?? $body['message_id_str'] ?? null;

        return new SmsSendResult(
            success: true,
            providerMessageId: $messageId !== null ? (string) $messageId : null,
        );
    }

    /**
     * Does this answer carry Termii's own proof that a message was accepted?
     *
     * The message id (or its `message_id_str` twin) is issued only for a
     * message Termii has taken, so its presence is a success marker in its own
     * right when the newer API wave omits `code`. An answer with a `code` that
     * is present but is NOT "ok" is never rescued by this — a refusal that
     * happens to echo an id still reads as a refusal.
     */
    private function carriesMessageId(array $body): bool
    {
        if (array_key_exists('code', $body) && $body['code'] !== null && $body['code'] !== self::OK) {
            return false;
        }

        foreach (['message_id', 'message_id_str'] as $field) {
            $value = $body[$field] ?? null;

            if (is_string($value) ? trim($value) !== '' : is_int($value)) {
                return true;
            }
        }

        return false;
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
     * THAT fallback is what makes the integration work for EVERY church, and it
     * is why this whole stream is deployed with
     * SMS_SENDER_FROM_CHURCH_NAME=false: `ChurchFlow` is an ACCOUNT-WIDE
     * approved header on the Termii account (confirmed active on 2026-11-25 via
     * GET /api/sender-id), so every owner, every parish pastor and every
     * church on this deployment sends under it — no per-church registration
     * required. Turning the church-name switch on before each church's header
     * is approved would send under a name Termii refuses.
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
            // Composer ships a CA bundle at ONE of these two paths, depending
            // on the install's layout (a Linux/shared-hosting deploy usually
            // has the `ca-bundle` package and the first path; this project's
            // `vendor/` has only `composer/ca-bundle/res/cacert.pem`). Both are
            // checked, because a missing bundle means every send fails with
            // "cURL error 60" and presents as "provider unreachable".
            base_path('vendor/composer/ca-bundle/res/cacert.pem'),
            base_path('vendor/composer/ca-bundle/cacert.pem'),
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
    private function assertConfigured(?string $from = null): void
    {
        if (blank($this->apiKey)) {
            throw new \RuntimeException(
                'Termii is not configured: set TERMII_API_KEY in .env. '
                .'Without it no SMS can be sent, and a "success" would be fiction.'
            );
        }

        // `from` is now decided per message (the caller's name, or the
        // configured sender as fallback), so this class can no longer refuse
        // up front for a missing TERMII_SENDER_ID without breaking the very
        // path that fills that gap. The check that matters is the one below:
        // no usable sender on THIS message is still a refusal, raised before
        // any unit is spent.
        if (! $this->hasUsableSender($from)) {
            throw new \RuntimeException(
                'Termii is not configured: set TERMII_SENDER_ID in .env to one of your APPROVED '
                .'sender ids (see the Termii dashboard, or GET /api/sender-id for the list and its '
                .'status). Termii refuses a send whose `from` is not approved, and guessing a '
                .'default would fail the church\'s text after its units were reserved.'
            );
        }
    }

    /**
     * Is there a sender name THIS message could go out under at all?
     *
     * The caller's own name counts: it is the name SmsSenderName resolved for
     * the church or parish, and on this deployment it is the account-wide
     * approved header. Only when neither the caller nor the configuration has
     * one is the message refused — before any unit is spent.
     */
    private function hasUsableSender(?string $from = null): bool
    {
        foreach ([$from, $this->senderId] as $candidate) {
            if (trim((string) preg_replace('/\s+/u', ' ', (string) $candidate)) !== '') {
                return true;
            }
        }

        return false;
    }
}

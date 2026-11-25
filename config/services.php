<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment gateway (Phase 8)
    |--------------------------------------------------------------------------
    |
    | webhook_secret guards the generic webhook endpoint. With it UNSET every
    | webhook request is rejected — deliberately fails closed, because a
    | webhook is the one request that can mark an invoice paid and activate a
    | church, so "not configured" must never mean "accept anything".
    |
    | This key originally shipped in its own config/services.payments.snippet.php
    | file whose own header said to merge it in here. Left as a separate file it
    | was never loaded by anything, so config('services.payments.webhook_secret')
    | silently resolved to null and the endpoint could not have accepted a real
    | webhook even with the env var set. Merged into the real file instead.
    */
    /*
    |--------------------------------------------------------------------------
    | Church logo public URL
    |--------------------------------------------------------------------------
    |
    | The base URL the church logo is served from (see Church::logoUrl()).
    |
    | It must be the ORIGIN that actually serves `public/storage` — normally the
    | app itself, so it defaults to APP_URL. Point it elsewhere only when a CDN
    | or separate asset host fronts that directory.
    |
    | Why this is its own key: the stored logo is written to disk on upload and
    | exists only on the machine that received it. Emails are sent from one
    | deployment (APP_URL) while the logo was uploaded on another (e.g. a
    | developer's machine at http://127.0.0.1:8000), so building the URL from
    | APP_URL produced a link that 404s and the image never showed. Setting
    | CHURCH_LOGO_URL on the machine that SENDS the email points it at the host
    | that actually holds the file, without changing APP_URL for anything else.
    | Blank (the default) keeps the historical behaviour: APP_URL + /storage.
    */
    'church_logo_url' => env('CHURCH_LOGO_URL'),

    'payments' => [
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),

        /*
        | Which gateway the app actually uses: `null` (network-free, always
        | succeeds - the local/test default), `paystack` or `flutterwave`.
        |
        | Defaulting to `null` is deliberate: switching a live payment path on
        | must be a decision someone makes, never something that happens because
        | a key was pasted into an environment. A real provider is selected by
        | GATEWAY plus its secret key.
        */
        'gateway' => env('PAYMENT_GATEWAY', 'null'),

        'paystack' => [
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),

            /*
            | Where Paystack sends the browser after a payment. When unset, each
            | checkout falls back to its own signed-in verify route
            | (checkout.verify), which is what makes the flow work on any host
            | without configuration. Set PAYSTACK_CALLBACK_URL only when a
            | single fixed page is wanted (it must still verify server-side).
            */
            'callback_url' => env('PAYSTACK_CALLBACK_URL'),

            /*
            | Optional override for the key Paystack signs webhooks with. Blank
            | means "use PAYSTACK_SECRET_KEY", which is Paystack's own default:
            | the dashboard signs with the account secret key and exposes no
            | separate webhook key. Do not invent one — see
            | PaystackWebhookController.
            */
            'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),

            /*
            | The CA bundle used to verify Paystack's TLS certificate. Blank
            | means "choose one automatically": PHP's curl.cainfo / openssl.cafile
            | when set, otherwise the bundle Composer ships
            | (vendor/composer/ca-bundle/res/cacert.pem). See
            | PaystackGateway::tlsOptions() for why this is needed — a PHP build
            | with no CA bundle fails every request with "cURL error 60" and the
            | checkout page answers 500. Set it only to pin a specific file.
            */
            'ca_bundle' => env('PAYSTACK_CA_BUNDLE'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS provider (Phase 7) — Termii
    |--------------------------------------------------------------------------
    |
    | The app talks to SMS providers only through SmsProviderInterface; which
    | one is used is decided in AppServiceProvider and nowhere else.
    |
    | `gateway` mirrors services.payments.gateway: `null` (the network-free
    | NullSmsProvider, the local/test default) or `termii`. Termii is selected
    | only when the gateway is named explicitly AND a key exists — a key on its
    | own must never switch a live SMS path on, and a named gateway with no key
    | throws rather than pretending a text was sent.
    |
    | base_url is configurable because Termii routes accounts to different
    | hosts (v3 vs v4). This deployment answers on https://v4.api.termii.com/
    | for sender-id and balance (probed, not assumed — see
    | SESSION-HANDOFF.md §1x), so that is the default.
    |
    | sender_id has NO default on purpose. Termii rejects a send whose `from`
    | is not an APPROVED sender id, and inventing a default ("ChurchFlow")
    | would turn a configuration gap into a failed text the church paid a unit
    | for. Unset means the provider refuses, in words naming the variable.
    |
    |     …UPDATED 2026-11-25 — the default IS the approved header now. Both
    |     statements above still hold, which is exactly why this is safe:
    |     `ChurchFlow` was measured ACTIVE on the account (GET /api/sender-id,
    |     2026-11-25), so it is not an invented name. Keeping it as the default
    |     means the stream works for EVERY church on a deploy whose .env was
    |     copied before the variable was filled in — and an env value still
    |     overrides it. A blank TERMII_SENDER_ID= line (present but empty) wins
    |     over this default, which is what lets an install disable sending.
    |
    | channel: `dnd` routes around Nigerian do-not-disturb blocks and requires
    | an approved sender id; `generic` does not. `dnd` is the right default for
    | a church reaching its own congregation, and it matches Termii's own
    | examples.
    */
    'sms' => [
        'gateway' => env('SMS_GATEWAY', 'null'),

        'termii' => [
            'base_url' => env('TERMII_BASE_URL', 'https://v4.api.termii.com'),
            'api_key' => env('TERMII_API_KEY'),

            /*
            | PLATFORM FALLBACK sender id — usually the platform's own approved
            | sender, used only when the sending church has no name of its own.
            |
            | The sender id is NOT an installation-wide constant: per the
            | church's requirement (2026-11-17) a text goes out under the NAME OF
            | THE CHURCH / PARISH PASTOR that is sending it. That name is resolved
            | per send (see SenderIdResolver) and passed into
            | TermiiProvider::send() as `$from`; this value is what it falls back
            | to when the church has no usable name.
            */
            'sender_id' => env('TERMII_SENDER_ID', 'ChurchFlow'),

            /*
            | Whether a message may be sent under the CHURCH's own name instead of
            | the account's approved sender id. FALSE by default: Termii refuses a
            | `from` that is not an approved header on the account, so a church
            | name that has not been registered would fail every send. Turn it on
            | only when each church holds an approved header of its own.
            */
            'from_church_name' => (bool) env('SMS_SENDER_FROM_CHURCH_NAME', false),
            'channel' => env('TERMII_CHANNEL', 'dnd'),
            'type' => env('TERMII_MESSAGE_TYPE', 'plain'),

            /*
            | Same CA-bundle problem PaystackGateway documents: this machine's
            | PHP has no curl.cainfo, so libcurl cannot verify the provider's
            | certificate and every request dies with "cURL error 60" — which
            | would turn every campaign into "provider unreachable". Blank means
            | "choose one automatically" (curl.cainfo → openssl.cafile → the
            | bundle Composer ships). Verification is never disabled.
            */
            'ca_bundle' => env('TERMII_CA_BUNDLE'),
        ],
    ],

];

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
        ],
    ],

];

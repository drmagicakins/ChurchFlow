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
    ],

];

<?php

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PaystackWebhookController;
use Illuminate\Support\Facades\Route;

// §46: versioned API surface. Stateless — no session, no CSRF (Laravel's
// api middleware group) — which is exactly right for a provider-to-server
// webhook. Authenticity comes from the shared-secret header check inside
// PaymentWebhookController, which fails closed if no secret is configured.
Route::prefix('v1')->group(function () {
    Route::post('/webhooks/payments', [PaymentWebhookController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('api.v1.webhooks.payments');

    // The URL to enter in the Paystack dashboard (Settings → API Keys &
    // Webhooks → Webhook URL). Separate from the generic endpoint on purpose:
    // Paystack authenticates with an HMAC-SHA512 signature over the raw body
    // (`x-paystack-signature`), not our shared secret. See
    // PaystackWebhookController for the fail-closed policy.
    Route::post('/webhooks/paystack', [PaystackWebhookController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('api.v1.webhooks.paystack');

    // §46/§14: the church-facing read API, authenticated by a church-issued
    // bearer token (`api.token`). ApiController and AuthenticateApiToken both
    // existed with no route between them, so these endpoints were unreachable
    // — `api_access` could never actually be used by any plan. The controller
    // still enforces the plan feature itself (402), so routing them here does
    // not grant a Starter/Growth church anything.
    Route::middleware(['api.token:read', 'throttle:120,1'])->group(function () {
        Route::get('/me', [ApiController::class, 'me'])->name('api.v1.me');
        Route::get('/members', [ApiController::class, 'members'])->name('api.v1.members');
        Route::get('/events', [ApiController::class, 'events'])->name('api.v1.events');
        Route::get('/attendance/sessions', [ApiController::class, 'attendanceSessions'])->name('api.v1.attendance.sessions');
    });
});

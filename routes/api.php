<?php

use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

// §46: versioned API surface. Stateless — no session, no CSRF (Laravel's
// api middleware group) — which is exactly right for a provider-to-server
// webhook. Authenticity comes from the shared-secret header check inside
// PaymentWebhookController, which fails closed if no secret is configured.
Route::prefix('v1')->group(function () {
    Route::post('/webhooks/payments', [PaymentWebhookController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('api.v1.webhooks.payments');
});

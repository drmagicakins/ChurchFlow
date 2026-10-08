<?php

use App\Http\Controllers\Marketing\HomeController;
use App\Http\Controllers\Marketing\MarketingSignupController;
use Illuminate\Support\Facades\Route;

/*
 |--------------------------------------------------------------------------
 | Marketing / Public Routes
 |--------------------------------------------------------------------------
 | Public-facing pages only. No tenant context is required here — a church
 | (tenant) is created later, after account creation and verified payment.
 | Include this file from routes/web.php: require __DIR__.'/marketing.php';
 |
 | Every route below is named, and every link in the navbar, footer and page
 | components resolves through a route name rather than a literal path. That is
 | what makes it safe to move a page: renaming one line here updates every link.
 */

// "/" is owned by LandingController (see routes/web.php, Phase 10 landing
// page). This used to be HomeController@index — the marketing-suite home
// page — and two routes answering the same URI is a silent bug: whichever
// was registered last won, so this one was shadowing the real landing page.
//
// Deleting it outright would break every `route('home')` link, and pointing
// it back at "/" (a redirect to itself) is a loop. So the marketing-suite
// home page keeps its own URL at /home and its own route name `home`, and
// "/" belongs to the landing page.
Route::get('/home', [HomeController::class, 'index'])->name('home');

Route::get('/features', [HomeController::class, 'features'])->name('features');
Route::get('/pricing', [HomeController::class, 'pricing'])->name('pricing');
Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::get('/demo', [HomeController::class, 'demo'])->name('demo');
Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
Route::get('/support', [HomeController::class, 'support'])->name('support');

Route::prefix('resources')->name('resources.')->group(function () {
    Route::get('/blog', [HomeController::class, 'blog'])->name('blog');
    Route::get('/help-center', [HomeController::class, 'helpCenter'])->name('help-center');
    Route::get('/guides', [HomeController::class, 'guides'])->name('guides');
});

Route::prefix('legal')->name('legal.')->group(function () {
    Route::get('/privacy', [HomeController::class, 'privacy'])->name('privacy');
    Route::get('/terms', [HomeController::class, 'terms'])->name('terms');
});

/*
 |--------------------------------------------------------------------------
 | Onboarding entry
 |--------------------------------------------------------------------------
 | The landing page's "Get Started" buttons point here rather than straight at
 | /register, so that when the billing module lands there is exactly one place
 | to change: MarketingSignupController::start(). Today it validates the optional
 | ?plan= against config and forwards to /register.
 |
 | It is a GET because it only redirects. It creates nothing — no user, no church
 | tenant, no subscription — because a tenant must not exist before payment is
 | verified (brief, section 12).
 */
Route::get('/get-started', [MarketingSignupController::class, 'start'])->name('get-started');

/*
 |--------------------------------------------------------------------------
 | Post-registration journey (billing must happen before tenant creation)
 |--------------------------------------------------------------------------
 | Register -> Choose Subscription -> Payment -> Server-side Verification
 | -> Create/Activate Church Tenant -> Setup Wizard -> Dashboard.
 | These are stubs: wire them up once the billing module is implemented.
 | Never treat a client-side "payment successful" screen as proof of payment.
 */
// Route::middleware('auth')->group(function () {
//     Route::get('/billing/plans', [BillingController::class, 'plans'])->name('billing.plans');
//     Route::get('/billing/checkout/{plan}', [BillingController::class, 'checkout'])->name('billing.checkout');
//     Route::post('/billing/payment/{plan}', [BillingController::class, 'initiatePayment'])->name('billing.payment');
//     Route::post('/billing/webhook/{provider}', [BillingWebhookController::class, 'handle'])->name('billing.webhook');
//     Route::get('/church/setup', [ChurchSetupController::class, 'show'])->name('church.setup');
// });

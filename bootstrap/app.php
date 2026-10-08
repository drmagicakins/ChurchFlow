<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // §46: the versioned API surface. Without this line routes/api.php is
        // never loaded at all, so the payment webhook the gateway calls would
        // 404 — and the route's own existence is asserted by
        // BillingEnforcementTest, which fails loudly rather than silently.
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // §43 (Phase 10): security headers on every response. Appended
        // globally so even public routes (marketing, /health) are covered.
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Without this, IdentifyTenant isn't in Laravel's default middleware
        // priority list, so it was running AFTER SubstituteBindings (which
        // resolves route-model-bound parameters like {member} in `members.show`).
        // At that point app('tenant.church_id') hadn't been set yet, so
        // BelongsToTenant's global scope filtered every implicitly-bound model
        // out of existence — every show/update/destroy route 404'd for every
        // resource in the app, authenticated or not, regardless of tenant.
        // Confirmed independent of the grid work: GET /events/1 404s the same way.
        //
        // Verified in a real browser before and after: the Members grid's own
        // "View" link (/members/4) and /events/1 both returned 404 before this
        // line and 200 after it.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\IdentifyTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

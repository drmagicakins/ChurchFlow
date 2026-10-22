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

        // §46: the alias the versioned public API routes use to authenticate a
        // bearer token and bind the tenant from it. Named rather than used as a
        // class string so the route file stays readable and the ability
        // parameter (route middleware 'api.token:read') is possible.
        $middleware->alias([
            'api.token' => \App\Http\Middleware\AuthenticateApiToken::class,
        ]);

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

        // The same ordering problem, for the platform-admin routes.
        //
        // PlatformAdminOnly is what sets 'tenant.disabled', and every
        // tenant-scoped model's global scope reads that flag. Left in the
        // default order it ran AFTER SubstituteBindings, so binding a
        // tenant-owned parameter (platform-admin.support.show's {ticket}) was
        // filtered by the tenant scope while no tenant was bound — the scope
        // emitted `where 1 = 0`, the model came back null, and the route 404'd
        // even though the ticket existed and the admin could see it in the
        // queue. Billing's {church} binding never exposed this because Church
        // is not a BelongsToTenant model.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\PlatformAdminOnly::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

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
        // A server fault renders resources/views/errors/500.blade.php for a
        // BROWSER, instead of Laravel's developer error page (stack trace, SQL,
        // request internals). The framework already does this for any status
        // that has a matching errors/{status} view — 419 and 422 are the
        // precedents — so this only has to make sure a served page never falls
        // back to the debug one: in local/testing the debug page is the default,
        // and a church administrator testing the app is not a developer.
        //
        // Deliberately does NOT touch JSON: API callers, the payment webhook and
        // every fetch() in the app keep the existing JSON error contract and
        // its status codes, and 404/403/419 keep their own pages.
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return null; // leave the framework's JSON handling exactly as it is
            }

            // Validation, authentication and authorisation are NOT server faults
            // and must keep the framework's own handling: a validation error
            // goes back to the form with its message, a guest is redirected to
            // sign in, a denied policy is a 403. (A validation error caught by
            // the branch below would be shown as "Something went wrong" —
            // that is precisely what this guard prevents.)
            if ($e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof \Illuminate\Auth\AuthenticationException
                || $e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                return null;
            }

            // 404 / 403 / 419 keep their own handling and pages.
            //
            // 422 deliberately does NOT opt out: that is the plan limit (a 422
            // abort from PlanLimitService, e.g. "You've reached the branches
            // limit... Current: 1 / 1 branches"). Without this it was a bare
            // browser error document — "An Error Occurred: Unprocessable
            // Content" — with no way to upgrade. It now gets errors/422, which
            // states the limit and links to billing. A validation failure never
            // reaches here (guarded above), so this cannot turn a form error
            // into an error page.
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                && $e->getStatusCode() < 500
                && $e->getStatusCode() !== 422) {
                return null;
            }

            $status = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            // Only a page we actually have is used. A 503 (maintenance) has its
            // own page in this app; anything else without one renders the 500
            // copy but keeps its own status, so the browser and any monitoring
            // still see the truth (503 + Retry-After, 523, ...).
            $view = view()->exists("errors.{$status}") ? "errors.{$status}" : 'errors.500';

            $rendered = response()
                ->view($view, ['exception' => $e], $status)
                ->withException($e);

            // Never let a page reached by a broken signed-in request be cached:
            // the next person to arrive would be served this error page.
            $rendered->headers->set('Cache-Control', 'no-store, private');

            return $rendered;
        });
    })->create();

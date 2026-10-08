<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * §34: active/past_due/grace_period get normal access — past_due and
 * grace_period are deliberately NOT restricted here; a real deployment
 * would show a banner prompting payment, which is a UI concern, not an
 * access-control one. expired/cancelled/suspended are restricted to only
 * the billing routes, so the church owner can always reach the page that
 * lets them pay and get back in. §35: none of this ever touches data —
 * restriction is purely routing, nothing is deleted or hidden from a
 * future reactivation.
 *
 * Runs AFTER IdentifyTenant, so app('tenant.church_id') is already bound.
 */
class EnsureSubscriptionAllowsAccess
{
    private const ALWAYS_ALLOWED_ROUTE_PREFIXES = ['billing.', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $church = $request->user()->church;

        if (!$church || in_array($church->status, ['active', 'past_due', 'grace_period', 'pending'], true)) {
            return $next($request);
        }

        $routeName = $request->route()?->getName() ?? '';

        foreach (self::ALWAYS_ALLOWED_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return $next($request);
            }
        }

        abort(403, "Your church's subscription is {$church->status}. Please reactivate your subscription to continue.");
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant (church) from the authenticated user and
 * binds it into the container for the duration of the request.
 *
 * Register this on every route group EXCEPT platform-admin routes.
 * Platform-admin routes instead run PlatformAdminOnly, which binds
 * 'tenant.disabled' = true so TenantScope steps aside entirely, and
 * platform-admin controllers filter by church_id explicitly and deliberately
 * wherever they need to (e.g. "show all churches").
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if(!$user, 401);
        abort_if($user->is_platform_admin, 403, 'Platform admins do not have an implicit church context.');

        // §1/§2: a registered user with no church has not completed
        // payment yet. That's the normal state of someone mid-signup, not
        // an error — send them back into the plan/payment flow rather than
        // showing a bare 403. API callers still get the 403.
        if (!$user->church_id) {
            abort_if($request->expectsJson(), 403, 'This account is not attached to a church.');

            return redirect()->route('plans.index')
                ->with('status', 'Choose a plan and complete payment to set up your church.');
        }

        app()->instance('tenant.church_id', $user->church_id);

        return $next($request);
    }
}

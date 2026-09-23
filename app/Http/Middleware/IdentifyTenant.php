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
        abort_if(!$user->church_id, 403, 'This account is not attached to a church.');

        app()->instance('tenant.church_id', $user->church_id);

        return $next($request);
    }
}

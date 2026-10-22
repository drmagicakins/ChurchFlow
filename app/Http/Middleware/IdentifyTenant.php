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

        // A platform admin has no church, so a tenant-scoped route cannot
        // resolve a tenant for them. This used to abort with a bare 403
        // ("Platform admins do not have an implicit church context") — a raw
        // error page naming an internal concept the operator cannot act on,
        // shown the moment they clicked any in-app link after logging in.
        //
        // The honest answer is a redirect to the area that IS theirs: they
        // asked for a page that cannot exist for their account, and the useful
        // response is "go here instead", not an error. API callers still get
        // the 403, because a machine cannot follow a redirect to a page it did
        // not ask for and should be told plainly that this route is not for it.
        if ($user->is_platform_admin) {
            abort_if($request->expectsJson(), 403, 'Platform admins do not have an implicit church context.');

            return redirect()->route('platform-admin.dashboard');
        }

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

        // §14/§37 parish scoping. When the signed-in user is attached to a
        // parish (and is not the registrar / church-wide), bind that parish so
        // TenantScope narrows every parish-owned query to it. Binding it here —
        // once, beside the tenant — is what makes "a user sees only their
        // parish's information" structural rather than a rule each controller
        // has to remember. The registrar is deliberately NOT bound, so they see
        // every parish.
        if ($user->isParishScoped()) {
            app()->instance('tenant.organizational_unit_id', $user->organizational_unit_id);
        }

        return $next($request);
    }
}

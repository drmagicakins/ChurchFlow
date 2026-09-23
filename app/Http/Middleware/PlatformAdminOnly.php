<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlatformAdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(!$request->user() || !$request->user()->is_platform_admin, 403);

        // Explicitly disable tenant scoping for this request rather than
        // binding a church_id — platform-admin controllers see across
        // tenants by design and must filter deliberately when narrowing.
        app()->instance('tenant.disabled', true);

        return $next($request);
    }
}

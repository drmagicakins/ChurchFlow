<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * §46: authenticates the versioned public API with a church-issued bearer
 * token, and binds the tenant from the TOKEN (never from the session, which a
 * machine caller does not have).
 *
 * This is the API's equivalent of IdentifyTenant: once it has resolved a token
 * it binds 'tenant.church_id', so every tenant-scoped model query in the API
 * controller is automatically filtered to the key's church — the same
 * fail-closed guarantee the web app relies on. A request with no valid token
 * never reaches a controller, so it never reaches a query.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $plaintext = $request->bearerToken();

        if (! $plaintext) {
            return $this->unauthorized('An API token is required. Send it as: Authorization: Bearer <token>.');
        }

        $token = ApiToken::resolve($plaintext);

        if (! $token) {
            return $this->unauthorized('This API token is invalid, revoked or expired.');
        }

        if ($ability !== null && ! $token->hasAbility($ability)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => "This token does not have the '{$ability}' ability.",
            ], 403);
        }

        // Bind the tenant the token belongs to. Every downstream query is
        // scoped by this, so a token can never read another church's rows even
        // if a controller forgot an explicit filter.
        app()->instance('tenant.church_id', $token->church_id);

        // Expose the resolved token + its church to the controller.
        $request->attributes->set('api_token', $token);
        $request->attributes->set('api_church', $token->church);

        // Record usage so the management screen can show a key that is (or is
        // not) actually being used. best-effort: a failed write here must not
        // fail the request the caller is making.
        try {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        } catch (\Throwable $e) {
            report($e);
        }

        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        return response()->json(['error' => 'unauthorized', 'message' => $message], 401);
    }
}

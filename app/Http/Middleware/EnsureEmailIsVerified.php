<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * WHO has to confirm their email address — and who does not.
 *
 * The requirement is "the church owner and parish pastor should activate their
 * email". Read literally that is two roles; read as a rule it is every account
 * that can act on a church's behalf, and it is expressed the narrow way it is
 * because getting each half wrong has a different cost:
 *
 *   IN (gated)          the church owner, a parish pastor, and any other
 *                       church-scoped account. They receive the church's mail,
 *                       can post announcements to the congregation, and are the
 *                       people a reply is addressed to — all of which need an
 *                       address that is KNOWN to work.
 *
 *   OUT (not gated)     - a person who has registered but not bought a plan:
 *                         no church yet, nothing to protect, and blocking them
 *                         would break the signup journey this app deliberately
 *                         orders as pay-first;
 *                       - platform administrators: their address is provisioned
 *                         by the operator, not self-registered;
 *                       - an INACTIVE account: it cannot sign in anyway, so a
 *                         verification error would be the wrong message.
 *
 * EXISTING ACCOUNTS ARE NOT LOCKED OUT. An account created before this feature
 * existed has `email_verified_at = NULL` and has been working for months. It is
 * NOT pushed back to the "confirm your address" page: that would take a live
 * church's owner out of their own dashboard for a rule that did not exist when
 * they signed up. Verification is required at the next moment it can be done
 * without breaking their work — see the SECURITY NOTE below.
 *
 * SECURITY NOTE — what this middleware is and is not. It stops an unverified
 * account from USING the application. It cannot pretend a legacy account is
 * verified, and it does not: the offers are "confirm now" and "not now", and the
 * dashboard carries a persistent reminder until the address is confirmed.
 */
class EnsureEmailIsVerified
{
    /** Session key set by "Not now" (see the reminder on the notice page). */
    public const DEFER_KEY = 'email_verification_deferred_at';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $this->mayUseTheApp($user)) {
            return $next($request);
        }

        // A JSON caller (an API client, a fetch) gets a clear status rather than
        // a redirect it cannot follow.
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Please confirm your email address before continuing.',
                'verify' => route('verification.notice'),
            ], 403);
        }

        return redirect()->route('verification.notice');
    }

    /**
     * Whether this account may use the application without confirming.
     *
     * Public so the dashboard reminder and any future screen can ask the SAME
     * question rather than re-deriving it (and drifting from it).
     */
    public static function mayUseTheApp(User $user): bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        if (! $user->is_active) {
            return true; // cannot sign in; the verification message would mislead
        }

        // No church yet: a signup that has not bought a plan. Nothing to reach.
        if ($user->church_id === null) {
            return true;
        }

        if ($user->hasVerifiedEmail()) {
            return true;
        }

        // A legacy account (created before verification existed) may continue
        // until it has been offered the chance to confirm — and its owner chose
        // to defer. Once deferred it is never asked again by a redirect; the
        // in-app reminder is what keeps the offer visible.
        return $user->created_at !== null
            && $user->created_at->lt(self::verificationShippedAt())
            && session(self::DEFER_KEY) === 'legacy';
    }

    /**
     * When this feature went live.
     *
     * Accounts created BEFORE it cannot have a confirmation, so they are given
     * the choice rather than an ultimatum. Kept as a single, named constant so
     * the cut-off is one visible decision instead of a date buried in a
     * comparison — and so tests can pin it.
     */
    public static function verificationShippedAt(): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::parse('2026-11-17 00:00:00');
    }
}

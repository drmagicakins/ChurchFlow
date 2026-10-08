<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * §43 "secure headers". Applied globally (see bootstrap/app.php).
 *
 * CSP is kept tight but MUST describe how this app actually loads assets,
 * or it silently breaks the page rather than protecting it. Two allowances
 * are load-bearing here:
 *
 *  1. `script-src 'self' 'unsafe-eval'` — Alpine evaluates every x-data /
 *     x-show / @click expression with `new Function(...)`. Without
 *     'unsafe-eval' the browser refuses to compile those expressions, so
 *     Alpine strips `x-cloak` but never applies `x-show` — the Resources
 *     submenu then renders permanently open on the marketing navbar, and
 *     every other Alpine-driven interaction (mobile drawer, pricing toggle,
 *     FAQ accordions) is dead. This was observed, not assumed: with the CSP
 *     header removed the same page hid the submenu correctly.
 *  2. Google Fonts — the marketing and auth layouts both load Inter from
 *     fonts.googleapis.com. Without `style-src`/`font-src` entries for it,
 *     the stylesheet is blocked and the site falls back to a system font.
 *
 * `img-src` includes `data:` for inline SVG/data-URI images. Revisit
 * 'unsafe-eval' if/when Alpine is replaced or a nonce-based build (e.g.
 * moving directive expressions out of the DOM) makes it unnecessary.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; "
            ."script-src 'self' 'unsafe-eval'; "
            ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            ."font-src 'self' https://fonts.gstatic.com data:; "
            ."img-src 'self' data:; "
            ."connect-src 'self'; "
            ."frame-ancestors 'none';"
        );

        return $response;
    }
}

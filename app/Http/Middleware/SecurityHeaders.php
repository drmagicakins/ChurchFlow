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
 *
 * PHASE 12 — THE INLINE-SCRIPT GAP, FOUND IN A BROWSER
 * ---------------------------------------------------
 * `script-src 'self' 'unsafe-eval'` allowed no inline scripts at all. That
 * silently killed every `<script>` block in the app: the landing page's
 * tab explorer, the dashboard's Chart.js render, and every form's
 * collapse/expand toggle. Nothing 500'd and no PHP test noticed — the pages
 * returned 200 with the markup intact, and only the browser refused to
 * execute the script. It was caught by loading the real pages in a real
 * browser and reading the console.
 *
 * The fix is a NONCE, not `'unsafe-inline'`:
 *
 *   - A nonce lets the app's own inline scripts run, and nothing else. A
 *     blanket 'unsafe-inline' would also re-enable inline scripts injected
 *     by an attacker, which is the main thing CSP exists to stop — so it
 *     would fix the symptom by removing most of the protection.
 *   - The nonce is generated per response and attached to every script tag
 *     in that response.
 *
 * Blanket `'unsafe-inline'` is still emitted for local/dev as a fallback,
 * because a nonce only applies to scripts the server actually tags. Anything
 * that injects a script without the nonce (a third-party widget, a cached
 * prebuilt asset) would otherwise break in dev with no obvious cause. In
 * production the nonce is the only inline allowance.
 */
class SecurityHeaders
{
    /** The per-request nonce, so views can tag their own script tags. */
    public static function nonce(): ?string
    {
        return app()->bound('csp.nonce') ? app('csp.nonce') : null;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Generated before the response so every view rendered during this
        // request can read it and stamp it onto its own <script> tags.
        $nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        app()->instance('csp.nonce', $nonce);

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $scriptSrc = "'self' 'unsafe-eval' 'nonce-{$nonce}' https://cdn.jsdelivr.net";

        // Dev-only escape hatch — see the class docblock. Never in production.
        if (app()->environment('local', 'testing')) {
            $scriptSrc .= " 'unsafe-inline'";
        }

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; "
            ."script-src {$scriptSrc}; "
            ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            ."font-src 'self' https://fonts.gstatic.com data:; "
            ."img-src 'self' data:; "
            ."connect-src 'self'; "
            ."frame-ancestors 'none';"
        );

        return $response;
    }
}

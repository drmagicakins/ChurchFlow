<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
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
 * NOTE on cdn.jsdelivr.net: the dashboard's `@push('scripts')` block used to
 * load Chart.js from that CDN, and this policy was widened to allow it. That
 * script tag has since been removed — Chart.js is imported through
 * resources/js/app.js and bundled by Vite instead, which is strictly better
 * here: it is version-pinned by package.json, it works offline, and it does
 * not hand a third party the ability to execute code in this app. The CDN
 * entry is therefore gone rather than left in as harmless-looking dead
 * configuration, because a standing third-party `script-src` allowance for a
 * script nothing loads is pure attack surface. Leaving it also hid a real
 * finding: a test asserting the allowance stays "correct" while the app
 * no longer needs it.
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
 *
 * THE VITE DEV-SERVER GAP — SAME BUG, SECOND APPEARANCE
 * ----------------------------------------------------
 * `npm run dev` does not serve assets from this origin. Vite serves them from
 * its own dev server (http://localhost:5173, and http://[::1]:5173 when the
 * browser resolves "localhost" to IPv6), and the Blade layouts point at it.
 * The policy above allows only 'self', so in dev the browser refused ALL of
 * them:
 *
 *     Loading the stylesheet 'http://[::1]:5173/resources/css/app.css'
 *     violates the following Content-Security-Policy directive:
 *     "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com"
 *
 * The visible symptom was not an error page. The dashboard returned 200 with
 * its markup complete, but arrived with no stylesheet, no Alpine and no
 * Chart.js — so the KPI tiles, the finance chart and every interactive panel
 * rendered as unstyled, inert text. That is the "dashboard shows nothing
 * useful" report: the figures were in the HTML the whole time.
 *
 * The dev origins are therefore allowed, but ONLY under `local`. `testing`
 * and production keep the strict policy, so this cannot widen what ships —
 * which is also what SecurityHeadersCspTest asserts when it requires
 * `script-src` to contain no `http` origin at all.
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

        /*
         | The Vite bundle must carry the SAME per-request nonce.
         |
         | This policy declares `script-src … 'nonce-…'`. Per the CSP spec, the
         | presence of a nonce or hash makes 'unsafe-inline' IGNORED — browsers
         | say so explicitly in the violation message. So every <script> that does
         | not carry THIS request's nonce is refused, even in local dev.
         |
         | @vite emits `<script type="module" src=…>` with no nonce by default.
         | The file downloaded fine (200, correct MIME) and ran when imported by
         | hand, but the tag itself was blocked, so app.js never executed and
         | `window.Alpine` stayed undefined on every page. Everything Alpine-
         | driven was therefore inert: the confirm dialog's Cancel button, the
         | reminder popup, the mobile nav. The visible symptom was a confirm box
         | whose Cancel did nothing while the submit loader spun "Working…"
         | forever, because nothing had registered to clear it.
         |
         | It has to be set HERE rather than in a service provider: this
         | middleware is appended late, so its nonce is the one written into the
         | header. A provider reading app('csp.nonce') at boot gets a different
         | (or null) value, and the tag then carries a nonce the policy does not
         | recognise — which fails exactly the same way.
         */
        Vite::useCspNonce($nonce);

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $scriptSrc = "'self' 'unsafe-eval' 'nonce-{$nonce}'";

        // Dev-only escape hatch — see the class docblock. Never in production.
        if (app()->environment('local', 'testing')) {
            $scriptSrc .= " 'unsafe-inline'";
        }

        // The Vite dev server is a different origin, so `npm run dev` needs it
        // named explicitly or every asset it serves is blocked and the pages
        // render unstyled and inert. Scoped to `local` only: `testing` and
        // production must not carry a remote origin (SecurityHeadersCspTest
        // asserts script-src contains no `http`).
        //
        // Only the two hostnames are listed, deliberately. A bracketed IPv6
        // literal (`http://[::1]:5173`) is NOT a valid CSP source — the browser
        // rejects it outright with "contains an invalid source ... It will be
        // ignored", so it cannot be relied on even though Vite listens there.
        // `localhost` is listed alongside `127.0.0.1` because a browser may
        // resolve the former to either stack; when it resolves to ::1 the
        // request is made against the `localhost` origin, which IS allowed.
        $viteOrigins = '';
        $viteConnect = '';

        if (app()->environment('local')) {
            $viteOrigins = ' http://localhost:5173 http://127.0.0.1:5173';
            // HMR talks over a websocket, which `connect-src` governs.
            $viteConnect = ' ws://localhost:5173 ws://127.0.0.1:5173';
        }

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; "
            ."script-src {$scriptSrc}{$viteOrigins}; "
            ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com{$viteOrigins}; "
            ."font-src 'self' https://fonts.gstatic.com data:{$viteOrigins}; "
            ."img-src 'self' data:; "
            ."connect-src 'self'{$viteConnect}; "
            ."frame-ancestors 'none';"
        );

        return $response;
    }
}

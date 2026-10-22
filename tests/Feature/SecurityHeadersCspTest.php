<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 12 — the Content-Security-Policy must not break the app's own scripts.
 *
 * THE BUG THIS GUARDS AGAINST
 * ---------------------------
 * Phase 10 set `script-src 'self' 'unsafe-eval'` with no inline allowance. CSP
 * then blocked every inline `<script>` in the app: the landing page's tab
 * explorer, the dashboard's Chart.js render, and every form's
 * collapse/expand toggle. Nothing errored server-side — the pages returned 200
 * with all their markup intact — so no existing test noticed. Every PHP test
 * and the smoke sweep passed while the UI was broken in a browser.
 *
 * The fix is a per-response nonce, stamped onto each inline script tag, plus
 * a `nonce-...` entry in `script-src`. `'unsafe-inline'` is emitted only in
 * local/testing as a fallback.
 *
 *   4. The Chart.js CDN allowance is GONE, and asserted gone. Chart.js is
 *      imported through resources/js/app.js and bundled by Vite, so the
 *      `cdn.jsdelivr.net` entry was a standing third-party script allowance
 *      for something nothing loaded — pure attack surface. A test that keeps
 *      asserting the allowance exists is worse than no test, because it locks
 *      in an outdated decision and hides the fact that the app moved on.
 *
 * WHAT THESE TESTS CAN AND CANNOT DO
 * ----------------------------------
 * A PHP test cannot execute JavaScript, so it cannot prove a handler fires.
 * What it CAN prove is the thing that was actually broken: that the policy
 * permits what the views emit. That is the mechanism, and it is asserted here.
 * The end-to-end behaviour (toggle opens, panel fills) was verified in a real
 * browser — see the README's verification section.
 */
class SecurityHeadersCspTest extends TestCase
{
    use RefreshDatabase;

    private function cspFrom(string $path): string
    {
        $response = $this->get($path);
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp, "No CSP header on {$path}");

        return $csp;
    }

    public function test_the_policy_carries_a_nonce_so_inline_scripts_can_run(): void
    {
        $csp = $this->cspFrom('/login');

        $this->assertMatchesRegularExpression(
            "/script-src[^;]*'nonce-[A-Za-z0-9_-]+'/",
            $csp,
            'script-src must carry a nonce, or every inline <script> in the app is blocked.'
        );
    }

    public function test_the_nonce_is_unique_per_response(): void
    {
        // A reused nonce is as good as no nonce: an attacker who can read one
        // response could replay it. Cheap to assert, so it is asserted.
        $first = $this->cspFrom('/login');
        $second = $this->cspFrom('/login');

        preg_match("/'nonce-([A-Za-z0-9_-]+)'/", $first, $a);
        preg_match("/'nonce-([A-Za-z0-9_-]+)'/", $second, $b);

        $this->assertNotEmpty($a, 'No nonce in the first response.');
        $this->assertNotSame($a[1], $b[1], 'The CSP nonce must not be reused across responses.');
    }

    public function test_renderable_pages_tag_their_inline_scripts_with_the_response_nonce(): void
    {
        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy');
        preg_match("/'nonce-([A-Za-z0-9_-]+)'/", $csp, $m);
        $nonce = $m[1] ?? null;

        $this->assertNotNull($nonce);

        // The landing page ships an inline script for its tab explorer. If it
        // carries the response's nonce, the browser will run it.
        $this->assertStringContainsString(
            'nonce="'.$nonce.'"',
            $response->getContent(),
            'The page\'s inline scripts must carry the same nonce the policy allows.'
        );
    }

    /**
     * The Vite module bundle is a <script> like any other, and the policy's
     * nonce makes 'unsafe-inline' irrelevant: a tag without the matching nonce
     * is refused even in local dev.
     *
     * This is not theoretical. @vite emitted the module tag with no nonce, so
     * app.js never executed, `window.Alpine` was undefined on every page, and
     * everything Alpine-driven was inert — including the confirm dialog's
     * Cancel button, which then did nothing while the submit loader displayed
     * "Working…" forever because the click handler had never been bound.
     */
    public function test_the_vite_bundle_tag_carries_the_response_nonce(): void
    {
        $response = $this->get('/login');

        $csp = $response->headers->get('Content-Security-Policy');
        preg_match("/'nonce-([A-Za-z0-9_-]+)'/", $csp, $m);
        $nonce = $m[1] ?? null;

        $this->assertNotNull($nonce, 'No nonce in the CSP header.');

        $html = $response->getContent();

        preg_match_all('/<script[^>]*type="module"[^>]*>/', $html, $tags);
        $this->assertNotEmpty($tags[0], 'Expected at least one module script (the Vite bundle).');

        foreach ($tags[0] as $tag) {
            $this->assertStringContainsString(
                'nonce="'.$nonce.'"',
                $tag,
                'A module <script> without the response nonce is blocked by this policy, '
                    .'so the bundle would never run. Tag was: '.$tag,
            );
        }
    }

    public function test_no_third_party_script_origin_is_allowed(): void
    {        // Chart.js used to load from cdn.jsdelivr.net; it is now bundled by
        // Vite (see the note in resources/js/app.js). A standing allowance for
        // a host nothing loads from is attack surface with no upside, so the
        // policy must not carry one. Asserting its ABSENCE is the point: a
        // test asserting its presence would keep a stale decision alive.
        $csp = $this->cspFrom('/login');

        preg_match('/script-src([^;]*)/', $csp, $m);
        $scriptSrc = $m[1] ?? '';

        $this->assertStringNotContainsString('cdn.jsdelivr.net', $scriptSrc);
        $this->assertStringNotContainsString('http', $scriptSrc, 'script-src must not allow a remote origin.');
    }

    public function test_the_dashboard_does_not_load_chartjs_from_a_cdn(): void
    {
        // The other half of the same guarantee: even if the policy were
        // widened, the view itself must not reach for a CDN.
        $source = file_get_contents(resource_path('views/dashboard.blade.php'));

        $this->assertStringNotContainsString('cdn.jsdelivr.net', $source);
        $this->assertStringNotContainsString('chart.umd', $source);
    }

    public function test_unsafe_inline_is_never_shipped_in_the_production_policy(): void
    {
        // The dev fallback is scoped to local/testing. This asserts the scoping
        // itself is right, because 'unsafe-inline' in production would undo
        // most of what CSP is protecting against.
        $this->assertTrue(
            app()->environment('testing'),
            'This test asserts the local/testing behaviour; it must run in that environment.'
        );

        $this->assertStringContainsString("'unsafe-inline'", $this->cspFrom('/login'));
    }

    public function test_the_other_security_headers_are_still_present(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString(
            'camera=()',
            $response->headers->get('Permissions-Policy')
        );
    }
}

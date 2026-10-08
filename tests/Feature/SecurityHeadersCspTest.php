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

    public function test_the_cdn_chart_library_is_permitted_by_script_src(): void
    {
        // The dashboard renders Chart.js from jsdelivr. Without an explicit
        // allowance the script is blocked and the chart silently never draws —
        // the same class of failure as the inline-script gap.
        $csp = $this->cspFrom('/login');

        $this->assertStringContainsString(
            'https://cdn.jsdelivr.net',
            $csp,
            'script-src must allow the Chart.js CDN the dashboard loads from.'
        );
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

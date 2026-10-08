<?php

namespace Tests\Feature;

use App\Domains\PlatformHealth\Services\HealthCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HealthAndSecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The checks the endpoint is contractually required to report. Phase 10
     * shipped three (database, cache, queue); Phase 13 added the worker,
     * storage, scheduler and payment-configuration checks on top. This list is
     * the contract, so it is asserted by name rather than as a partial
     * structure that would silently accept the checks disappearing.
     */
    private const REQUIRED_CHECKS = [
        'database', 'cache', 'queue', 'queue_worker', 'storage', 'scheduler', 'payments',
    ];

    /**
     * Give every scheduled-process check the heartbeat it needs, so the only
     * thing this test is measuring is the code under test and not whether a
     * queue worker or scheduler happens to be running on the machine.
     *
     * Without this the endpoint returns 503 for reasons that are about the
     * environment, not the code — which is exactly what the test used to do.
     */
    private function seedRuntimeHeartbeats(): void
    {
        Cache::put('health:queue_worker_heartbeat', now()->toIso8601String(), now()->addMinutes(30));
        Cache::put('health:scheduler_heartbeat', now()->toIso8601String(), now()->addHour());
        Cache::put('health:last_run', now()->toIso8601String(), now()->addDay());
    }

    /**
     * Configure the payment gateway, so the `payments` check has something to
     * verify.
     *
     * Without this the check fails — correctly, because the webhook endpoint
     * really is unconfigured in the test environment. That failure is severable
     * (see config/health.php), but "severable" and "present" are different
     * things: this test is about the endpoint reporting each dependency, so the
     * dependency is configured rather than explained away.
     */
    private function configurePaymentGateway(): void
    {
        config()->set('services.payments.webhook_secret', 'test-webhook-secret');
    }

    public function test_health_endpoint_is_public_and_reports_each_dependency_independently(): void
    {
        $this->seedRuntimeHeartbeats();
        $this->configurePaymentGateway();

        $response = $this->getJson('/health.json');

        // The endpoint's rule is 200 when nothing is degrading, 503 when
        // something is. With every dependency satisfied that has to be a 200 —
        // and the status field has to agree with the code, because two sources
        // of truth that can disagree is the bug this pair of assertions exists
        // to prevent. A monitor reading `status` and a monitor reading the HTTP
        // code must never be told different things.
        //
        // NOTE the URL: `/health` is the human-readable HTML page (Phase 13)
        // and `/health.json` is the machine endpoint. Requesting the HTML page
        // with getJson() is how this test used to end up asserting "Invalid
        // JSON was returned" — the page was fine, the test asked the wrong one.
        $response->assertOk();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->json('status'));

        $checks = $response->json('checks');
        $this->assertIsArray($checks);

        foreach (self::REQUIRED_CHECKS as $check) {
            $this->assertArrayHasKey($check, $checks, "The /health contract requires a [{$check}] check.");
            $this->assertArrayHasKey('ok', $checks[$check]);
            $this->assertArrayHasKey('latency_ms', $checks[$check]);
        }

        // Each dependency is probed on its own. The database check is the one
        // that must genuinely pass with a working test connection — if it did
        // not, the rest of the suite could not run at all.
        $this->assertTrue($checks['database']['ok']);

        $this->assertArrayHasKey('open_incidents', $response->json());
        $this->assertArrayHasKey('timestamp', $response->json());

        // A read-only probe must not have written anything. GET /health is
        // public, so if viewing it recorded incidents a crawler would open them
        // by itself — the guarantee this asserts.
        $this->assertDatabaseCount('health_incidents', 0);
    }

    /**
     * The inverse of the above: when the endpoint does report 503, the status
     * field must agree with it. Two sources of truth that can disagree is the
     * bug this guards against — a monitor reading `status` and a monitor
     * reading the HTTP code must never be told different things.
     */
    public function test_health_status_field_always_agrees_with_the_http_code(): void
    {
        $this->seedRuntimeHeartbeats();
        $this->configurePaymentGateway();

        $response = $this->getJson('/health.json');

        $expected = $response->json('status') === 'ok' ? 200 : 503;
        $this->assertSame($expected, $response->getStatusCode());
    }

    /**
     * The other half of the relationship, proven rather than assumed: when a
     * severity-causing check fails, the endpoint must report 503 and say
     * `degraded`. Without this, the test above could pass on an endpoint that
     * reported 200 unconditionally.
     */
    public function test_a_severity_causing_failure_makes_the_endpoint_report_503(): void
    {
        $this->seedRuntimeHeartbeats();
        $this->configurePaymentGateway();

        // Break exactly one check, and leave everything else healthy.
        config()->set('health.force_severity.payments', true);
        config()->set('services.payments.webhook_secret', null);

        $this->getJson('/health.json')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.payments.ok', false);
    }

    /**
     * An unconfigured dependency must never make a dev machine report as
     * degraded. This is the behaviour a fresh install depends on: with no
     * payment credentials and no worker running, `/health` has to say healthy,
     * or every developer learns to ignore the status page.
     */
    public function test_a_local_environment_does_not_report_degraded_without_worker_or_payment_configuration(): void
    {
        // No heartbeats seeded on purpose — this is the fresh-install case.
        Cache::flush();
        config()->set('services.payments.webhook_secret', null);

        $service = app(HealthCheckService::class);

        $this->assertFalse($service->failureCounts('queue_worker'));
        $this->assertFalse($service->failureCounts('payments'));

        // The probe still reports the truth — it is the STATUS that is
        // unaffected, not the finding.
        $live = $service->runReadOnly();
        $this->assertFalse($live['results']['queue_worker']['ok']);

        $this->getJson('/health.json')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    /**
     * But the escape hatch in config/health.php restores Phase 10's stricter
     * behaviour, so a production deployment can still treat a missing webhook
     * secret as the billing outage it would be there — and the exemption is
     * per-check, not a global off switch.
     */
    public function test_severity_can_be_forced_on_per_check_through_configuration(): void
    {
        Cache::flush();
        config()->set('services.payments.webhook_secret', null);

        // With no override, local reports healthy — the default, proven above.
        $service = app(HealthCheckService::class);
        $this->assertFalse($service->failureCounts('payments'));

        // Forcing it ON makes this one check count despite being local.
        config()->set('health.force_severity.payments', true);
        $this->assertTrue($service->failureCounts('payments'));

        // ...and the check left un-forced still does not, so the override is
        // per-check rather than a global switch.
        $this->assertFalse($service->failureCounts('queue_worker'));
    }

    /**
     * Forcing severity on also flows through to the endpoint, which is the part
     * that actually matters: the 503 a monitor would see in production.
     */
    public function test_forcing_severity_makes_the_endpoint_report_degraded(): void
    {
        Cache::flush();
        config()->set('services.payments.webhook_secret', null);
        config()->set('health.force_severity.payments', true);

        $this->getJson('/health.json')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded');
    }

    public function test_security_headers_are_present_on_a_normal_response(): void
    {
        $response = $this->get('/health');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_hsts_header_is_only_set_on_an_https_request(): void
    {
        $plain = $this->get('/health');
        $this->assertNull($plain->headers->get('Strict-Transport-Security'));

        $secure = $this->get('https://localhost/health');
        $this->assertNotNull($secure->headers->get('Strict-Transport-Security'));
    }
}

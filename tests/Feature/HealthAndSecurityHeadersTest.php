<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthAndSecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_public_and_reports_each_dependency_independently(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk();
        $response->assertJsonStructure(['status', 'checks' => ['database', 'cache', 'queue'], 'timestamp']);
        $this->assertTrue($response->json('checks.database.ok'));
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

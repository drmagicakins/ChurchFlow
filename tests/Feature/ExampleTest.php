<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
/**
 * The public landing page, rendered against a real (empty) schema.
 *
 * This began as Laravel's scaffold ExampleTest asserting `GET /` returns
 * 200. That assertion is still worth keeping, but Phase 10 made `/` a real
 * page owned by LandingController, which queries the `plans` table — so the
 * test genuinely needs a database now. Without RefreshDatabase it passed
 * against whatever dev database happened to be lying around (or failed with
 * "no such table: plans" on a clean one): a test that could never fail
 * honestly for the right reason.
 *
 * LandingPageTest covers the page's actual behaviour (live plan data, the
 * empty-catalog fallback, CTA routing). This one only guards that `/` is up.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}

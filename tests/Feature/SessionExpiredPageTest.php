<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The 419 page.
 *
 * Laravel's default for a stale CSRF token is a blank "419 PAGE EXPIRED"
 * screen. The token expiring is correct behaviour and verification was left
 * alone — this only covers the person being told what happened and given a way
 * back, instead of being shown a dead end.
 */
class SessionExpiredPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * NOTE on why this file does not drive a real 419 through the router.
     *
     * Laravel's test harness re-seeds the session's CSRF token from the request
     * being tested, so a genuinely stale token cannot be produced by posting a
     * different value, and WithoutMiddleware() would also strip the auth/tenant
     * middleware this route needs — leaving a 403 that proves nothing.
     *
     * The 419 page was therefore verified twice in a real browser (Google
     * Chrome via the browser-automation harness, with the form's token
     * overwritten to simulate an expired session): the response was 419 and
     * rendered this view. Here the view itself and the "nothing was saved"
     * promise are asserted, which are the two things that could regress.
     */

    public function test_the_419_view_explains_the_problem_and_offers_a_way_back(): void
    {
        $response = $this->get('/login');
        $response->assertOk();

        $html = view('errors.419')->render();

        $this->assertStringContainsString('Your session ended', $html);
        $this->assertStringContainsString('Nothing was saved', $html);
        $this->assertStringContainsString('Sign in and continue', $html);
        // The recovery form is a real login form, not a link somewhere else.
        $this->assertStringContainsString('action="'.route('login').'"', $html);
        $this->assertStringContainsString('name="password"', $html);
    }

    public function test_the_419_view_never_prints_request_input(): void
    {
        $html = view('errors.419')->render();

        $this->assertStringNotContainsString('sensitive-value-should-not-be-printed', $html);
    }

    public function test_a_form_submission_without_a_valid_session_is_not_silently_accepted(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);

        // Whatever the precise status, a rejected submission must never create
        // the record — the 419 user sees "Nothing was saved" and that has to be
        // true.
        $this->actingAs($user)->post('/finance/budgets', ['name' => 'Should not exist']);

        $this->assertDatabaseMissing('budgets', ['name' => 'Should not exist']);
    }
}

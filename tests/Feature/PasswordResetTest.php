<?php

namespace Tests\Feature;

use App\Mail\PasswordResetMail;
use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Forgot / reset password, end to end.
 *
 * The full round trip is asserted as a journey, not as isolated endpoints:
 * ask on the sign-in page -> an email is actually SENT -> the link in that
 * email works -> the new password signs in -> the old one does not. Any of
 * those steps failing alone would still leave a person locked out.
 *
 * Two behaviours are asserted as deliberately BLANK responses, because their
 * absence is the feature:
 *
 *  - an unknown email must look identical to a known one (no directory), and
 *  - a used, expired or forged token must look identical to each other.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function church(): Church
    {
        $church = Church::factory()->create(['status' => 'active']);
        app()->instance('tenant.church_id', $church->id);

        return $church;
    }

    private function person(Church $church, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'church_id' => $church->id,
            'name' => 'Pastor Ada',
            'email' => 'ada@example.test',
            'password' => Hash::make('original-password-1'),
            'is_active' => true,
        ], $attributes));
    }

    /**
     * The token out of the emailed link, as a person's client would follow it.
     *
     * The token is a PATH SEGMENT (/reset-password/{token}), not a query
     * parameter — the route is declared that way. Parsing the path is also
     * what a mail client does when a person clicks, so the test follows the
     * same step the recipient does.
     */
    private function tokenFromEmail(PasswordResetMail $mail): string
    {
        $path = (string) parse_url($mail->url, PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));

        return (string) end($segments);
    }

    // --- the entry point ----------------------------------------------------

    public function test_the_sign_in_page_offers_a_forgot_password_link(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Forgot your password?')
            ->assertSee(route('password.request'), false);
    }

    public function test_the_forgot_password_page_loads_without_a_session(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Send reset link');
    }

    // --- the journey --------------------------------------------------------

    public function test_a_person_can_reset_their_password_from_the_emailed_link(): void
    {
        Mail::fake();
        $this->person($this->church());

        $this->post(route('password.email'), ['email' => 'ada@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status');

        // 1. An email was really sent to that address.
        Mail::assertSent(PasswordResetMail::class, fn ($mail) => $mail->hasTo('ada@example.test'));

        /** @var PasswordResetMail $sent */
        $sent = Mail::sent(PasswordResetMail::class)->first();
        $token = $this->tokenFromEmail($sent);

        $this->assertNotEmpty($token, 'The emailed link carried no token, so it could never work.');
        $this->assertStringContainsString('email=ada%40example.test', $sent->url);

        // 2. The link opens the reset page with the form, not the "dead link" notice.
        $this->get($sent->url)
            ->assertOk()
            ->assertSee('Save new password')
            ->assertDontSee('invalid or has expired');

        // 3. Submitting it changes the password.
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'ada@example.test',
            'password' => 'brand-new-password-2',
            'password_confirmation' => 'brand-new-password-2',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        // 4. The NEW password signs in and the OLD one does not.
        $this->assertTrue(Hash::check('brand-new-password-2', User::where('email', 'ada@example.test')->first()->password));
        $this->post(route('login'), ['email' => 'ada@example.test', 'password' => 'original-password-1'])
            ->assertSessionHasErrors('email');
        $this->post(route('login'), ['email' => 'ada@example.test', 'password' => 'brand-new-password-2'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_a_reset_changes_only_the_password_and_keeps_the_accounts_church_and_access(): void
    {
        Mail::fake();
        $church = $this->church();
        $user = $this->person($church, ['is_registrar' => true, 'organizational_unit_id' => null]);

        $this->post(route('password.email'), ['email' => 'ada@example.test']);
        $token = $this->tokenFromEmail(Mail::sent(PasswordResetMail::class)->first());

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'ada@example.test',
            'password' => 'brand-new-password-2',
            'password_confirmation' => 'brand-new-password-2',
        ]);

        $fresh = $user->fresh();
        $this->assertSame($church->id, $fresh->church_id, 'A reset moved the account to another church.');
        $this->assertTrue($fresh->is_registrar, 'A reset dropped the owner flag.');
        $this->assertTrue($fresh->is_active);
        $this->assertSame('Pastor Ada', $fresh->name);
    }

    // --- the email itself ---------------------------------------------------

    public function test_the_email_contains_working_html_and_plain_text_links(): void
    {
        Mail::fake();
        $this->person($this->church());

        $this->post(route('password.email'), ['email' => 'ada@example.test']);

        $mail = Mail::sent(PasswordResetMail::class)->first();
        $rendered = $mail->render();

        // The link a person clicks must be present in the HTML part...
        $this->assertStringContainsString(
            route('password.reset', ['token' => $this->tokenFromEmail($mail), 'email' => 'ada@example.test']),
            $rendered,
        );
        $this->assertStringContainsString('Set a new password', $rendered);
        // ...and the subject must say what it is.
        $this->assertSame('Set a new ChurchFlow password', $mail->subject);
        // A person who did not ask must be told they can ignore it.
        $this->assertStringContainsString('ignore this email', $rendered);
    }

    public function test_the_plain_text_part_is_not_raw_html(): void
    {
        Mail::fake();
        $this->person($this->church());

        $this->post(route('password.email'), ['email' => 'ada@example.test']);

        $mail = Mail::sent(PasswordResetMail::class)->first();

        // Laravel 12's Mailable has no renderText(); the text part is rendered
        // from its own view, which is the view the mailable declares via
        // ->text('emails.password-reset-text'). Rendering THAT view asserts the
        // same thing a mail client would receive.
        $plain = view('emails.password-reset-text', [
            'name' => 'Pastor Ada',
            'url' => $mail->url,
            'hours' => 24,
        ])->render();

        $this->assertStringNotContainsString('<html', $plain, 'The text/plain part contains markup, so some clients would show tags.');
        $this->assertStringNotContainsString('<a ', $plain, 'The text/plain part contains an HTML anchor.');
        $this->assertStringContainsString($mail->url, $plain, 'The plain-text part must carry the link in full.');
    }

    // --- what must NOT be revealed or allowed -------------------------------

    public function test_an_unknown_email_gets_the_same_answer_and_no_email(): void
    {
        Mail::fake();

        $response = $this->post(route('password.email'), ['email' => 'nobody@example.test']);

        $response->assertRedirect()->assertSessionHas('status');
        // The statement is identical to the known-address case: if it differed,
        // this page would be a directory of which emails have accounts.
        $this->assertSame(
            'If an account uses that email address, we have sent a link to set a new password. The link expires in one day.',
            session('status')
        );

        Mail::assertNothingSent();
    }

    public function test_an_inactive_account_cannot_be_reset(): void
    {
        Mail::fake();
        $this->person($this->church(), ['is_active' => false]);

        $this->post(route('password.email'), ['email' => 'ada@example.test'])->assertSessionHas('status');

        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_an_email_used_by_two_churches_is_not_guessed_at(): void
    {
        // users is unique on (church_id, email), not email alone. The token
        // table has no church_id, so issuing a link for "whichever row came
        // back first" could reset the WRONG account.
        Mail::fake();

        $first = Church::factory()->create(['status' => 'active']);
        $second = Church::factory()->create(['status' => 'active']);
        app()->instance('tenant.church_id', $first->id);
        $this->person($first);
        $this->person($second);

        $this->post(route('password.email'), ['email' => 'ada@example.test'])->assertSessionHas('status');

        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('password_reset_tokens')->count(), 'A reset link was issued for an ambiguous account.');
    }

    public function test_a_token_can_only_be_used_once(): void
    {
        Mail::fake();
        $this->person($this->church());
        $this->post(route('password.email'), ['email' => 'ada@example.test']);
        $token = $this->tokenFromEmail(Mail::sent(PasswordResetMail::class)->first());

        $payload = [
            'token' => $token,
            'email' => 'ada@example.test',
            'password' => 'brand-new-password-2',
            'password_confirmation' => 'brand-new-password-2',
        ];

        $this->post(route('password.update'), $payload)->assertRedirect(route('login'));

        // Replaying the same link must fail, and must not change the password
        // AGAIN to whatever the second request asks for.
        $this->post(route('password.update'), array_merge($payload, [
            'password' => 'attacker-password-3',
            'password_confirmation' => 'attacker-password-3',
        ]))->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('brand-new-password-2', User::where('email', 'ada@example.test')->first()->password));
    }

    public function test_an_expired_token_is_refused(): void
    {
        Mail::fake();
        $this->person($this->church());
        $this->post(route('password.email'), ['email' => 'ada@example.test']);
        $token = $this->tokenFromEmail(Mail::sent(PasswordResetMail::class)->first());

        // Move the clock past the configured lifetime (24 hours).
        $this->travel(25)->hours();

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'ada@example.test',
            'password' => 'brand-new-password-2',
            'password_confirmation' => 'brand-new-password-2',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('original-password-1', User::where('email', 'ada@example.test')->first()->password));
    }

    public function test_a_forged_token_is_refused(): void
    {
        $this->person($this->church());

        $this->post(route('password.update'), [
            'token' => str_repeat('a', 64),
            'email' => 'ada@example.test',
            'password' => 'brand-new-password-2',
            'password_confirmation' => 'brand-new-password-2',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('original-password-1', User::where('email', 'ada@example.test')->first()->password));
    }

    public function test_the_stored_token_is_a_hash_not_the_token_itself(): void
    {
        Mail::fake();
        $this->person($this->church());
        $this->post(route('password.email'), ['email' => 'ada@example.test']);
        $token = $this->tokenFromEmail(Mail::sent(PasswordResetMail::class)->first());

        $stored = DB::table('password_reset_tokens')->where('email', 'ada@example.test')->value('token');

        $this->assertNotSame($token, $stored, 'The raw token was stored, so a database dump could be replayed into a password change.');
        $this->assertSame(hash('sha256', $token), $stored);
    }

    public function test_a_dead_link_says_so_on_arrival(): void
    {
        $this->person($this->church());

        $this->get(route('password.reset', ['token' => 'not-a-real-token', 'email' => 'ada@example.test']))
            ->assertOk()
            ->assertSee('invalid or has expired')
            // The form is not offered for a dead link: it would only fail one
            // step later, after the person had typed a password twice.
            ->assertDontSee('Save new password');
    }

    public function test_requesting_a_reset_is_throttled(): void
    {
        Mail::fake();
        $this->person($this->church());

        // The limit is 3 per address per 15 minutes.
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('password.email'), ['email' => 'ada@example.test'])->assertSessionHas('status');
        }

        $this->post(route('password.email'), ['email' => 'ada@example.test'])
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString('Too many reset requests', session('errors')->first('email'));
    }

    public function test_a_successful_reset_ends_the_old_sessions(): void
    {
        // A reset exists to lock someone out of an account they reached with
        // the old password; leaving those sessions alive would defeat it.
        Mail::fake();
        $user = $this->person($this->church());

        $this->post(route('password.email'), ['email' => 'ada@example.test']);
        $token = $this->tokenFromEmail(Mail::sent(PasswordResetMail::class)->first());

        DB::table('sessions')->insert([
            'id' => 'old-session', 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'test', 'payload' => 'x', 'last_activity' => now()->timestamp,
        ]);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'ada@example.test',
            'password' => 'brand-new-password-2',
            'password_confirmation' => 'brand-new-password-2',
        ]);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }
}

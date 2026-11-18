<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureEmailIsVerified;
use App\Mail\VerifyEmailMail;
use App\Models\Church;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The standard email-verification flow, for the church owner and parish pastor.
 *
 * Each test pins one promise and is written so that removing the feature turns
 * it red:
 *   - an unconfirmed CHURCH account is stopped at the dashboard;
 *   - the owner and pastor CAN confirm, through the real signed link;
 *   - a NEW signup with no church is not blocked (the pay-first journey);
 *   - a LEGACY church account is offered a choice, not locked out;
 *   - the link is signed, single-account and refuses tampering;
 *   - the message is a branded mail, not Laravel's bare default.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function church(): Church
    {
        return Church::factory()->create();
    }

    /** A church owner: registrar flag + the Church Owner role, as activation sets it. */
    private function owner(Church $church, array $attributes = []): User
    {
        // A church may hold only ONE role of a given name (the database enforces
        // it), so a second owner in the same church — which a test needs to prove
        // that a link for someone else is refused — reuses that role instead of
        // inserting a duplicate.
        $role = Role::withoutGlobalScopes()->firstOrCreate(
            ['church_id' => $church->id, 'name' => 'Church Owner'],
        );
        $role->permissions()->sync([]);

        $user = User::factory()->create(array_merge([
            'church_id' => $church->id,
            'is_registrar' => true,
            'is_active' => true,
            'email_verified_at' => null,
        ], $attributes));

        $user->roles()->attach($role);

        return $user;
    }

    private function pastor(Church $church, array $attributes = []): User
    {
        RolePermissionSeeder::ensureParishPastorRole($church->id);
        $role = Role::withoutGlobalScopes()
            ->where('church_id', $church->id)
            ->where('name', RolePermissionSeeder::PARISH_PASTOR_ROLE)
            ->firstOrFail();

        $user = User::factory()->create(array_merge([
            'church_id' => $church->id,
            'is_active' => true,
            'email_verified_at' => null,
        ], $attributes));

        $user->roles()->attach($role);

        return $user;
    }

    /** The signed link Laravel's MustVerifyEmail produces for a user. */
    private function linkFor(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );
    }

    // --- the gate -----------------------------------------------------------

    public function test_an_unconfirmed_church_owner_is_sent_to_the_verification_page(): void
    {
        $church = $this->church();
        $owner = $this->owner($church);

        $this->actingAs($owner)
            ->get('/dashboard')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_an_unconfirmed_parish_pastor_is_sent_to_the_verification_page(): void
    {
        $church = $this->church();
        $pastor = $this->pastor($church);

        $this->actingAs($pastor)
            ->get('/dashboard')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_a_confirmed_owner_reaches_the_dashboard(): void
    {
        $church = $this->church();
        $owner = $this->owner($church, ['email_verified_at' => now()]);

        $this->actingAs($owner)->get('/dashboard')->assertOk();
    }

    public function test_a_signup_with_no_church_yet_is_not_blocked(): void
    {
        // The pay-first journey: no church means nothing to protect, and blocking
        // here would break signup. Asserted against the GATE itself (the thing
        // that decides), not a particular screen — the plans page needs seeded
        // plans and has nothing to do with this rule.
        $registrant = User::factory()->create(['church_id' => null, 'email_verified_at' => null]);

        $this->assertTrue(EnsureEmailIsVerified::mayUseTheApp($registrant));

        // ...and the middleware agrees, by letting the request through to the
        // route instead of redirecting it to the verification page.
        $response = $this->actingAs($registrant)->get('/dashboard');
        $this->assertNotSame(route('verification.notice'), $response->headers->get('Location'));
    }

    public function test_a_legacy_church_account_can_defer_and_keep_working(): void
    {
        $church = $this->church();

        // Created BEFORE verification shipped — the shape every existing church
        // had until today. It must not be locked out of its own dashboard.
        $legacy = $this->owner($church);
        $legacy->forceFill(['created_at' => EnsureEmailIsVerified::verificationShippedAt()->subMonth()])->saveQuietly();

        $this->actingAs($legacy)
            ->get('/dashboard')
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($legacy)
            ->post(route('verification.defer'))
            ->assertRedirect();

        $this->actingAs($legacy)->get('/dashboard')->assertOk();
    }

    public function test_a_new_account_cannot_skip_verification(): void
    {
        $church = $this->church();
        $owner = $this->owner($church); // created now → after the cut-off

        $this->actingAs($owner)->post(route('verification.defer'))->assertForbidden();

        $this->actingAs($owner)->get('/dashboard')->assertRedirect(route('verification.notice'));
    }

    // --- the flow works -----------------------------------------------------

    public function test_the_signed_link_confirms_the_address_and_lets_them_in(): void
    {
        $church = $this->church();
        $owner = $this->owner($church);

        $this->actingAs($owner)
            ->get($this->linkFor($owner))
            ->assertRedirect();

        $this->assertNotNull($owner->fresh()->email_verified_at);

        $this->actingAs($owner->fresh())->get('/dashboard')->assertOk();
    }

    public function test_the_parish_pastor_confirms_through_the_same_link(): void
    {
        $church = $this->church();
        $pastor = $this->pastor($church);

        $this->actingAs($pastor)->get($this->linkFor($pastor))->assertRedirect();

        $this->assertNotNull($pastor->fresh()->email_verified_at);
        $this->actingAs($pastor->fresh())->get('/dashboard')->assertOk();
    }

    public function test_clicking_the_link_twice_is_not_an_error(): void
    {
        $church = $this->church();
        $owner = $this->owner($church);
        $link = $this->linkFor($owner);

        $this->actingAs($owner)->get($link)->assertRedirect();
        $this->actingAs($owner->fresh())->get($link)->assertRedirect(); // mail clients prefetch

        $this->assertNotNull($owner->fresh()->email_verified_at);
    }

    // --- the link cannot be forged or misused -------------------------------

    public function test_an_unsigned_link_is_refused(): void
    {
        $church = $this->church();
        $owner = $this->owner($church);

        $this->actingAs($owner)
            ->get("/email/verify/{$owner->id}/".sha1($owner->email))
            ->assertForbidden();

        $this->assertNull($owner->fresh()->email_verified_at);
    }

    public function test_a_link_belonging_to_another_account_is_refused(): void
    {
        $church = $this->church();
        $owner = $this->owner($church);
        $other = $this->owner($church, ['email' => 'someone-else@example.test', 'is_registrar' => false]);

        // Signed for $other, used while signed in as $owner.
        $this->actingAs($owner)->get($this->linkFor($other))->assertForbidden();

        $this->assertNull($other->fresh()->email_verified_at);
    }

    public function test_the_link_stops_working_once_the_address_changes(): void
    {
        $church = $this->church();
        $owner = $this->owner($church);
        $link = $this->linkFor($owner);

        $owner->forceFill(['email' => 'moved@example.test'])->save();

        $this->actingAs($owner->fresh())->get($link)->assertForbidden();
        $this->assertNull($owner->fresh()->email_verified_at);
    }

    // --- the message --------------------------------------------------------

    public function test_registration_sends_the_confirmation_message(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Ada Owner',
            'email' => 'ada@example.test',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
        ])->assertRedirect(route('plans.index'));

        Notification::assertSentTo(
            User::where('email', 'ada@example.test')->firstOrFail(),
            \App\Notifications\VerifyChurchEmail::class,
        );
    }

    public function test_the_confirmation_message_is_the_branded_mail_not_the_bare_default(): void
    {
        $church = $this->church();
        $owner = $this->owner($church);

        $url = $this->linkFor($owner);
        $html = (new VerifyEmailMail($owner, $url))->render();

        // Carries the church, the action, and the absolute signed link...
        $this->assertStringContainsString($church->name, $html);
        $this->assertStringContainsString('Confirm email address', $html);
        // The church's monogram when no logo has been uploaded — the branded
        // frame's fallback, which is what a church sees before it adds one.
        $this->assertStringContainsString('font-weight:700;line-height:48px', $html);
        $this->assertStringContainsString(e($url), $html);
        // ...and NOT the framework's placeholder greeting.
        $this->assertStringNotContainsString('Verify Email Address', $html);
    }

    public function test_resending_uses_the_same_standard_notification(): void
    {
        Notification::fake();

        $church = $this->church();
        $owner = $this->owner($church);

        // The resend route is inside the authenticated group but NOT behind the
        // gate (the gate would loop), so it is reachable unconfirmed.
        $this->actingAs($owner)
            ->post(route('verification.send'))
            ->assertRedirect();

        Notification::assertSentTo($owner, \App\Notifications\VerifyChurchEmail::class);
    }

    public function test_a_confirmed_account_is_told_there_is_nothing_to_resend(): void
    {
        Notification::fake();

        $church = $this->church();
        $owner = $this->owner($church, ['email_verified_at' => now()]);

        $this->actingAs($owner)->post(route('verification.send'))->assertRedirect();

        Notification::assertNothingSent();
    }
}

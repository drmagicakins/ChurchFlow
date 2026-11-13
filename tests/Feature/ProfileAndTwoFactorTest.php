<?php

namespace Tests\Feature;

use App\Domains\Security\Services\TwoFactorService;
use App\Models\Church;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The account menu: profile picture, password, two-step authentication — and
 * the notification bell's feed.
 *
 * The rules these tests protect:
 *  1. Every action is SELF-SERVICE. Posting somebody else's data must change
 *     nothing about them.
 *  2. A password change requires the CURRENT password — a borrowed session
 *     must not be able to take an account over.
 *  3. Two-step is only "on" once it has been PROVED with a valid code, so an
 *     abandoned setup can never lock its owner out.
 *  4. A wrong code never signs anyone in.
 */
class ProfileAndTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $church = Church::factory()->create(['status' => 'active']);
        app()->instance('tenant.church_id', $church->id);

        $plan = Plan::factory()->create();

        Subscription::create([
            'church_id' => $church->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        return User::factory()->create([
            'church_id' => $church->id,
            'email' => 'pastor@example.test',
            'password' => 'Secret12345',
        ]);
    }

    private function totp(): TwoFactorService
    {
        return app(TwoFactorService::class);
    }

    // --- profile picture ----------------------------------------------------

    public function test_a_user_can_upload_a_profile_picture(): void
    {
        Storage::fake('private');

        $user = $this->user();

        $response = $this->actingAs($user)->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->image('me.jpg', 200, 200),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Profile picture updated.');

        $user->refresh();

        $this->assertNotNull($user->avatar_upload_id);
        $this->assertNotNull($user->avatarUrl(), 'The picture must be served through a signed URL.');
    }

    public function test_uploading_a_second_picture_replaces_the_first_instead_of_orphaning_it(): void
    {
        Storage::fake('private');

        $user = $this->user();

        $this->actingAs($user)->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->image('one.jpg', 100, 100),
        ]);

        $first = $user->fresh()->avatar_upload_id;

        $this->actingAs($user)->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->image('two.jpg', 100, 100),
        ]);

        $second = $user->fresh()->avatar_upload_id;

        $this->assertNotSame($first, $second);
        $this->assertDatabaseMissing('uploaded_files', ['id' => $first]);
    }

    public function test_a_non_image_is_refused(): void
    {
        Storage::fake('private');

        $user = $this->user();

        $this->actingAs($user)->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->create('evil.php', 10, 'text/x-php'),
        ])->assertSessionHasErrors('photo');

        $this->assertNull($user->fresh()->avatar_upload_id);
    }

    public function test_a_user_can_remove_their_picture_and_falls_back_to_initials(): void
    {
        Storage::fake('private');

        $user = $this->user();
        // A KNOWN name: the factory invents one per user, so asserting on
        // initials without setting the name tests the factory, not initials().
        $user->forceFill(['name' => 'Pastor Okafor'])->save();

        $this->actingAs($user)->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->image('me.jpg'),
        ]);

        $this->actingAs($user)->delete(route('profile.photo.destroy'));

        $user->refresh();

        $this->assertNull($user->avatar_upload_id);
        $this->assertNull($user->avatarUrl());
        $this->assertSame('PO', $user->initials()); // "Pastor Okafor" -> PO
    }

    // --- password -----------------------------------------------------------

    public function test_a_password_change_requires_the_current_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('profile.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'BrandNew12345',
            'password_confirmation' => 'BrandNew12345',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('Secret12345', $user->fresh()->password), 'The password must not change.');
    }

    public function test_a_password_change_with_the_right_current_password_succeeds(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('profile.password.update'), [
            'current_password' => 'Secret12345',
            'password' => 'BrandNew12345',
            'password_confirmation' => 'BrandNew12345',
        ])->assertSessionHas('status', 'Your password has been changed.');

        $this->assertTrue(Hash::check('BrandNew12345', $user->fresh()->password));
    }

    public function test_a_password_change_requires_confirmation_and_length(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('profile.password.update'), [
            'current_password' => 'Secret12345',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('Secret12345', $user->fresh()->password));
    }

    // --- two-step authentication -------------------------------------------

    public function test_two_step_is_not_on_until_a_code_is_confirmed(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('profile.two-factor.enable'));

        $user->refresh();

        $this->assertNotEmpty($user->two_factor_secret, 'The secret is generated.');
        $this->assertFalse($user->hasTwoFactorEnabled(), 'But an UNCONFIRMED setup must not count as on.');

        // And crucially: signing in still works normally.
        auth()->logout();
        $this->post('/login', ['email' => 'pastor@example.test', 'password' => 'Secret12345'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_a_wrong_code_does_not_confirm_the_setup(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post(route('profile.two-factor.enable'));

        $this->actingAs($user->fresh())->post(route('profile.two-factor.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_a_valid_code_confirms_the_setup_and_returns_recovery_codes(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post(route('profile.two-factor.enable'));

        $user->refresh();
        $code = $this->totp()->currentCode((string) $user->two_factor_secret);

        $this->actingAs($user)->post(route('profile.two-factor.confirm'), ['code' => $code])
            ->assertSessionHas('status', 'Two-step authentication is now on.')
            ->assertSessionHas('recovery_codes');

        $user->refresh();

        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertCount(10, $this->totp()->storedRecoveryHashes($user));
    }

    public function test_with_two_step_on_a_correct_password_alone_does_not_sign_in(): void
    {
        $user = $this->user();
        $user->forceFill([
            'two_factor_secret' => $this->totp()->generateSecret(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->post('/login', ['email' => 'pastor@example.test', 'password' => 'Secret12345'])
            ->assertRedirect(route('two-factor.login'));

        $this->assertGuest('web');
    }

    public function test_the_second_step_signs_in_with_a_valid_code(): void
    {
        $user = $this->user();
        $user->forceFill([
            'two_factor_secret' => $this->totp()->generateSecret(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->post('/login', ['email' => 'pastor@example.test', 'password' => 'Secret12345']);

        $this->post('/two-factor', [
            'code' => $this->totp()->currentCode((string) $user->fresh()->two_factor_secret),
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user->fresh(), 'web');
    }

    public function test_a_wrong_code_at_the_second_step_does_not_sign_in(): void
    {
        $user = $this->user();
        $user->forceFill([
            'two_factor_secret' => $this->totp()->generateSecret(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->post('/login', ['email' => 'pastor@example.test', 'password' => 'Secret12345']);

        $this->post('/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');

        $this->assertGuest('web');
    }

    public function test_the_second_step_refuses_when_no_challenge_is_pending(): void
    {
        $this->post('/two-factor', ['code' => '123456'])->assertStatus(403);
    }

    public function test_a_recovery_code_works_once_and_then_is_dead(): void
    {
        $user = $this->user();

        $secret = $this->totp()->generateSecret();
        $user->forceFill(['two_factor_secret' => $secret])->save();
        $codes = $this->totp()->confirm($user);
        $user->refresh();

        // First use: accepted.
        $this->post('/login', ['email' => 'pastor@example.test', 'password' => 'Secret12345']);
        $this->post('/two-factor', ['recovery_code' => $codes[0]])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh(), 'web');

        // Second use of the SAME code: refused.
        auth()->logout();
        $this->post('/login', ['email' => 'pastor@example.test', 'password' => 'Secret12345']);
        $this->post('/two-factor', ['recovery_code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertGuest('web');
    }

    public function test_disabling_two_step_requires_the_password(): void
    {
        $user = $this->user();
        $user->forceFill([
            'two_factor_secret' => $this->totp()->generateSecret(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user)->post(route('profile.two-factor.disable'), ['current_password' => 'nope'])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled(), 'A wrong password must not weaken the account.');
    }

    public function test_the_secret_and_recovery_codes_are_never_serialised(): void
    {
        $user = $this->user();
        $secret = $this->totp()->generateSecret();
        $user->forceFill(['two_factor_secret' => $secret])->save();
        $this->totp()->confirm($user);

        $json = $user->fresh()->toJson();

        $this->assertStringNotContainsString($secret, $json);
        $this->assertStringNotContainsString('two_factor_secret', $json);
        $this->assertStringNotContainsString('two_factor_recovery_codes', $json);
    }

    // --- the bell's feed ----------------------------------------------------

    public function test_the_notification_feed_returns_only_the_callers_items(): void
    {
        $me = $this->user();
        $someoneElse = User::factory()->create(['church_id' => $me->church_id]);

        \App\Models\AppNotification::create([
            'user_id' => $me->id, 'title' => 'Mine', 'body' => 'hello', 'type' => 'test',
        ]);
        \App\Models\AppNotification::create([
            'user_id' => $someoneElse->id, 'title' => 'Theirs', 'body' => 'secret', 'type' => 'test',
        ]);

        $response = $this->actingAs($me)->getJson(route('notifications.feed'))->assertOk();

        $response->assertJsonPath('unread', 1);
        $response->assertJsonCount(1, 'items');
        $response->assertJsonPath('items.0.title', 'Mine');
        $this->assertStringNotContainsString('Theirs', $response->getContent());
    }

    public function test_a_guest_cannot_read_the_feed(): void
    {
        $this->getJson(route('notifications.feed'))->assertUnauthorized();
    }

    // --- the gateway is untouched by any of this ---------------------------

    public function test_the_suite_still_uses_the_network_free_gateway(): void
    {
        // Guards the phpunit.xml isolation documented in SESSION-HANDOFF §1p.
        $this->assertInstanceOf(
            \App\Domains\Subscriptions\Gateways\NullPaymentGateway::class,
            app(\App\Domains\Subscriptions\Gateways\PaymentGatewayInterface::class),
        );
    }
}

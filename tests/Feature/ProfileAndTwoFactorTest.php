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

    /**
     * REGRESSION: the `max:` rule is measured in KILOBYTES for a file, and the
     * profile form advertised 5MB. Two separate defects made a perfectly good
     * multi-megabyte photo fail with "The photo failed to upload.":
     *
     *  1. it was passed maxUploadMb() (= 5), i.e. a FIVE KILOBYTE ceiling; and
     *  2. PHP's own upload_max_filesize (2M by default) discarded the file
     *     before Laravel ever saw it.
     *
     * A 1KB image passes a 5KB limit, so the original single-picture test was
     * green throughout both bugs. This one uses a file big enough to catch them.
     */
    public function test_a_realistic_multi_megabyte_photo_is_accepted(): void
    {
        Storage::fake('private');

        $user = $this->user();

        // A REAL 1.5MB file: UploadedFile::fake()->image()->size() writes only a
        // few dozen bytes and pads the reported size, so the multipart upload
        // would arrive tiny and prove nothing about the size ceiling.
        $path = tempnam(sys_get_temp_dir(), 'bigjpg');
        file_put_contents($path, $this->jpegOfSize(1500 * 1024));

        $this->actingAs($user)->post(route('profile.photo.update'), [
            'photo' => new UploadedFile($path, 'holiday.jpg', 'image/jpeg', null, true),
        ])->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertNotNull($user->avatar_upload_id, 'A 1.5MB photo must upload, not be refused as "too large".');

        $stored = \App\Models\UploadedFile::withoutGlobalScopes()->find($user->avatar_upload_id);
        $this->assertGreaterThan(1024 * 1024, $stored->size_bytes, 'The stored file must really be the multi-megabyte one.');

        @unlink($path);
    }

    /**
     * A genuinely valid JPEG of exactly $bytes bytes, cheaply.
     *
     * A small 8x8 image is encoded, then a COM (comment) segment of the needed
     * length is inserted straight after SOI and BEFORE the first frame header —
     * the order that keeps it a JPEG getimagesize() and fileinfo still accept.
     * Padding this way costs no bitmap memory, unlike a huge canvas.
     */
    private function jpegOfSize(int $bytes): string
    {
        $img = imagecreatetruecolor(8, 8);
        imagefilledrectangle($img, 0, 0, 8, 8, imagecolorallocate($img, 200, 40, 40));

        ob_start();
        imagejpeg($img, null, 90);
        $jpeg = ob_get_clean();
        imagedestroy($img);

        $pad = max(0, $bytes - strlen($jpeg) - 4);

        // A COM payload above 65533 bytes needs more than one segment.
        $comments = '';
        while ($pad > 0) {
            $chunk = min($pad, 65533);
            $comments .= chr(0xFF).chr(0xFE).pack('n', 2 + $chunk).str_repeat('A', $chunk);
            $pad -= $chunk;
        }

        return substr($jpeg, 0, 2).$comments.substr($jpeg, 2);
    }

    /**
     * The ceiling the screen and the rule agree on is in KILOBYTES, and it is
     * at least 1 MB — the bug made it 5 KB while the hint said "5MB".
     */
    public function test_the_advertised_upload_limit_is_in_kilobytes_and_at_least_one_megabyte(): void
    {
        $kb = \App\Domains\Files\Services\FileUploadService::maxUploadKb();

        $this->assertGreaterThanOrEqual(1024, $kb, 'A limit below 1MB is the 5-kilobyte bug returning.');
        $this->assertSame(
            \App\Domains\Files\Services\FileUploadService::maxUploadBytes(),
            $kb * 1024,
            'maxUploadKb() and maxUploadBytes() must describe the same ceiling.'
        );
    }

    /**
     * The ceiling must never exceed what PHP ITSELF will accept. A server left
     * at PHP's default upload_max_filesize (2M) discards anything larger before
     * the request reaches Laravel, so promising 5MB there guarantees a failure
     * that no validation message could explain.
     */
    public function test_the_advertised_limit_never_exceeds_the_servers_own_upload_limit(): void
    {
        $phpLimit = \App\Domains\Files\Services\FileUploadService::phpUploadLimitBytes();

        if ($phpLimit === null) {
            $this->markTestSkipped('This PHP build reports no upload_max_filesize ceiling.');
        }

        $this->assertLessThanOrEqual(
            $phpLimit,
            \App\Domains\Files\Services\FileUploadService::maxUploadBytes(),
            'The app must not advertise an upload size PHP will silently discard.'
        );
    }

    /**
     * REGRESSION: a server with no usable upload temp directory must be reported
     * as a SERVER fault, not as a bad photo.
     *
     * The reported symptom was "That photo could not be uploaded…" for EVERY
     * image, including a 300 KB one, because PHP had no writable temp dir
     * (UPLOAD_ERR_NO_TMP_DIR). `isValid()` alone cannot tell that apart from a
     * corrupt file, and blaming the photo sends the person round in circles.
     */
    public function test_a_server_with_no_upload_temp_directory_is_reported_as_a_server_fault(): void
    {
        Storage::fake('private');

        $user = $this->user();

        $path = tempnam(sys_get_temp_dir(), 'notmp');
        file_put_contents($path, $this->jpegOfSize(2048));

        // A file claiming UPLOAD_ERR_NO_TMP_DIR is exactly what PHP hands Laravel
        // when its temp directory is unusable.
        $file = new UploadedFile($path, 'me.jpg', 'image/jpeg', UPLOAD_ERR_NO_TMP_DIR, true);

        $this->assertSame(UPLOAD_ERR_NO_TMP_DIR, $file->getError());

        try {
            app(\App\Domains\Files\Services\FileUploadService::class)
                ->storeImage($file, $user->church, $user, $user);

            $this->fail('An upload with no temp directory must be refused, not stored.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(500, $e->getStatusCode());
            $this->assertStringContainsString('temporary directory', $e->getMessage());
            $this->assertStringContainsString('upload_tmp_dir', $e->getMessage());
            $this->assertStringNotContainsString('corrupted', $e->getMessage());
        }

        @unlink($path);
    }

    /**
     * The serve command must give the PHP server a writable temp directory.
     *
     * `php artisan serve` strips TEMP/TMP from the child environment, and on
     * Windows sys_get_temp_dir() then resolves to C:\\Windows, which is not
     * writable — every upload failed. App\\Console\\Commands\\ServeCommand exists
     * to prevent that, and this proves its chosen directory is real and writable.
     */
    public function test_the_serve_command_provides_a_writable_upload_temp_directory(): void
    {
        $dir = storage_path('app/upload-tmp');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $this->assertDirectoryExists($dir);
        $this->assertTrue(is_writable($dir), 'The upload temp directory must be writable or PHP cannot receive a file.');

        // The server PHP would otherwise fall back to on Windows.
        $this->assertNotSame('C:\\Windows', $dir);

        $this->assertTrue(
            is_subclass_of(\App\Console\Commands\ServeCommand::class, \Illuminate\Foundation\Console\ServeCommand::class),
            'The app serve command must still be a real Laravel serve command.'
        );
    }

    /**
     * A photo above the ceiling is refused with a message that names the REAL
     * limit — not Laravel's generic "The photo failed to upload.", which reads
     * as a corrupt file and gives the person nothing to act on.
     */
    public function test_an_oversized_photo_is_refused_with_the_actual_limit_named(): void
    {
        Storage::fake('private');

        $user = $this->user();

        // A real file just over the ceiling. UploadedFile::fake()->image() with a
        // huge canvas allocates a true-colour bitmap (4000x4000 is ~64MB), which
        // is enough to exhaust the suite's memory limit — so the bytes are built
        // directly instead.
        $tooBig = \App\Domains\Files\Services\FileUploadService::maxUploadKb() + 64;
        $path = tempnam(sys_get_temp_dir(), 'hugejpg');
        file_put_contents($path, $this->jpegOfSize($tooBig * 1024));

        $response = $this->actingAs($user)->post(route('profile.photo.update'), [
            'photo' => new UploadedFile($path, 'huge.jpg', 'image/jpeg', null, true),
        ]);

        @unlink($path);

        $response->assertSessionHasErrors('photo');

        $message = session('errors')->first('photo');

        $this->assertStringNotContainsString('failed to upload', $message, 'The generic message hides the cause.');
        $this->assertStringContainsString(\App\Domains\Files\Services\FileUploadService::maxUploadMb().'MB', $message);

        $this->assertNull($user->fresh()->avatar_upload_id);
    }

    /**
     * A parish pastor is not the owner and holds only their own permissions —
     * the profile picture is self-service, so the SAME path must work for them.
     */
    public function test_a_parish_pastor_can_upload_a_profile_picture_too(): void
    {
        Storage::fake('private');

        $owner = $this->user();

        $type = \App\Models\UnitType::create([
            'church_id' => $owner->church_id,
            'name' => 'Branch',
            'level' => 1,
        ]);

        $unit = \App\Models\OrganizationalUnit::create([
            'church_id' => $owner->church_id,
            'unit_type_id' => $type->id,
            'name' => 'Grace Parish',
        ]);

        $pastor = User::factory()->create([
            'church_id' => $owner->church_id,
            'organizational_unit_id' => $unit->id,
            'email' => 'parish-pastor@example.test',
        ]);

        $this->assertTrue($pastor->isParishScoped(), 'The fixture must really be a parish-scoped pastor.');

        $this->actingAs($pastor)->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->image('me.jpg', 400, 400),
        ])->assertSessionHas('status', 'Profile picture updated.');

        $this->assertNotNull($pastor->fresh()->avatar_upload_id);
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

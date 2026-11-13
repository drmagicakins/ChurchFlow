<?php

namespace App\Http\Controllers;

use App\Domains\Files\Services\FileUploadService;
use App\Domains\Security\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * The signed-in person's own account: photo, password and two-step
 * authentication.
 *
 * EVERY ACTION HERE IS SELF-SERVICE — it operates on $request->user() and on
 * nothing else. There is deliberately no {user} parameter: an account's own
 * password and second factor are not an administrative power, so no permission
 * or role grants them over somebody else. A parish pastor and the church owner
 * use the identical path, which is what makes it safe to put in the header
 * dropdown for every role.
 *
 * Nothing here can touch church_id, organizational_unit_id, roles or
 * permissions, so a person cannot promote themselves by posting extra fields.
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly FileUploadService $uploads,
        private readonly TwoFactorService $twoFactor,
    ) {}

    /** Upload or replace the profile picture (reuses the member-photo storage path). */
    public function updatePhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'image', 'max:5120'],
        ]);

        $user = $request->user();

        // A personal photo belongs to the same church/tenant store as every
        // other upload, so the plan's storage quota applies unchanged. The
        // church may legitimately be null for a not-yet-activated signup, and
        // that person has no dropdown to upload from in the first place.
        abort_if($user->church_id === null, 409, 'Your account is not attached to a church yet.');

        $uploaded = $this->uploads->storeImage(
            $request->file('photo'),
            $user->church,
            $user,
            $user,
        );

        $previous = $user->avatar_upload_id;

        $user->forceFill(['avatar_upload_id' => $uploaded->id])->save();

        // Replacing a photo leaves the old file orphaned, which would keep
        // counting against the church's quota for ever. Delete it only AFTER
        // the new id is saved, so a failure cannot leave the user with no photo.
        if ($previous) {
            $old = \App\Models\UploadedFile::withoutGlobalScopes()->find($previous);

            if ($old && $old->id !== $uploaded->id) {
                $this->uploads->delete($old);
            }
        }

        // Clear the cached relation so the header shows the new picture on the
        // redirect that follows.
        $user->unsetRelation('avatarUpload');

        return back()->with('status', 'Profile picture updated.');
    }

    /** Remove the picture; the person falls back to their initials. */
    public function destroyPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->avatar_upload_id) {
            return back()->with('status', 'You have no profile picture to remove.');
        }

        $previous = $user->avatar_upload_id;

        $user->forceFill(['avatar_upload_id' => null])->save();
        $user->unsetRelation('avatarUpload');

        $old = \App\Models\UploadedFile::withoutGlobalScopes()->find($previous);

        if ($old) {
            $this->uploads->delete($old);
        }

        return back()->with('status', 'Profile picture removed.');
    }

    /**
     * Change the password.
     *
     * The CURRENT password is required even though the person is already signed
     * in: an unattended browser or a stolen session must not be enough to take
     * the account over permanently. Success also regenerates the session id,
     * which invalidates any other tab's session fixation.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That is not your current password.',
            ]);
        }

        $user->forceFill(['password' => $data['password']])->save();

        // Same regeneration the login does: the credential changed, so the
        // session identity should too.
        $request->session()->regenerate();

        return back()->with('status', 'Your password has been changed.');
    }

    /** Start the two-step setup: generate a secret and show the QR/URI. */
    public function enableTwoFactor(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return back()->with('status', 'Two-step authentication is already on.');
        }

        $this->twoFactor->beginSetup($user);

        return back()->with('status', 'Scan the code with your authenticator app, then enter the 6-digit code to finish.');
    }

    /** Finish the setup — only a correct code makes it real. */
    public function confirmTwoFactor(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! filled($user->two_factor_secret)) {
            throw ValidationException::withMessages([
                'code' => 'Start the setup first, then enter the code from your app.',
            ]);
        }

        if (! $this->twoFactor->verify((string) $user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => 'That code is not valid. Check your app and try again.',
            ]);
        }

        $codes = $this->twoFactor->confirm($user);

        // Shown ONCE, in this redirect's flash data — they are stored hashed.
        return back()->with('status', 'Two-step authentication is now on.')
            ->with('recovery_codes', $codes);
    }

    /** Turn it off. Requires the password, because disabling a second factor is a weakening. */
    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That is not your current password.',
            ]);
        }

        $this->twoFactor->disable($user);

        return back()->with('status', 'Two-step authentication has been turned off.');
    }

    /** A fresh set of recovery codes (the old ones stop working immediately). */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->hasTwoFactorEnabled(), 409, 'Two-step authentication is not on.');

        $codes = $this->twoFactor->confirm($user);

        return back()->with('status', 'New recovery codes generated. The old ones no longer work.')
            ->with('recovery_codes', $codes);
    }
}

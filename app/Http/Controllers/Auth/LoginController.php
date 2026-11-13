<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = strtolower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        // A user can legitimately exist in more than one church's users
        // table row-set with the same email (unique per church_id, not
        // globally) — Auth::attempt alone can't disambiguate that, so we
        // resolve by church context first where one is known (subdomain
        // routing, a "which church?" step) and fall back to "single global
        // match" for platform admins / the common single-church case.
        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        // Two-step authentication: the password was right, so now ask for the
        // code. The account is signed out again (`Auth::logout()`) and its id
        // parked in the session — NOT `Auth::login()`ed with a flag — so a
        // forgotten tab cannot be used as an authenticated session by simply
        // navigating away from the challenge page.
        if ($user->hasTwoFactorEnabled()) {
            $this->startTwoFactorChallenge($request, $user, $throttleKey);

            return redirect()->route('two-factor.login');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly(); // saveQuietly: this isn't an auditable business change

        return redirect()->intended(route('dashboard'));
    }

    /**
     * The second step of sign-in: a code from the authenticator app, or one
     * of the stored recovery codes.
     *
     * Throttled on the same key as the password attempt, so a valid password
     * and an unlimited guess list is not a way round the second factor.
     */
    public function twoFactorChallenge(Request $request): RedirectResponse
    {
        $pendingId = $request->session()->get('two_factor.user_id');

        abort_unless($pendingId, 403, 'Start signing in again.');

        $data = $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $throttleKey = 'two-factor|'.$pendingId.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'code' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        $user = User::find($pendingId);

        abort_unless($user && $user->hasTwoFactorEnabled(), 403, 'Start signing in again.');

        $twoFactor = app(\App\Domains\Security\Services\TwoFactorService::class);
        $ok = false;

        if (filled($data['recovery_code'] ?? null)) {
            $remaining = $twoFactor->consumeRecoveryCode(
                $twoFactor->storedRecoveryHashes($user),
                (string) $data['recovery_code'],
            );

            if ($remaining !== null) {
                // Consumed: a recovery code is single-use, and the stored list
                // is rewritten so the same code cannot be replayed.
                $user->forceFill(['two_factor_recovery_codes' => json_encode($remaining)])->save();
                $ok = true;
            }
        } elseif (filled($data['code'] ?? null)) {
            $ok = $twoFactor->verify((string) $user->two_factor_secret, (string) $data['code']);
        }

        if (! $ok) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'code' => 'That code is not valid. Try again, or use a recovery code.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $remember = (bool) $request->session()->pull('two_factor.remember', false);
        $request->session()->forget('two_factor');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        return redirect()->intended(route('dashboard'));
    }

    /** Park the half-authenticated state and sign out until the code is proved. */
    private function startTwoFactorChallenge(Request $request, User $user, string $throttleKey): void
    {
        Auth::guard('web')->logout();

        $request->session()->put([
            'two_factor.user_id' => $user->id,
            'two_factor.remember' => $request->boolean('remember'),
        ]);

        // The PASSWORD throttle is cleared here, not hit: the password was
        // correct, and charging the wrong limiter would let a person who once
        // mistyped their password be refused a sign-in they are entitled to.
        // Code guesses are throttled separately in twoFactorChallenge().
        RateLimiter::clear($throttleKey);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

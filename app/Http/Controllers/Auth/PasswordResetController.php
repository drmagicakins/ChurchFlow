<?php

namespace App\Http\Controllers\Auth;

use App\Domains\Auth\Services\PasswordResetTokenService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Forgot / reset password — the only way back in without the church owner.
 *
 * Three rules this controller holds to:
 *
 *  1. **Never reveal whether an email has an account.** requestLink() always
 *     answers with the same page and the same sentence. A church's staff list
 *     is not public, and "no such user" would make this screen a directory.
 *
 *  2. **A link is single-use and short-lived.** The token is spent the moment
 *     the password changes, so a forwarded or logged email cannot be replayed.
 *
 *  3. **Nothing else changes.** A reset replaces the password only: the
 *     account keeps its church, parish, role and per-pastor permissions. The
 *     owner is not granting or revoking anything by resetting a password.
 */
class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResetTokenService $tokens) {}

    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function requestLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [
            'email.required' => 'Enter the email address you sign in with.',
        ]);

        // A reset mail is a message to an inbox, so this endpoint is throttled
        // per address AND per IP: the per-address key stops one inbox being
        // flooded, the per-IP key stops one attacker cycling addresses.
        $emailKey = 'password-reset:'.mb_strtolower(trim($data['email']));
        $ipKey = 'password-reset-ip:'.$request->ip();

        if (RateLimiter::tooManyAttempts($emailKey, 3) || RateLimiter::tooManyAttempts($ipKey, 10)) {
            $seconds = max(RateLimiter::availableIn($emailKey), RateLimiter::availableIn($ipKey));

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Too many reset requests. Please wait '
                        .$this->humanDuration($seconds).' before trying again.',
                ]);
        }

        RateLimiter::hit($emailKey, 900);
        RateLimiter::hit($ipKey, 900);

        // Sends only when the address identifies exactly one active account;
        // returns the same response either way (rule 1).
        $this->tokens->send($data['email']);

        return back()->with(
            'status',
            'If an account uses that email address, we have sent a link to set a new password. '
            .'The link expires in one day.'
        );
    }

    /** The page the emailed link opens. */
    public function resetForm(Request $request, string $token): View
    {
        $email = (string) $request->query('email', '');

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
            // Checked here so an obviously dead link says so on arrival rather
            // than only after the person has typed a new password twice.
            'linkIsUsable' => $this->tokens->soleActiveAccountFor($email) !== null
                && User::passwordResetTokenIsValid(
                    $this->tokens->normalise($email),
                    $token,
                ),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $this->tokens->soleActiveAccountFor($data['email']);

        // One message for every failure mode below (no account, expired token,
        // wrong token, already spent). Distinguishing them would tell an
        // attacker which addresses exist and which tokens were once real.
        $invalid = 'This password reset link is invalid or has expired. Request a new one.';

        if (! $user || ! User::passwordResetTokenIsValid($user->email, $data['token'])) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => $invalid]);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        // Single use. Kept even if the save above is retried: the token is gone
        // once a password has actually been written.
        $user->forgetPasswordResetToken();

        // Any session opened with the OLD password is no longer trusted.
        // Laravel's AuthenticateSession would do this per-device; without it a
        // stolen session would outlive the reset that was meant to kill it.
        DB::table('sessions')->where('user_id', $user->id)->delete();

        return redirect()->route('login')->with(
            'status',
            'Your password has been updated. Sign in with your new password.'
        );
    }

    /** "15 minutes" / "2 hours" — friendlier than a bare seconds count. */
    private function humanDuration(int $seconds): string
    {
        if ($seconds <= 60) {
            return max(1, $seconds).' seconds';
        }

        $minutes = (int) ceil($seconds / 60);

        return $minutes >= 60
            ? (int) ceil($minutes / 60).' hours'
            : $minutes.' minutes';
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Email verification — the STANDARD flow (Laravel's `MustVerifyEmail`).
 *
 * Four routes, and each answers a different real moment:
 *
 *   GET  /email/verify                    the "check your inbox" page a signed-in,
 *                                         unverified person lands on;
 *   GET  /email/verify/{id}/{hash}        the SIGNED link from the email itself;
 *   POST /email/verification-notification resend it (throttled — see routes);
 *   POST /email/verify/{id}/{hash}        the same link, submitted from the page
 *                                         (some mail clients prefetch GET links,
 *                                         so the page offers a button too).
 *
 * SECURITY, AND WHY EACH CHECK IS THERE
 * -------------------------------------
 *   - `hasValidSignature()` — the link is a Laravel SIGNED url. Without it
 *     anyone could mark any account verified by guessing an id and a hash,
 *     because the hash is derived from data a visitor already knows (the email
 *     address they registered). The signature is what makes the link proof that
 *     WE sent it.
 *   - `hash_equals(sha1($user->email), $hash)` — the link stops working if the
 *     address has since been changed, which is exactly right: confirming the OLD
 *     address must not confirm the new one.
 *   - `$request->user()->id === $user->id` — a signed link for someone else is
 *     refused, so a person cannot confirm another account while signed in.
 *   - already verified → a friendly redirect, never a 403. Two clicks on a link
 *     (or a mail client opening it twice) is not an error.
 *
 * The signed-route middleware on the link routes also validates the signature
 * before the controller runs; the explicit check here keeps the controller
 * correct if it is ever reached by another path.
 */
class EmailVerificationController extends Controller
{
    /** "We have sent you a link" — shown to a signed-in, unverified account. */
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->afterVerification($request);
        }

        return view('auth.verify-email', [
            'email' => $request->user()->email,
        ]);
    }

    /** The link in the email. */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = $this->userForLink($request, $id, $hash);

        // Already confirmed: two clicks (or a prefetch by the mail provider) must
        // not be an error page. Send them on to what they were doing.
        if ($user->hasVerifiedEmail()) {
            return $this->afterVerification($request);
        }

        if ($user->markEmailAsVerified()) {
            // Fires Laravel's standard event so anything that wants to welcome a
            // newly-verified account can listen without touching this flow.
            event(new Verified($user));
        }

        return $this->afterVerification($request)
            ->with('status', 'Email address confirmed. Thank you!');
    }

    /**
     * "Not now" — a LEGACY account chooses to carry on without confirming.
     *
     * Only offered to accounts created before verification existed (see
     * EnsureEmailIsVerified::verificationShippedAt()): a church that has been
     * running for months must not be locked out of its own dashboard by a rule
     * that arrived afterwards. The choice is recorded in the session, not the
     * user row — it is a per-session acknowledgement, and the in-app reminder
     * keeps the offer open. Accounts created after the cut-off are never shown
     * this, and posting it as one does nothing.
     */
    public function defer(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && ! $user->hasVerifiedEmail(), 403);

        abort_unless(
            $user->created_at !== null && $user->created_at->lt(EnsureEmailIsVerified::verificationShippedAt()),
            403,
            'New accounts must confirm their email address before continuing.'
        );

        $request->session()->put(EnsureEmailIsVerified::DEFER_KEY, 'legacy');

        return $this->afterVerification($request)
            ->with('status', 'You can confirm your email address any time from your dashboard.');
    }

    /** Re-send the confirmation mail. */
    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasVerifiedEmail()) {
            return $this->afterVerification($request)->with('status', 'Your email address is already confirmed.');
        }

        $user->sendEmailVerificationNotification();

        return back()->with(
            'status',
            'A new confirmation link has been sent to '.$user->email.'. It expires in '
                .(int) config('auth.verification.expire', 60).' minutes.'
        );
    }

    /**
     * The account a signed link identifies, or a refusal.
     *
     * Every refusal is the same 403 with the same words: which of the four checks
     * failed is not information a stranger should be given.
     */
    private function userForLink(Request $request, string $id, string $hash): User
    {
        abort_unless($request->hasValidSignature(), 403, 'This verification link is invalid or has expired. Sign in and request a new one.');
        abort_unless($request->user(), 403, 'Sign in to confirm your email address.');

        $user = User::query()->find($id);

        abort_unless($user && $request->user()->id === $user->id, 403, 'That verification link is for a different account.');
        abort_unless(hash_equals(sha1((string) $user->email), (string) $hash), 403, 'That verification link is no longer valid — the email address has changed since it was sent.');

        return $user;
    }

    /**
     * Where a verified account goes next.
     *
     * Someone who has just confirmed an address — or who was already confirmed —
     * wants the app, not the "check your inbox" page. A user with no church yet
     * (mid-signup) is sent to the plans page, which is where their journey
     * continues; Auth::logout() is deliberately NOT called, because a verified
     * account is a legitimate session.
     */
    private function afterVerification(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->church_id === null) {
            return redirect()->route('plans.index');
        }

        return redirect()->intended(route('dashboard'));
    }
}

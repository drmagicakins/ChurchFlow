<?php

namespace App\Domains\Auth\Services;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Forgot-password for a multi-tenant application.
 *
 * Two facts about THIS app shape the whole design:
 *
 *  1. `users` is unique on (church_id, email), NOT on email alone (see
 *     database/migrations/…_create_users_table.php). One email can therefore
 *     be a user of two churches. `password_reset_tokens` is keyed by email and
 *     has no room for a church_id, so issuing a link for "whichever row came
 *     back first" would let a person reset the WRONG account — possibly another
 *     church's. This service only issues a link when the email identifies
 *     exactly one active account, and says so plainly when it does not.
 *
 *  2. A church member's address is created by an administrator, and the reset
 *     request itself proves nothing about the requester. So the response NEVER
 *     says whether an email exists. The same page and the same sentence are
 *     shown either way; only the inbox can tell the difference. That is also
 *     what stops this screen being used to enumerate the staff of a church.
 */
class PasswordResetTokenService
{
    /**
     * Send a reset link for $email, if there is exactly one account to send it to.
     *
     * Deliberately returns void: the caller must not branch on the outcome,
     * because branching is what leaks existence.
     */
    public function send(string $email): void
    {
        $email = $this->normalise($email);

        $user = $this->soleActiveAccountFor($email);

        if (! $user) {
            // No account, several accounts, or an inactive one. The visitor is
            // told the same thing either way (see the controller).
            return;
        }

        $token = $user->createPasswordResetToken();

        // Not queued. A reset request is the one mail that must not depend on a
        // worker being alive — R5 records that nothing supervises one here, and
        // a queued reset that never runs looks identical to a broken feature.
        Mail::to($user->email)->send(new PasswordResetMail($user, $this->resetUrl($email, $token)));
    }

    /**
     * The account a reset may be applied to, or null.
     *
     * Used by BOTH the send step and the apply step so the rule cannot drift:
     * whatever could receive a link is exactly what a link can change.
     */
    public function soleActiveAccountFor(string $email): ?User
    {
        $candidates = User::query()
            ->where('email', $this->normalise($email))
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($candidates->count() !== 1) {
            return null;
        }

        return $candidates->first();
    }

    /** The link a person clicks, carrying the token in the query string. */
    public function resetUrl(string $email, string $token): string
    {
        return route('password.reset', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * Emails are matched without HTML-encoded characters.
     *
     * WHY: when the "check your email" flash is rendered, Laravel escapes the
     * address for display (`a+b@x.com` becomes `a&#039;b...` for apostrophes and
     * `+` survives but `<` does not). Submitting the address back through a
     * form is fine, but a copy-paste from an already-rendered page can arrive
     * HTML-escaped. Decoding is a no-op for a normal address and prevents a
     * legitimate user being told their token is invalid for a cosmetic reason.
     */
    public function normalise(string $email): string
    {
        return mb_strtolower(trim(html_entity_decode($email, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}

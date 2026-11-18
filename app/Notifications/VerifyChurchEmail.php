<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * "Confirm your email address" — the STANDARD verification notification.
 *
 * WHY EXTEND LARAVEL'S RATHER THAN WRITE ONE. Laravel's VerifyEmail already
 * implements the part that is easy to get subtly wrong: an expiring SIGNED
 * link (so it cannot be forged, guessed, or replayed indefinitely), a URL bound
 * to the user id and a hash of the email address (so the link stops working the
 * moment the address is changed), and the plain-text alternative. A hand-written
 * version would be a second, silently divergent implementation of a security
 * control.
 *
 * WHAT IS OVERRIDDEN, AND WHY. The subject line and the wording only — the
 * message now says "Confirm your email address" and names the app, which is
 * what a recipient actually needs to recognise it. The LINK is untouched: it is
 * still built by Laravel's own `verificationUrl()`, the same signed route and
 * the same expiry.
 *
 * NOT QUEUED. Laravel's default sends through the Notification facade, which
 * DELIVERS SYNCHRONOUSLY unless the notification implements ShouldQueue — and
 * this one deliberately does not, for the reason PasswordResetTokenService
 * records: a confirmation message must not depend on a queue worker being
 * alive, or "waiting for the email" and "the feature is broken" look identical.
 *
 * Laravel reaches this class through `User::sendEmailVerificationNotification()`
 * (see App\Models\User), so `verified` middleware, `hasVerifiedEmail()` and
 * `markEmailAsVerified()` all keep working exactly as documented.
 */
class VerifyChurchEmail extends BaseVerifyEmail
{
    /**
     * The mail — SAME signed link, our own words.
     *
     * `verificationUrl()` is inherited: see the class docblock for why the link
     * must not be reimplemented here.
     */
    protected function buildMailMessage($url)
    {
        $product = config('marketing.product.name', 'ChurchFlow');

        return (new MailMessage)
            ->subject("Confirm your email address · {$product}")
            ->greeting('Confirm your email address')
            ->line('Hello, and welcome to '.$product.'.')
            ->line('Please confirm this email address so we can send you updates about your church — announcements, reminders and billing notices will all go here.')
            ->action('Confirm email address', $url)
            ->line('This link expires in '.config('auth.verification.expire', 60).' minutes and works once.')
            ->line('If you did not create this account, no further action is required — simply ignore this email.')
            ->salutation('— '.$product);
    }
}

<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * "Confirm your email address" — the message behind the standard verification
 * flow (see Notifications\VerifyChurchEmail for the link, which is Laravel's
 * own signed URL).
 *
 * TWO SENDERS, ONE TEMPLATE. Verification is requested at two different
 * moments, and the message must be true at both:
 *
 *   - a CHURCH account (owner or parish pastor) confirming its address — the
 *     mail carries the church's logo, signature and Reply-To, exactly like
 *     every other message the church sends;
 *   - a brand-new SIGNUP that has not bought a plan yet (so no church exists)
 *     — there is nothing to brand, and the mail comes from ChurchFlow.
 *
 * Sent synchronously (not queued): see the notification's docblock. That is also
 * why this class has no ShouldQueue.
 *
 * The HTML and the plain-text parts are SEPARATE views. Reusing the HTML one
 * for both would put raw markup in the text/plain alternative, and some clients
 * choose that part over the HTML one — the same reason PasswordResetMail splits
 * its two views.
 */
class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $url,
    ) {}

    public function build(): self
    {
        $church = $this->user->church;

        $data = [
            'name' => $this->user->name,
            'url' => $this->url,
            'expiresInMinutes' => (int) config('auth.verification.expire', 60),
            'church' => $church,
        ];

        // The Reply-To points at the church owner/pastor when there IS a church,
        // so a reply to a confirmation message reaches the church rather than
        // the platform — the same rule every other church email follows.
        if ($church) {
            $replyTo = app(\App\Domains\Communication\Services\ChurchReplyAddresses::class)->forChurch($church);

            if ($replyTo !== []) {
                $this->replyTo($replyTo);
            }
        }

        return $this->subject('Confirm your email address')
            ->view('emails.verify-email', $data)
            ->text('emails.verify-email-text', $data);
    }
}

<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * "Set a new password" — the link a person asked for on the sign-in page.
 *
 * Password PURPOSE (below) is text, not HTML. It is a real, clickable link in
 * every mail client, and it is what a person pastes back if the link is
 * wrapped or stripped by their provider.
 *
 * Sent synchronously, not queued: see PasswordResetTokenService::send().
 */
class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $url,
        public readonly int $expiresInMinutes = 1440,
    ) {}

    public function build(): self
    {
        return $this->subject('Set a new ChurchFlow password')
            // HTML part and plain-text part are SEPARATE views. Reusing one for
            // both would put raw markup in the text/plain alternative, and
            // some clients pick that part over the HTML one.
            ->view('emails.password-reset', [
                'name' => $this->user->name,
                'url' => $this->url,
                'hours' => max(1, (int) round($this->expiresInMinutes / 60)),
            ])
            ->text('emails.password-reset-text', [
                'name' => $this->user->name,
                'url' => $this->url,
                'hours' => max(1, (int) round($this->expiresInMinutes / 60)),
            ]);
    }
}

<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\EventRegistrationSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The "your event is coming up" reminder a pastor sends from the event page
 * (§16). One mail per person, so the greeting can be their own name and the
 * personal tone of a church reminder is preserved.
 *
 * TWO KINDS OF RECIPIENT. The first version took an
 * `EventRegistrationSubmission` — the public form's visitors — and only them.
 * Church members registered from inside the app have no such row, so they could
 * not be emailed at all, even though they are the people the reminder is really
 * for. The second argument is therefore either that row (unchanged, for any
 * caller already passing one) or the person's NAME.
 */
class EventReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /** The greeting name, resolved from whichever kind of recipient was given. */
    public readonly string $recipientName;

    /**
     * The visitor row, when there is one. Kept on the class because the template
     * and existing callers read it; null for a church member.
     */
    public readonly ?EventRegistrationSubmission $registration;

    public function __construct(
        public readonly Event $event,
        EventRegistrationSubmission|string $registration,
    ) {
        $this->registration = $registration instanceof EventRegistrationSubmission ? $registration : null;
        $this->recipientName = $registration instanceof EventRegistrationSubmission
            ? (string) ($registration->name ?: 'there')
            : ($registration !== '' ? $registration : 'there');
    }

    public function build(): self
    {
        $when = $this->event->starts_at?->format('l, j F Y \a\t g:i A');

        return $this->subject("Reminder: {$this->event->title}")
            ->view('emails.event-reminder', [
                'event' => $this->event,
                'registration' => $this->registration,
                'recipientName' => $this->recipientName,
                'when' => $when,
            ]);
    }
}

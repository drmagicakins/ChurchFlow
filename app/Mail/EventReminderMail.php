<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\EventRegistrationSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The "your event is coming up" reminder a pastor sends to an event's visitor
 * list from the event page (§16). One mail per visitor, so the greeting can be
 * their own name and the unsubscribe-free, personal tone of a church reminder
 * is preserved.
 */
class EventReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Event $event,
        public readonly EventRegistrationSubmission $registration,
    ) {}

    public function build(): self
    {
        $when = $this->event->starts_at?->format('l, j F Y \a\t g:i A');

        return $this->subject("Reminder: {$this->event->title}")
            ->view('emails.event-reminder', [
                'event' => $this->event,
                'registration' => $this->registration,
                'when' => $when,
            ]);
    }
}

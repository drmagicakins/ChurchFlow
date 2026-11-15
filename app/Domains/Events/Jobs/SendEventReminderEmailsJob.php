<?php

namespace App\Domains\Events\Jobs;

use App\Domains\Events\Services\EventReminderAudience;
use App\Mail\EventReminderMail;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventRegistrationSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * §16 "send event reminder" — emails everyone on the event's list: the guests
 * who filled in the public form AND the church's own members registered from
 * inside the app.
 *
 * It used to read `event_registration_submissions` only, so a church that
 * registered its congregation never emailed them — the audience was one table
 * wide. See EventReminderAudience, which now resolves both and is shared with
 * the SMS job so the two channels reach the same people.
 *
 * Queued for the same reason SendAnnouncementEmailsJob is: a list of a couple
 * of hundred addresses must not hold the pastor's browser open. Email is
 * included with the subscription, so there is no wallet and no
 * confirm-and-reserve step here — that is SMS only.
 *
 * `withoutGlobalScopes()` because a worker has no tenant bound; the event was
 * already tenant-verified by the controller that dispatched this.
 */
class SendEventReminderEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $eventId) {}

    public function handle(EventReminderAudience $audience): void
    {
        $event = Event::withoutGlobalScopes()->findOrFail($this->eventId);

        foreach ($audience->emailRecipients($event) as $recipient) {
            Mail::to($recipient['email'])->queue(new EventReminderMail($event, $recipient['name']));

            // Stamp the row the person came FROM: a member on `event_registrations`,
            // a guest on `event_registration_submissions`. Stamping the wrong one
            // would report a reminder that never happened.
            $this->markReminded($recipient['key']);
        }
    }

    private function markReminded(string $key): void
    {
        [$kind, $id] = explode(':', $key, 2);

        $model = $kind === 'member'
            ? EventRegistration::class
            : EventRegistrationSubmission::class;

        $model::withoutGlobalScopes()->whereKey((int) $id)->update(['email_reminded_at' => now()]);
    }
}

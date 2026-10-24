<?php

namespace App\Domains\Events\Jobs;

use App\Mail\EventReminderMail;
use App\Models\Event;
use App\Models\EventRegistrationSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * §16 "send event reminder" — emails every visitor on an event's public
 * registration list.
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

    public function handle(): void
    {
        $event = Event::withoutGlobalScopes()->findOrFail($this->eventId);

        EventRegistrationSubmission::withoutGlobalScopes()
            ->where('event_id', $event->id)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('email')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($event) {
                foreach ($rows as $row) {
                    Mail::to($row->email)->queue(new EventReminderMail($event, $row));
                    $row->forceFill(['email_reminded_at' => now()])->save();
                }
            });
    }
}

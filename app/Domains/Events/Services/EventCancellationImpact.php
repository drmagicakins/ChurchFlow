<?php

namespace App\Domains\Events\Services;

use App\Models\Event;

/**
 * What cancelling this event actually costs, in words.
 *
 * "Cancel this event? It will no longer appear on the calendar." was true and
 * useless: it named none of the things a person is about to lose. Cancelling
 * soft-deletes the event AND marks it cancelled, so the public registration
 * link stops working, nobody can be registered any more, and every reminder
 * already sent cannot be taken back — while the roster stays readable. Someone
 * pressing that button while the event is happening, or a day after posting it,
 * needs to know that BEFORE they press it.
 *
 * This class only REPORTS. It writes nothing and blocks nothing: the decision,
 * and the authorisation for it, stay in the controller (EventPolicy::delete).
 *
 * The facts it returns:
 *   - how soon the event is (running now / today / in N days / over);
 *   - how long ago it was created (a day or two = barely promoted);
 *   - how many people are registered, and how full;
 *   - whether a public link is open (and therefore stops working);
 *   - whether reminders have gone out (which cannot be recalled).
 */
class EventCancellationImpact
{
    /**
     * @return array{
     *     headline: string, reasons: array<int, string>, severity: string,
     *     confirm_label: string, registered: int, capacity_label: ?string
     * }
     */
    public function summarise(Event $event): array
    {
        $now = now();
        $starts = $event->starts_at;
        $ends = $event->ends_at ?: $event->starts_at;

        $registered = (int) $event->registrations()->where('status', 'registered')->count();
        $waitlisted = (int) $event->registrations()->where('status', 'waitlisted')->count();

        $reasons = [];
        $severity = 'normal';

        // --- when it is ------------------------------------------------------
        if ($starts && $ends && $now->between($starts, $ends) && ! $event->hasExpired()) {
            $severity = 'high';
            $reasons[] = 'This event is happening RIGHT NOW. Cancelling it takes the event off the '
                .'calendar while people are on their way or already there.';
        } elseif ($ends && $now->greaterThan($ends)) {
            $reasons[] = 'This event has already taken place. Cancelling it hides the record from the calendar; '
                .'its attendee history is kept.';
        } elseif ($starts && $starts->isToday()) {
            $severity = 'high';
            $reasons[] = 'This event is TODAY ('.$starts->format('g:i A').'). People may already be planning to come.';
        } elseif ($starts) {
            $days = (int) $now->startOfDay()->diffInDays($starts->copy()->startOfDay(), false);

            if ($days === 1) {
                $reasons[] = 'This event is TOMORROW.';
            } elseif ($days > 1) {
                $reasons[] = 'This event is in '.$days.' days.';
            }
        }

        // --- how long it has existed ----------------------------------------
        $createdAt = $event->created_at;
        $ageHours = $createdAt ? $createdAt->diffInHours($now) : null;

        if ($ageHours !== null && $ageHours <= 48 && $starts && $starts->isFuture()) {
            $severity = $severity === 'high' ? 'high' : 'elevated';
            $reasons[] = 'It was created only '.($ageHours < 24
                ? max(1, (int) round($ageHours)).' hour(s) ago'
                : (int) round($ageHours / 24).' day(s) ago')
                .', so this may be a mistake rather than a real cancellation.';
        }

        // --- people ----------------------------------------------------------
        $capacityLabel = null;

        if ($registered > 0) {
            $capacityLabel = $event->capacity
                ? $registered.' / '.$event->capacity
                : (string) $registered;

            $reasons[] = $registered.' '.str('person')->plural($registered).' already registered. '
                .'The list is kept, but nobody can register any more and the event leaves the calendar.';

            if ($event->capacity && $registered >= $event->capacity) {
                $reasons[] = 'The event is FULL — emailing or texting these people afterwards is the only way to '
                    .'tell them it is off.';
            }
        } elseif ($waitlisted > 0) {
            $reasons[] = $waitlisted.' '.str('person')->plural($waitlisted).' on the waiting list will not get a place.';
        }

        // --- the public link --------------------------------------------------
        if ($event->public_slug && ! $event->hasExpired()) {
            $reasons[] = 'The public registration link stops working immediately. Anyone it was shared with '
                .'(WhatsApp, flyers, social media) will see a closed page.';
        }

        // --- reminders already sent --------------------------------------------
        $smsSent = (int) $event->visitorRegistrations()->whereNotNull('sms_reminded_at')->count()
            + (int) $event->registrations()->whereNotNull('sms_reminded_at')->count();

        if ($smsSent > 0) {
            $reasons[] = 'SMS reminders have already gone to '.$smsSent.' '.str('person')->plural($smsSent)
                .'. Those messages cannot be recalled — consider sending a cancellation notice.';
        }

        if ($reasons === []) {
            $reasons[] = 'The event will be removed from the calendar.';
        }

        return [
            'headline' => $severity === 'high'
                ? 'Cancel this event — it is imminent or already under way?'
                : 'Cancel this event?',
            'reasons' => $reasons,
            'severity' => $severity,
            'confirm_label' => 'Yes, cancel the event',
            'registered' => $registered,
            'capacity_label' => $capacityLabel,
        ];
    }
}
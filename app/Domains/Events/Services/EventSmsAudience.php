<?php

namespace App\Domains\Events\Services;

use App\Domains\Communication\Services\PhoneNumber;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventRegistrationSubmission;
use App\Models\Member;

/**
 * WHO an event SMS goes to.
 *
 * Before this class the event SMS reached only `EventRegistrationSubmission`
 * rows — the people who filled in the PUBLIC form. Everybody registered from
 * inside the app lives in `event_registrations` and points at a `members` row,
 * so a church that registered its own congregation saw
 *
 *     Nobody who filled in the event form has a phone number yet.
 *
 * with ten people visibly registered, and the send button never appeared. That
 * looked exactly like a removed feature. Nothing had been removed; the audience
 * was simply one table wide.
 *
 * The audience is therefore assembled here, ONCE, and every caller — the event
 * screen, the controller's recipient count and the sending job — reads the same
 * list. A count on screen that differs from what the job sends is a bug this
 * class exists to make impossible.
 *
 * A recipient is skipped, never silently dropped, when their number cannot be
 * sent to (see PhoneNumber) — and the reason is carried on the row so the
 * church can be told why somebody was left out.
 */
class EventSmsAudience
{
    public function __construct(private readonly PhoneNumber $phones) {}

    /**
     * @return array<int, array{name: string, phone: string, source: string, key: string, problem: ?string}>
     *                                                                                                         key      = kind:id, used to mark a row as reminded
     *                                                                                                         problem  = why this person is skipped (null when they are not)
     */
    public function forEvent(Event $event): array
    {
        $recipients = [];
        $seenNumbers = [];

        // 1. VISITORS — the public form's sign-ups, unchanged and first, so an
        //    existing deployment sends to exactly the people it sent to before.
        $visitors = EventRegistrationSubmission::withoutGlobalScopes()
            ->where('event_id', $event->id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('id')
            ->get();

        foreach ($visitors as $visitor) {
            $recipients[] = $this->entry(
                name: $visitor->name ?: 'Visitor',
                rawPhone: $visitor->phone,
                source: 'visitor',
                key: 'visitor:'.$visitor->id,
                seenNumbers: $seenNumbers,
            );
        }

        // 2. MEMBERS — people registered for the event from inside the app. This
        //    is the half that was missing.
        $registrations = EventRegistration::withoutGlobalScopes()
            ->where('event_id', $event->id)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('member_id')
            ->orderBy('id')
            ->get();

        foreach ($registrations as $registration) {
            $member = Member::withoutGlobalScopes()->find($registration->member_id);

            if (! $member) {
                continue; // the member row was removed; there is nobody to text
            }

            $recipients[] = $this->entry(
                name: $member->full_name ?: 'Member',
                rawPhone: $member->phone,
                source: 'member',
                key: 'member:'.$member->id,
                seenNumbers: $seenNumbers,
            );
        }

        return $recipients;
    }

    /**
     * The recipients an SMS can actually be sent to.
     *
     * @return array<int, array{name: string, phone: string, source: string, key: string, problem: null}>
     */
    public function sendable(Event $event): array
    {
        return array_values(array_filter(
            $this->forEvent($event),
            fn (array $r) => $r['problem'] === null,
        ));
    }

    /**
     * The people who WILL be skipped if an SMS is sent, with the reason.
     *
     * @return array<int, array{name: string, phone: string, source: string, key: string, problem: string}>
     */
    public function skipped(Event $event): array
    {
        return array_values(array_filter(
            $this->forEvent($event),
            fn (array $r) => $r['problem'] !== null,
        ));
    }

    /**
     * Build one audience row.
     *
     * `$seenNumbers` is by reference: a member who is registered twice, or a
     * visitor who signed up twice, is texted ONCE. Sending the same reminder two
     * or three times to the same phone is a real cost to the church and looks
     * like a broken system to the recipient.
     */
    private function entry(
        string $name,
        mixed $rawPhone,
        string $source,
        string $key,
        array &$seenNumbers,
    ): array {
        $problem = $this->phones->problemWith($rawPhone);
        $number = $problem === null ? $this->phones->normalise($rawPhone) : null;

        if ($number !== null && isset($seenNumbers[$number])) {
            $problem = 'also listed as '.$seenNumbers[$number].'; sent once already';
        } elseif ($number !== null) {
            $seenNumbers[$number] = $name;
        }

        return [
            'name' => $name,
            'phone' => $number ?? (string) $rawPhone,
            'source' => $source,
            'key' => $key,
            'problem' => $problem,
        ];
    }
}

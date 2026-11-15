<?php

namespace App\Domains\Events\Services;

use App\Domains\Communication\Services\PhoneNumber;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventRegistrationSubmission;
use App\Models\Member;

/**
 * WHO an event reminder reaches — by email AND by SMS.
 *
 * There are two kinds of person on an event's list, and they live in different
 * tables:
 *
 *   - VISITORS  — `event_registration_submissions`, people who filled in the
 *                 public form. They have their own name/email/phone columns.
 *   - MEMBERS   — `event_registrations`, people registered from inside the app.
 *                 Their name, email and phone live on the `members` row.
 *
 * Both reminder jobs used to read the FIRST table only. A church that registered
 * its own congregation therefore saw
 *
 *     Nobody who filled in the event form has a phone number yet.
 *
 * with a full roster on the page, the SMS form never appeared, and the email
 * reminder reached only guests — while the church's own members, who are the
 * people actually expected to turn up, were never contacted. That looked exactly
 * like the feature having been removed. Nothing had been removed; the audience
 * was one table wide.
 *
 * Resolving the audience HERE, once, is what keeps three things equal:
 *
 *   1. the number printed on the page ("Send SMS to N"),
 *   2. the number the controller reports after queueing, and
 *   3. the number of messages the job actually sends.
 *
 * Nothing is ever silently dropped. A person missing an address is returned with
 * the reason on the row, so the screen can name them.
 */
class EventReminderAudience
{
    public function __construct(private readonly PhoneNumber $phones) {}

    /**
     * Every person on the list, with the addresses they can be reached at.
     *
     * @return array<int, array{
     *     name: string, email: ?string, phone: ?string, source: string,
     *     key: string, email_problem: ?string, phone_problem: ?string
     * }>
     */
    public function all(Event $event): array
    {
        $rows = [];
        $seenPhones = [];
        $seenEmails = [];

        // 1. VISITORS — first, and unchanged, so an existing deployment keeps
        //    reaching exactly the guests it reached before.
        $visitors = EventRegistrationSubmission::withoutGlobalScopes()
            ->where('event_id', $event->id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('id')
            ->get();

        foreach ($visitors as $visitor) {
            $rows[] = $this->row(
                name: $visitor->name ?: 'Visitor',
                rawEmail: $visitor->email,
                rawPhone: $visitor->phone,
                source: 'visitor',
                key: 'visitor:'.$visitor->id,
                seenPhones: $seenPhones,
                seenEmails: $seenEmails,
            );
        }

        // 2. MEMBERS — the half that was missing.
        $registrations = EventRegistration::withoutGlobalScopes()
            ->where('event_id', $event->id)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('member_id')
            ->orderBy('id')
            ->get();

        foreach ($registrations as $registration) {
            $member = Member::withoutGlobalScopes()->find($registration->member_id);

            if (! $member) {
                continue; // the member row is gone; there is nobody to contact
            }

            $rows[] = $this->row(
                name: $member->full_name ?: 'Member',
                rawEmail: $member->email,
                rawPhone: $member->phone,
                source: 'member',
                key: 'member:'.$member->id,
                seenPhones: $seenPhones,
                seenEmails: $seenEmails,
            );
        }

        return $rows;
    }

    /**
     * People an SMS can be sent to, as [name, phone, source, key].
     *
     * A phone that appears twice (a member also on the public form, or the same
     * visitor signing up twice) is texted ONCE — the second row carries the
     * reason instead.
     *
     * @return array<int, array{name: string, phone: string, source: string, key: string}>
     */
    public function smsRecipients(Event $event): array
    {
        return array_values(array_map(
            fn (array $r) => ['name' => $r['name'], 'phone' => (string) $r['phone'], 'source' => $r['source'], 'key' => $r['key']],
            array_filter($this->all($event), fn (array $r) => $r['phone'] !== null && $r['phone_problem'] === null),
        ));
    }

    /**
     * People an email can be sent to, as [name, email, source, key].
     *
     * @return array<int, array{name: string, email: string, source: string, key: string}>
     */
    public function emailRecipients(Event $event): array
    {
        return array_values(array_map(
            fn (array $r) => ['name' => $r['name'], 'email' => (string) $r['email'], 'source' => $r['source'], 'key' => $r['key']],
            array_filter($this->all($event), fn (array $r) => $r['email'] !== null && $r['email_problem'] === null),
        ));
    }

    /**
     * Everyone who will be skipped for either channel, with the reason — so the
     * page can say "Akeem Fadairo — no phone number" instead of simply not
     * contacting them.
     *
     * @return array<int, array{name: string, source: string, channel: string, problem: string}>
     */
    public function unreachable(Event $event): array
    {
        $out = [];

        foreach ($this->all($event) as $r) {
            if ($r['phone_problem'] !== null) {
                $out[] = ['name' => $r['name'], 'source' => $r['source'], 'channel' => 'SMS', 'problem' => $r['phone_problem']];
            }

            if ($r['email_problem'] !== null) {
                $out[] = ['name' => $r['name'], 'source' => $r['source'], 'channel' => 'Email', 'problem' => $r['email_problem']];
            }
        }

        return $out;
    }

    private function row(
        string $name,
        mixed $rawEmail,
        mixed $rawPhone,
        string $source,
        string $key,
        array &$seenPhones,
        array &$seenEmails,
    ): array {
        // --- email --------------------------------------------------------
        $email = is_string($rawEmail) ? trim($rawEmail) : null;
        $emailProblem = null;

        if (! filled($email)) {
            $emailProblem = 'no email address';
            $email = null;
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailProblem = 'the email address is not valid';
            $email = null;
        } elseif (isset($seenEmails[mb_strtolower($email)])) {
            $emailProblem = 'also listed as '.$seenEmails[mb_strtolower($email)].'; sent once already';
            $email = null;
        } else {
            $seenEmails[mb_strtolower($email)] = $name;
        }

        // --- phone --------------------------------------------------------
        $phoneProblem = $this->phones->problemWith($rawPhone);
        $phone = $phoneProblem === null ? $this->phones->normalise($rawPhone) : null;

        if ($phone !== null && isset($seenPhones[$phone])) {
            $phoneProblem = 'also listed as '.$seenPhones[$phone].'; sent once already';
            $phone = null;
        } elseif ($phone !== null) {
            $seenPhones[$phone] = $name;
        }

        return [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'source' => $source,
            'key' => $key,
            'email_problem' => $emailProblem,
            'phone_problem' => $phoneProblem,
        ];
    }
}
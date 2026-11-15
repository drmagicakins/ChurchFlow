<?php

namespace App\Domains\Events\Services;

use App\Models\Event;

/**
 * The SMS view of an event's reminder audience.
 *
 * The audience itself lives in EventReminderAudience, which both channels share:
 * the same member roster and the same public-form visitors, resolved once. Keeping
 * ONE resolver is what stops email and SMS from quietly reaching different groups
 * of people — which is exactly how the original bug happened (SMS and email both
 * read the visitor table and nothing else, so a church that registered its own
 * congregation could not reach it at all).
 *
 * This class deliberately adds no logic of its own; it names the SMS channel's
 * shape so the controller, the view and the sending job all read the same words.
 */
class EventSmsAudience
{
    public function __construct(private readonly EventReminderAudience $audience) {}

    /** @return array<int, array{name: string, phone: string, source: string, key: string}> */
    public function sendable(Event $event): array
    {
        return $this->audience->smsRecipients($event);
    }

    /**
     * Everyone who will NOT be texted, with the reason.
     *
     * @return array<int, array{name: string, phone: string, source: string, key: string, problem: string}>
     */
    public function skipped(Event $event): array
    {
        $out = [];

        foreach ($this->audience->all($event) as $row) {
            if ($row['phone_problem'] === null) {
                continue;
            }

            $out[] = [
                'name' => $row['name'],
                'phone' => (string) ($row['phone'] ?? ''),
                'source' => $row['source'],
                'key' => $row['key'],
                'problem' => $row['phone_problem'],
            ];
        }

        return $out;
    }
}
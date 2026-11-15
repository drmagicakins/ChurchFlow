<?php

namespace App\Domains\Events\Jobs;

use App\Domains\Communication\Providers\SmsProviderInterface;
use App\Domains\Communication\Services\SmsSegmentCalculator;
use App\Domains\Communication\Services\SmsWalletService;
use App\Domains\Events\Services\EventSmsAudience;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventRegistrationSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * §16 "bulk SMS to the people coming to this event".
 *
 * Runs on the same rules as SmsCampaignService, deliberately — not a parallel
 * sending path:
 *
 *   - only people with a sendable phone number are counted or charged;
 *   - units are reserved (debited) up front from the wallet of the church that
 *     owns the event;
 *   - a failed send refunds that person's units with the reason on the ledger;
 *   - a partial failure never stops the rest of the batch.
 *
 * THE AUDIENCE IS `EventSmsAudience`'s, not a query of this job's own. It used
 * to read `EventRegistrationSubmission` alone, so people registered from inside
 * the app were never texted — the exact audience the organiser had put on the
 * list. Resolving it in one shared place is what keeps the number on the button
 * and the number of messages sent identical.
 *
 * It is a JOB rather than an inline loop because "bulk" is the whole point: the
 * pastor confirms and gets their page back, the messages go out on the worker.
 */
class SendEventReminderSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $eventId,
        private readonly string $message,
    ) {}

    public function handle(
        SmsProviderInterface $provider,
        SmsWalletService $wallets,
        SmsSegmentCalculator $segments,
        EventSmsAudience $audience,
    ): void {
        // No tenant is bound in a worker process; the controller already
        // proved this event belongs to the signed-in church.
        $event = Event::withoutGlobalScopes()->findOrFail($this->eventId);

        $recipients = $audience->sendable($event);

        if ($recipients === []) {
            return;
        }

        $segmentsPerMessage = $segments->segmentsFor($this->message);
        $totalUnits = count($recipients) * $segmentsPerMessage;

        // The CHURCH-WIDE wallet, explicitly. This job runs with no signed-in
        // user, so it can never be "the pastor's parish wallet" — and a send that
        // debited one parish's credits for the whole church's event would be
        // wrong even if it happened to have enough units.
        $wallet = $wallets->walletFor($event->church);

        try {
            $wallets->debit($wallet, $totalUnits, "event:{$event->id}:audience", "Event reminder SMS: {$event->title}");
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Insufficient credits. The pastor is told on the events screen how
            // many units were needed, rather than the send half-succeeding.
            Log::warning('Event reminder SMS not sent — wallet debit refused.', [
                'event_id' => $event->id,
                'units' => $totalUnits,
                'reason' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($recipients as $recipient) {
            $result = $provider->send($recipient['phone'], $this->message);

            if ($result->success) {
                $this->markReminded($recipient['key']);
                continue;
            }

            $wallets->refund(
                $wallet,
                $segmentsPerMessage,
                "event:{$event->id}:{$recipient['key']}",
                "Refund for failed event SMS: {$result->failureReason}",
            );
        }
    }

    /**
     * Record that this person has been reminded, on the row they came from.
     *
     * A visitor and a member are different tables; only the table that produced
     * the recipient is stamped, so a member can never be marked on a visitor
     * row (or vice versa) just because the ids happen to collide.
     */
    private function markReminded(string $key): void
    {
        [$kind, $id] = explode(':', $key, 2);

        $model = $kind === 'member'
            ? EventRegistration::class
            : EventRegistrationSubmission::class;

        $model::withoutGlobalScopes()->whereKey((int) $id)->update(['sms_reminded_at' => now()]);
    }
}
<?php

namespace App\Domains\Events\Jobs;

use App\Domains\Communication\Providers\SmsProviderInterface;
use App\Domains\Communication\Services\SmsSegmentCalculator;
use App\Domains\Communication\Services\SmsWalletService;
use App\Models\Event;
use App\Models\EventRegistrationSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * §16 "bulk SMS to the people who filled the event form".
 *
 * Runs on the same rules as SmsCampaignService, deliberately — not a parallel
 * sending path:
 *
 *   - only visitors with a phone number are counted or charged;
 *   - units are reserved (debited) up front from the wallet of the church that
 *     owns the event;
 *   - a failed send refunds that person's units with the reason on the ledger;
 *   - a partial failure never stops the rest of the batch.
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
    ): void {
        // No tenant is bound in a worker process; the controller already
        // proved this event belongs to the signed-in church.
        $event = Event::withoutGlobalScopes()->findOrFail($this->eventId);

        $recipients = EventRegistrationSubmission::withoutGlobalScopes()
            ->where('event_id', $event->id)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('id')
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $segmentsPerMessage = $segments->segmentsFor($this->message);
        $totalUnits = $recipients->count() * $segmentsPerMessage;

        $wallet = $wallets->walletFor($event->church);

        try {
            $wallets->debit($wallet, $totalUnits, "event:{$event->id}:visitors", "Event reminder SMS: {$event->title}");
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
            $result = $provider->send($recipient->phone, $this->message);

            if ($result->success) {
                $recipient->forceFill(['sms_reminded_at' => now()])->save();
                continue;
            }

            $wallets->refund(
                $wallet,
                $segmentsPerMessage,
                "event:{$event->id}:visitor:{$recipient->id}",
                "Refund for failed event SMS: {$result->failureReason}",
            );
        }
    }
}

<?php

namespace App\Domains\Communication\Services;

use App\Domains\Communication\Providers\SmsProviderInterface;
use App\Domains\Communication\Providers\SmsSendResult;
use App\Domains\Communication\Services\SmsSenderName;
use App\Models\SmsCampaign;

class SmsSendingService
{
    public function __construct(
        private readonly SmsProviderInterface $provider,
        private readonly SmsWalletService $wallets,
        private readonly ?SmsSenderName $senders = null,
    ) {}

    /**
     * Iterates every pending recipient, sends via the configured provider,
     * and — the one piece of real refund logic in this module — returns a
     * recipient's reserved units to the wallet the moment their specific
     * send fails, with the failure reason on record (§27). A partial
     * failure never blocks the rest of the batch from sending.
     */
    public function send(SmsCampaign $campaign): SmsCampaign
    {
        abort_unless($campaign->status === 'queued', 409, 'Only a queued campaign can be sent.');

        $campaign->update(['status' => 'sending']);
        // The campaign's own wallet, so a failed parish send refunds the parish
        // that paid for it — not the main church (§17-19).
        $wallet = $campaign->wallet();

        // WHOSE NAME the text shows (2026-11-17): the church's, or the parish's
        // when the campaign belongs to one. Derived once, so every recipient in
        // the batch is sent under the same name. Null → the provider's own
        // configured sender.
        //
        // The church comes from the CAMPAIGN, not the wallet: SmsWallet carries
        // no church relation (it is tenant-scoped), and $campaign->church is the
        // same church wallet() was resolved from. The parish is the wallet's own
        // organizationalUnit, which is the level the campaign is billed at.
        $from = ($this->senders ?? new SmsSenderName)->for($campaign->church, $wallet->organizationalUnit);

        $sentCount = 0;
        $failedCount = 0;

        // A broken SMS configuration must not destroy the WHOLE batch.
        //
        // The provider refuses, by design, a send it cannot make honest sense of
        // (no key, or no approved sender on this message) — and that refusal used
        // to escape the loop, so every recipient after the first stayed
        // 'pending' for ever, their reserved units were never returned, and the
        // campaign was stuck in 'sending'. Now the first such answer is recorded
        // against the recipient it belongs to and the rest of the batch is failed
        // the same way, with the reason on each row — so every unit is refunded
        // and the church sees WHY, instead of a half-sent campaign.
        $batchStopped = null;

        foreach ($campaign->recipients()->where('status', 'pending')->get() as $recipient) {
            try {
                $result = $batchStopped === null
                    ? $this->provider->send($recipient->phone, $campaign->message, $from)
                    : new SmsSendResult(success: false, failureReason: $batchStopped);
            } catch (\Throwable $e) {
                report($e);
                $batchStopped = $e->getMessage();
                $result = new SmsSendResult(success: false, failureReason: $batchStopped);
            }

            if ($result->success) {
                $recipient->update(['status' => 'sent', 'provider_message_id' => $result->providerMessageId]);
                $sentCount++;
            } else {
                $recipient->update(['status' => 'failed', 'failed_reason' => $result->failureReason]);
                $this->wallets->refund(
                    $wallet,
                    $recipient->segments,
                    "campaign:{$campaign->id}:member:{$recipient->member_id}",
                    "Refund for failed SMS: {$result->failureReason}",
                );
                $failedCount++;
            }
        }

        $status = match (true) {
            $failedCount === 0 => 'completed',
            $sentCount === 0 => 'failed',
            default => 'partially_failed',
        };

        $campaign->update(['status' => $status, 'sent_at' => now()]);

        return $campaign->fresh();
    }
}

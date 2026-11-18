<?php

namespace App\Domains\Communication\Providers;

/**
 * §23: the SMS module must never be tightly coupled to one provider.
 * Nothing outside this namespace (SmsCampaignService, the sending job)
 * refers to Termii/Twilio/etc. by name — they depend on this interface
 * only, resolved from the container (see AppServiceProvider), and the
 * active provider is a platform-admin setting, never a hard-coded choice.
 */
interface SmsProviderInterface
{
    /**
     * @param  string|null  $from  The SENDER NAME to show the recipient — the
     *                             church's (or parish pastor's) own name, as the
     *                             church required on 2026-11-17. Optional so a
     *                             caller that has no name (or a provider with no
     *                             concept of one) keeps working unchanged; when
     *                             it is null the provider falls back to its own
     *                             configured sender.
     */
    public function send(string $toPhone, string $message, ?string $from = null): SmsSendResult;
}

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
    public function send(string $toPhone, string $message): SmsSendResult;
}

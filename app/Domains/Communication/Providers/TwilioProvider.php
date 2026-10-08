<?php

namespace App\Domains\Communication\Providers;

class TwilioProvider implements SmsProviderInterface
{
    public function __construct(
        private readonly ?string $accountSid = null,
        private readonly ?string $authToken = null,
        private readonly ?string $fromNumber = null,
    ) {}

    public function send(string $toPhone, string $message): SmsSendResult
    {
        throw new \RuntimeException('TwilioProvider is not configured for network access in this environment.');
    }
}

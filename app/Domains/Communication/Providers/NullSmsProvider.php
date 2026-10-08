<?php

namespace App\Domains\Communication\Providers;

use Illuminate\Support\Str;

/**
 * Used by default in this scaffold, in tests, and in local development —
 * never touches a network. Swap the container binding (see
 * AppServiceProvider) for TermiiProvider/TwilioProvider once real
 * credentials are configured by the platform admin (§28).
 */
class NullSmsProvider implements SmsProviderInterface
{
    public function send(string $toPhone, string $message): SmsSendResult
    {
        return new SmsSendResult(success: true, providerMessageId: (string) Str::uuid());
    }
}

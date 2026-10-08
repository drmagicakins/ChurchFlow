<?php

namespace App\Domains\Communication\Providers;

final class SmsSendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $failureReason = null,
    ) {}
}

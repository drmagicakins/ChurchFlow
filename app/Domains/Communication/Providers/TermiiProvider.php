<?php

namespace App\Domains\Communication\Providers;

/**
 * Real integration point for Termii's SMS API. Left as a stub with the
 * shape a real implementation needs — this sandbox has no network access
 * to Termii, and credentials must never be hard-coded here (§28: they're a
 * platform-admin setting, injected via config, never committed).
 */
class TermiiProvider implements SmsProviderInterface
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $senderId = null,
    ) {}

    public function send(string $toPhone, string $message): SmsSendResult
    {
        // Real implementation: POST to Termii's /api/sms/send with
        // $this->apiKey / $this->senderId, map their JSON response into
        // SmsSendResult. Throwing here makes it impossible to silently
        // "succeed" against a provider that was never actually wired up.
        throw new \RuntimeException('TermiiProvider is not configured for network access in this environment.');
    }
}

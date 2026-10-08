<?php

namespace App\Domains\Subscriptions\Services;

use App\Domains\PlatformAdmin\Services\PlatformSettingsService;

/**
 * Deliberately simple: one platform-wide flat rate (§28-configurable),
 * applied to every invoice regardless of the church's country. A real
 * multi-country deployment would need per-region rates and tax-exemption
 * handling (VAT/GST rules vary enormously) — flagged here rather than
 * quietly assumed away, since guessing at tax compliance is worse than
 * admitting the limitation.
 */
class TaxCalculator
{
    public function __construct(private readonly PlatformSettingsService $settings) {}

    /** @return array{rate:string, tax_amount:string} */
    public function calculate(string $subtotal): array
    {
        // The stored rate is a free-form string ('7.5'); normalise it to a
        // fixed 2-decimal string here so every consumer sees the same
        // '7.50' the rest of the money-formatting convention uses, rather
        // than a value that changes shape depending on how it was typed.
        $rate = bcadd($this->settings->defaultTaxRate(), '0', 2);
        $taxAmount = bcdiv(bcmul($subtotal, $rate, 4), '100', 2);

        return ['rate' => $rate, 'tax_amount' => $taxAmount];
    }
}

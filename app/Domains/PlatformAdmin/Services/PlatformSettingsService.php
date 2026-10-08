<?php

namespace App\Domains\PlatformAdmin\Services;

use App\Models\PlatformSetting;

/**
 * §28: SMS provider, sender ID, cost per unit, credit packages, etc. must
 * be changeable by a platform admin without a code deploy. This is a
 * simple key/value override store; a key with no row here falls back to
 * whatever default the caller supplies (typically an env-backed config
 * value), so an un-configured platform behaves exactly as it did before
 * this table existed.
 */
class PlatformSettingsService
{
    public function get(string $key, mixed $default = null): mixed
    {
        $setting = PlatformSetting::where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        PlatformSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }

    public function smsPricePerUnit(): ?string
    {
        $value = $this->get('sms_price_per_unit', config('billing.sms_price_per_unit'));

        return $value !== null ? (string) $value : null;
    }

    public function defaultTaxRate(): string
    {
        return (string) $this->get('default_tax_rate', config('billing.default_tax_rate', '0'));
    }
}

<?php

return [
    // §18/§28: platform-admin configuration, never a hard-coded constant.
    // These are only the ENV-backed defaults — PlatformSettingsService
    // checks a runtime-editable override (platform_settings table) first
    // and falls back to these when no admin has set one yet.
    'sms_price_per_unit' => env('SMS_PRICE_PER_UNIT'),
    'default_tax_rate' => env('DEFAULT_TAX_RATE', '0'),
];

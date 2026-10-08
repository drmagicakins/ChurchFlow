<?php

return [
    // §18/§28: platform-admin configuration, never a hard-coded constant.
    // These are only the ENV-backed defaults — PlatformSettingsService
    // checks a runtime-editable override (platform_settings table) first
    // and falls back to these when no admin has set one yet.
    'sms_price_per_unit' => env('SMS_PRICE_PER_UNIT'),
    'default_tax_rate' => env('DEFAULT_TAX_RATE', '0'),

    /*
     |--------------------------------------------------------------------------
     | The price list — the ONE place a price is written down
     |--------------------------------------------------------------------------
     | Starter / Growth / Denomination / Enterprise, with their prices stated.
     |
     | Two tables used to carry plan data independently — the `plans` table
     | (what the app actually charges: checkout, billing, plan limits) and
     | config('marketing.plans') (what the marketing pages displayed). The
     | marketing copy had `price => null` on every tier and rendered "Price on
     | request", while the seeded `plans` rows had real numbers, so the pricing
     | page and the checkout page could disagree about what a plan costs. That
     | is the single worst bug a pricing page can have.
     |
     | They now both read from here. PlanSeeder seeds the `plans` table from
     | this array, and the marketing pages resolve their displayed price from
     | the same array by `key`. Changing a number here changes it everywhere,
     | or — once the platform admin edits a plan — everywhere except this file,
     | because `plans` remains the runtime source of truth (see the
     | price_source note below).
     |
     | Prices are monthly in NGN. `yearly_price` is the discounted annual
     | equivalent (~2 months free), and Enterprise is quoted rather than
     | listed because a bespoke agreement can't honestly carry a sticker price.
     */
    'plans' => [
        'starter' => [
            'name' => 'Starter',
            'slug' => 'starter',
            'monthly_price' => 10000,
            'yearly_price' => 100000,
            'max_members' => 200,
            'max_branches' => 1,
            'max_admins' => 3,
            'storage_mb' => 1024,
            'sort_order' => 1,
            'features' => ['api_access' => false, 'custom_domain' => false, 'advanced_reports' => false],
        ],
        'growth' => [
            'name' => 'Growth',
            'slug' => 'growth',
            'monthly_price' => 25000,
            'yearly_price' => 250000,
            'max_members' => 1000,
            'max_branches' => 5,
            'max_admins' => 10,
            'storage_mb' => 10240,
            'sort_order' => 2,
            'features' => ['api_access' => false, 'custom_domain' => false, 'advanced_reports' => true],
        ],
        'denomination' => [
            'name' => 'Denomination',
            'slug' => 'denomination',
            'monthly_price' => 60000,
            'yearly_price' => 600000,
            'max_members' => null, // unlimited
            'max_branches' => null,
            'max_admins' => null,
            'storage_mb' => 51200,
            'sort_order' => 3,
            'features' => ['api_access' => true, 'custom_domain' => true, 'advanced_reports' => true],
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            // Null means "quoted individually", never zero. TrialService and
            // the plan picker both treat a null price as "talk to us" rather
            // than as a free tier — see PlanSeeder, which seeds this row with
            // both prices null and is_active = false, so it is advertised but
            // not self-serve purchasable.
            'monthly_price' => null,
            'yearly_price' => null,
            'max_members' => null,
            'max_branches' => null,
            'max_admins' => null,
            'storage_mb' => 102400,
            'sort_order' => 4,
            'is_active' => false, // advertised, not directly purchasable — see Contact
            'features' => ['api_access' => true, 'custom_domain' => true, 'advanced_reports' => true],
        ],
    ],
];

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'monthly_price', 'yearly_price', 'currency',
        'max_members', 'max_branches', 'max_admins', 'storage_mb',
        'features', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function priceFor(string $billingInterval): string
    {
        return $billingInterval === 'yearly' && $this->yearly_price !== null
            ? (string) $this->yearly_price
            : (string) $this->monthly_price;
    }

    /**
     * The price as a human reads it: currency symbol, thousands separators,
     * and an explicit fallback for a plan that is quoted rather than listed.
     *
     * Centralised here because the same number is displayed in at least six
     * places (landing page, marketing pricing, post-registration plan picker,
     * billing hub, plan-change table, checkout review) and six copies of
     * '₦'.number_format() is six chances to render ₦25000 where the rest of
     * the site says ₦25,000.
     */
    public function displayPrice(?string $interval = 'monthly'): string
    {
        $amount = $interval === 'yearly' ? $this->yearly_price : $this->monthly_price;

        if ($amount === null) {
            return 'Custom';
        }

        return self::currencySymbol($this->currency).number_format((float) $amount);
    }

    /**
     * True when this plan can be bought self-serve. A plan with no monthly
     * price is quoted individually (Enterprise), so it is advertised with a
     * "Talk to us" CTA rather than a checkout button — there is no amount to
     * charge, and sending someone to a payment page for an unspecified sum
     * is worse than sending them to a conversation.
     */
    public function isSelfServe(): bool
    {
        return $this->monthly_price !== null;
    }

    public static function currencySymbol(?string $currency): string
    {
        return match ($currency) {
            'NGN' => '₦',
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => ($currency ? $currency.' ' : ''),
        };
    }

    public function hasFeature(string $key): bool
    {
        return (bool) ($this->features[$key] ?? false);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Deliberately does NOT use BelongsToTenant, the same reasoning as User: a
 * subscription checkout exists BEFORE any church does, so there is no
 * tenant to scope it to yet. Every query against this model filters by
 * user_id or church_id explicitly wherever it matters (see CheckoutService,
 * PaymentWebhookHandler) rather than relying on an ambient global scope.
 */
class Checkout extends Model
{
    protected $fillable = [
        'user_id', 'church_id', 'purpose', 'plan_id', 'billing_interval', 'sms_units',
        'amount', 'subtotal', 'tax_rate', 'tax_amount', 'currency', 'status',
        'provider', 'provider_reference', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}

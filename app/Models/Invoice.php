<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'church_id', 'checkout_id', 'type', 'amount', 'tax_amount', 'tax_rate', 'currency', 'status',
        'description', 'provider', 'provider_reference', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function checkout()
    {
        return $this->belongsTo(Checkout::class);
    }

    /**
     * `amount` is the subtotal (and, for a proration credit, may be
     * negative — see ProrationCalculator); `total` is what's actually
     * due/refunded, computed on read rather than stored twice.
     */
    public function total(): string
    {
        return bcadd((string) $this->amount, (string) $this->tax_amount, 2);
    }
}

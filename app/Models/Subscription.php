<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use BelongsToTenant, Auditable;

    protected $fillable = [
        'church_id', 'plan_id', 'status', 'billing_interval',
        'current_period_start', 'current_period_end',
        'cancel_requested_at', 'cancelled_at', 'provider', 'provider_subscription_reference',
    ];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'cancel_requested_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function isUsable(): bool
    {
        return in_array($this->status, ['active', 'past_due', 'grace_period'], true);
    }
}

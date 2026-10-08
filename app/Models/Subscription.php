<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use BelongsToTenant, Auditable;

    protected $fillable = [
        'church_id', 'plan_id', 'pending_plan_id', 'status', 'billing_interval',
        'current_period_start', 'current_period_end', 'trial_ends_at', 'trial_converted_at',
        'cancel_requested_at', 'cancelled_at', 'provider', 'provider_subscription_reference',
    ];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'trial_ends_at' => 'datetime',
            'trial_converted_at' => 'datetime',
            'cancel_requested_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /** The plan selected during a trial, to be applied when it converts. */
    public function pendingPlan()
    {
        return $this->belongsTo(Plan::class, 'pending_plan_id');
    }

    /**
     * A trial is a subscription like any other, distinguished only by its
     * status — see TrialService's docblock for why this is not a separate
     * concept with its own table.
     */
    public function isTrialing(): bool
    {
        return $this->status === 'trialing';
    }

    /** True once the free days are gone but the row has not moved on yet. */
    public function trialHasEnded(): bool
    {
        return $this->isTrialing() && $this->trial_ends_at !== null && $this->trial_ends_at->isPast();
    }

    public function isUsable(): bool
    {
        return in_array($this->status, ['trialing', 'active', 'past_due', 'grace_period'], true);
    }
}

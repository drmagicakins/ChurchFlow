<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubventionSubmission extends Model
{
    use BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = [
        'church_id', 'subvention_period_id', 'organizational_unit_id', 'subvention_rule_set_id',
        'figures', 'status', 'approval_status',
        'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'return_reason',
    ];

    /**
     * Mirrors the migration's column defaults. Without these, a freshly
     * created submission carries null in memory until it is reloaded, and
     * every workflow guard that asks "is this still a draft?" (submit(),
     * reopen(), ApprovalWorkflow) would reject a brand-new submission that
     * was never explicitly given a status.
     */
    protected $attributes = [
        'status' => 'draft',
        'approval_status' => 'not_required',
    ];

    protected function casts(): array
    {
        return [
            'figures' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function period()
    {
        return $this->belongsTo(SubventionPeriod::class, 'subvention_period_id');
    }

    public function organizationalUnit()
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function ruleSet()
    {
        return $this->belongsTo(SubventionRuleSet::class, 'subvention_rule_set_id');
    }

    public function calculations()
    {
        return $this->hasMany(SubventionCalculation::class)->latest('id');
    }

    /** The most recent calculation — calculations are versioned, never overwritten. */
    public function latestCalculation()
    {
        return $this->hasOne(SubventionCalculation::class)->latestOfMany();
    }

    public function approval()
    {
        return $this->morphOne(Approval::class, 'approvable', 'approvable_type', 'approvable_id')->latestOfMany();
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = [
        'church_id', 'organizational_unit_id', 'member_id',
        'principal_amount', 'monthly_deduction', 'interest_rate', 'status', 'notes',
    ];

    /**
     * Mirrors the migration's `status` default. Without it a freshly created
     * Loan has a null in-memory status until it is reloaded from the
     * database, so LoanService::recordPayment()'s "is this loan active?"
     * guard would reject a brand-new loan that was never explicitly given a
     * status.
     */
    protected $attributes = [
        'status' => 'active',
        'interest_rate' => 0,
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'monthly_deduction' => 'decimal:2',
            'interest_rate' => 'decimal:2',
        ];
    }

    public function organizationalUnit()
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function payments()
    {
        return $this->hasMany(LoanPayment::class);
    }

    public function borrowerLabel(): string
    {
        return $this->organizationalUnit?->name ?? $this->member?->full_name ?? 'Unknown borrower';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SubventionCalculation extends Model
{
    /**
     * The direction the stored `shortfall` was written in. The engine was
     * corrected to (retention − deductions); rows written before that hold
     * (deductions − retention) and are marked as such by the migration that
     * added this column. The marker is recorded on the row itself, so the
     * boundary is a fact rather than a date guessed from the data.
     */
    public const SHORTFALL_RETENTION_MINUS_DEDUCTIONS = 'retention_minus_deductions';

    public const SHORTFALL_DEDUCTIONS_MINUS_RETENTION = 'deductions_minus_retention';

    /**
     * The shortfall as it should be read today, whichever direction the row
     * was stored in. Rows marked with the old direction are flipped.
     */
    public function shortfallInCurrentDirection(): float
    {
        $shortfall = (float) $this->shortfall;

        if ($this->shortfall_direction === self::SHORTFALL_DEDUCTIONS_MINUS_RETENTION) {
            return -$shortfall;
        }

        return $shortfall;
    }

    protected $fillable = [
        'subvention_submission_id', 'retention_total', 'deduction_total', 'shortfall',
        'shortfall_direction',
        'loan_id', 'loan_deduction_applied', 'remittance_amount', 'breakdown',
        'loan_payment_id', 'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'retention_total' => 'decimal:2',
            'deduction_total' => 'decimal:2',
            'shortfall' => 'decimal:2',
            'loan_deduction_applied' => 'decimal:2',
            'remittance_amount' => 'decimal:2',
            'breakdown' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('viaSubmissionTenant', function (Builder $builder) {
            if (app()->bound('tenant.disabled') && app('tenant.disabled') === true) {
                return;
            }

            $churchId = app()->bound('tenant.church_id') ? app('tenant.church_id') : null;

            if ($churchId === null) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $builder->whereHas(
                'submission',
                fn ($q) => $q->withoutGlobalScopes()->where('church_id', $churchId)
            );
        });
    }

    public function submission()
    {
        return $this->belongsTo(SubventionSubmission::class, 'subvention_submission_id');
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function loanPayment()
    {
        return $this->belongsTo(LoanPayment::class);
    }
}

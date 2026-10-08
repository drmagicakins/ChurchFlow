<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SubventionCalculation extends Model
{
    protected $fillable = [
        'subvention_submission_id', 'retention_total', 'deduction_total', 'shortfall',
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

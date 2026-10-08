<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LoanPayment extends Model
{
    protected $fillable = ['loan_id', 'amount', 'paid_on', 'recorded_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_on' => 'date'];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('viaLoanTenant', function (Builder $builder) {
            if (app()->bound('tenant.disabled') && app('tenant.disabled') === true) {
                return;
            }

            $churchId = app()->bound('tenant.church_id') ? app('tenant.church_id') : null;

            if ($churchId === null) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $builder->whereHas('loan', fn ($q) => $q->withoutGlobalScopes()->where('church_id', $churchId));
        });
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}

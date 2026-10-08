<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BudgetItem extends Model
{
    protected $fillable = ['budget_id', 'category', 'planned_amount'];

    protected $casts = ['planned_amount' => 'decimal:2'];

    protected static function booted(): void
    {
        static::addGlobalScope('viaBudgetTenant', function (Builder $builder) {
            if (app()->bound('tenant.disabled') && app('tenant.disabled') === true) {
                return;
            }

            $churchId = app()->bound('tenant.church_id') ? app('tenant.church_id') : null;

            if ($churchId === null) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $builder->whereHas('budget', fn ($q) => $q->withoutGlobalScopes()->where('church_id', $churchId));
        });
    }

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }
}

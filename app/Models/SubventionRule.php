<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SubventionRule extends Model
{
    protected $fillable = [
        'subvention_rule_set_id', 'name', 'base_field', 'type',
        'rate', 'fixed_amount', 'cap_amount', 'classification', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
            'cap_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('viaRuleSetTenant', function (Builder $builder) {
            if (app()->bound('tenant.disabled') && app('tenant.disabled') === true) {
                return;
            }

            $churchId = app()->bound('tenant.church_id') ? app('tenant.church_id') : null;

            if ($churchId === null) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $builder->whereHas(
                'ruleSet',
                fn ($q) => $q->withoutGlobalScopes()->where('church_id', $churchId)
            );
        });
    }

    public function ruleSet()
    {
        return $this->belongsTo(SubventionRuleSet::class, 'subvention_rule_set_id');
    }
}

<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Budget extends Model
{
    use BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = ['church_id', 'financial_account_id', 'name', 'period_start', 'period_end', 'status'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date'];
    }

    public function financialAccount()
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function items()
    {
        return $this->hasMany(BudgetItem::class);
    }
}

<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use BelongsToTenant, Auditable;

    // No SoftDeletes on purpose: a financial transaction is never deleted,
    // soft or otherwise (§48). Mistakes are corrected via voidTransaction()
    // (see TransactionService), which leaves the original row intact with
    // is_void=true and a required reason, plus, where appropriate, a
    // reversing entry — never a silent edit or removal.

    protected $fillable = [
        'church_id', 'financial_account_id', 'type', 'category', 'amount',
        'description', 'transacted_on', 'member_id', 'transfer_group_id',
        'recorded_by', 'approval_status', 'is_void', 'voided_at', 'void_reason', 'voided_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transacted_on' => 'date',
            'is_void' => 'boolean',
            'voided_at' => 'datetime',
        ];
    }

    public function financialAccount()
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approval()
    {
        return $this->morphOne(Approval::class, 'approvable', 'approvable_type', 'approvable_id')->latestOfMany();
    }

    public function scopeApproved($query)
    {
        return $query->whereIn('approval_status', ['approved', 'not_required']);
    }

    public function scopeNotVoid($query)
    {
        return $query->where('is_void', false);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('approval_status', 'pending');
    }
}

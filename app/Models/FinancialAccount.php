<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialAccount extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = ['church_id', 'name', 'type', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Computed on read from the transaction ledger — never a stored,
     * mutable column. This is the deliberate opposite of the legacy app's
     * loans_svp.total_repay, which was overwritten in place by a report
     * page and could drift silently from reality. Void and pending-approval
     * transactions never contribute.
     */
    public function balance(): string
    {
        $income = $this->transactions()
            ->approved()->notVoid()
            ->whereIn('type', ['income', 'donation', 'transfer_in'])
            ->sum('amount');

        $outgoing = $this->transactions()
            ->approved()->notVoid()
            ->whereIn('type', ['expense', 'transfer_out'])
            ->sum('amount');

        return bcsub((string) $income, (string) $outgoing, 2);
    }
}

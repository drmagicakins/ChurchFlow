<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A pastor's settlement account — where a subvention or remittance is paid.
 *
 * This is the pastor's OWN bank account, deliberately distinct from the
 * church's FinancialAccount rows (which hold the church's funds). The pastor
 * enters it; the super-admin/registrar reads it to make the transfer.
 */
class BankAccount extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = [
        'church_id', 'user_id',
        'bank_code', 'bank_name', 'account_name', 'account_number',
        'is_verified',
    ];

    protected $casts = ['is_verified' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** The masked account number for display in lists — never the full number. */
    public function maskedNumber(): string
    {
        $number = (string) $this->account_number;
        if (strlen($number) <= 4) {
            return $number;
        }

        return str_repeat('•', max(strlen($number) - 4, 0)).substr($number, -4);
    }
}

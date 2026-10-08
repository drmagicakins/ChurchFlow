<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SmsTransaction extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'church_id', 'sms_wallet_id', 'type', 'units', 'balance_after',
        'reference', 'description', 'created_by',
    ];

    public function wallet()
    {
        return $this->belongsTo(SmsWallet::class, 'sms_wallet_id');
    }
}

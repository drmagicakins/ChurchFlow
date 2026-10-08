<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SmsWallet extends Model
{
    use BelongsToTenant;

    protected $fillable = ['church_id', 'balance_units'];

    /**
     * The wallet's ledger.
     *
     * `latest('id')` is the important part. An unordered `latest()` is not a
     * reliable "most recent row": `latest()` defaults to `created_at`, and
     * several ledger rows written inside one transaction share a timestamp to
     * the second, so the tie is broken by whatever order the storage engine
     * happens to return. That made `transactions()->latest()->first()` hand
     * back a previous entry instead of the refund that had just been appended.
     * `id` is monotonic, so it is the honest "most recent" key for a ledger.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(SmsTransaction::class)->latest('id');
    }
}

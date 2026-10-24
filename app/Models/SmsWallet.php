<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SmsWallet extends Model
{
    use BelongsToTenant;

    protected $fillable = ['church_id', 'organizational_unit_id', 'balance_units'];

    /**
     * A wallet belongs either to the CHURCH as a whole (organizational_unit_id
     * = 0) or to one parish. The sentinel is 0 rather than NULL because the
     * unique index (church_id, organizational_unit_id) has to allow exactly one
     * church-wide wallet, and two NULLs are distinct in MySQL — a NULL column
     * would have let a church accumulate any number of "the" church wallet.
     */
    public const CHURCH_WIDE = 0;

    public function organizationalUnit()
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    /** Human label for the owner of this wallet. */
    public function ownerLabel(): string
    {
        if ((int) $this->organizational_unit_id === self::CHURCH_WIDE) {
            return 'Main church';
        }

        return $this->organizationalUnit?->name ?? 'Parish';
    }

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

<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsCampaign extends Model
{
    use HasFactory, BelongsToTenant, Auditable;

    protected $fillable = [
        'church_id', 'wallet_unit_id', 'name', 'message', 'audience_type',
        'organizational_unit_id', 'department_id', 'group_id',
        'status', 'estimated_units', 'reserved_units', 'created_by', 'scheduled_at', 'sent_at',
    ];

    /**
     * The wallet this campaign is billed to (§17-19): the parish's own, or the
     * church's when the creator is church-wide. Resolved from the campaign, not
     * from whoever happens to trigger the send, so the campaign the pastor
     * approved is the campaign that sends.
     */
    public function wallet(): SmsWallet
    {
        return app(\App\Domains\Communication\Services\SmsWalletService::class)
            ->walletFor($this->church, (int) ($this->wallet_unit_id ?: SmsWallet::CHURCH_WIDE));
    }

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function recipients()
    {
        return $this->hasMany(SmsCampaignRecipient::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SmsCampaignRecipient extends Model
{
    protected $fillable = [
        'sms_campaign_id', 'member_id', 'phone', 'segments',
        'status', 'provider_message_id', 'failed_reason',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('viaCampaignTenant', function (Builder $builder) {
            if (app()->bound('tenant.disabled') && app('tenant.disabled') === true) {
                return;
            }

            $churchId = app()->bound('tenant.church_id') ? app('tenant.church_id') : null;

            if ($churchId === null) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $builder->whereHas(
                'campaign',
                fn ($q) => $q->withoutGlobalScopes()->where('church_id', $churchId)
            );
        });
    }

    public function campaign()
    {
        return $this->belongsTo(SmsCampaign::class, 'sms_campaign_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}

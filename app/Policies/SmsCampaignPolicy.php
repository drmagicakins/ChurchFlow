<?php

namespace App\Policies;

use App\Models\SmsCampaign;
use App\Models\User;

class SmsCampaignPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sms.manage');
    }

    public function view(User $user, SmsCampaign $campaign): bool
    {
        return $this->sameTenant($user, $campaign) && $user->hasPermission('sms.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sms.manage');
    }

    public function update(User $user, SmsCampaign $campaign): bool
    {
        return $this->sameTenant($user, $campaign) && $user->hasPermission('sms.manage');
    }
}

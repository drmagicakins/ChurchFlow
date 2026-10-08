<?php

namespace App\Policies;

use App\Models\SubventionRuleSet;
use App\Models\User;

class SubventionRuleSetPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('subvention.submit') || $user->hasPermission('subvention.manage');
    }

    public function view(User $user, SubventionRuleSet $ruleSet): bool
    {
        return $this->sameTenant($user, $ruleSet);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('subvention.manage');
    }

    public function update(User $user, SubventionRuleSet $ruleSet): bool
    {
        return $this->sameTenant($user, $ruleSet) && $user->hasPermission('subvention.manage');
    }
}

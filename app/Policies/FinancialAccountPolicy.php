<?php

namespace App\Policies;

use App\Models\FinancialAccount;
use App\Models\User;

class FinancialAccountPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('finance.view');
    }

    public function view(User $user, FinancialAccount $account): bool
    {
        return $this->sameTenant($user, $account) && $user->hasPermission('finance.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('settings.manage');
    }
}

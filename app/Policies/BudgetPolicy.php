<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

class BudgetPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('finance.view');
    }

    public function view(User $user, Budget $budget): bool
    {
        return $this->sameTenant($user, $budget) && $user->hasPermission('finance.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('budgets.manage');
    }

    public function update(User $user, Budget $budget): bool
    {
        return $this->sameTenant($user, $budget) && $user->hasPermission('budgets.manage');
    }
}

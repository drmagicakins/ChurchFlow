<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;

class LoanPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('loans.manage') || $user->hasPermission('finance.view');
    }

    public function view(User $user, Loan $loan): bool
    {
        return $this->sameTenant($user, $loan) && ($user->hasPermission('loans.manage') || $user->hasPermission('finance.view'));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('loans.manage');
    }

    public function update(User $user, Loan $loan): bool
    {
        return $this->sameTenant($user, $loan) && $user->hasPermission('loans.manage');
    }
}

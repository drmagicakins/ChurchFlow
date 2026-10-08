<?php

namespace App\Policies;

use App\Models\Approval;
use App\Models\User;

class ApprovalPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('finance.approve') || $user->hasPermission('subvention.approve');
    }

    public function view(User $user, Approval $approval): bool
    {
        return $this->sameTenant($user, $approval);
    }

    /** "update" = approve/reject */
    public function update(User $user, Approval $approval): bool
    {
        return $this->sameTenant($user, $approval)
            && ($user->hasPermission('finance.approve') || $user->hasPermission('subvention.approve'));
    }
}

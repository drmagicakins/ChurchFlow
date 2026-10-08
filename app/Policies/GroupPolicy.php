<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('groups.manage') || $user->hasPermission('members.view');
    }

    public function view(User $user, Group $group): bool
    {
        return $this->sameTenant($user, $group);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('groups.manage');
    }

    public function update(User $user, Group $group): bool
    {
        return $this->sameTenant($user, $group) && $user->hasPermission('groups.manage');
    }

    public function delete(User $user, Group $group): bool
    {
        return $this->sameTenant($user, $group) && $user->hasPermission('groups.manage');
    }
}

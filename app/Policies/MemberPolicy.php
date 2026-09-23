<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

class MemberPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('members.view');
    }

    public function view(User $user, Member $member): bool
    {
        return $this->sameTenant($user, $member) && $user->hasPermission('members.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('members.create');
    }

    public function update(User $user, Member $member): bool
    {
        return $this->sameTenant($user, $member) && $user->hasPermission('members.edit');
    }

    public function delete(User $user, Member $member): bool
    {
        return $this->sameTenant($user, $member) && $user->hasPermission('members.delete');
    }
}

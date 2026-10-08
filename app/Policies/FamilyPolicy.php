<?php

namespace App\Policies;

use App\Models\Family;
use App\Models\User;

class FamilyPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('families.manage') || $user->hasPermission('members.view');
    }

    public function view(User $user, Family $family): bool
    {
        return $this->sameTenant($user, $family);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('families.manage');
    }

    public function update(User $user, Family $family): bool
    {
        return $this->sameTenant($user, $family) && $user->hasPermission('families.manage');
    }

    public function delete(User $user, Family $family): bool
    {
        return $this->sameTenant($user, $family) && $user->hasPermission('families.manage');
    }
}

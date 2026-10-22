<?php

namespace App\Policies;

use App\Models\OrganizationalUnit;
use App\Models\User;

/**
 * Branches. Managing the church's organisational structure is a settings-level
 * authority (the same permission that guards the church profile), not a
 * people-level one: renaming or adding a branch changes how every scoped
 * record is filed, which is exactly the blast radius settings.manage already
 * covers. Any member-visible role may view the list.
 */
class OrganizationalUnitPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('members.view') || $user->hasPermission('settings.manage');
    }

    public function view(User $user, OrganizationalUnit $unit): bool
    {
        return $this->sameTenant($user, $unit);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('settings.manage');
    }

    public function update(User $user, OrganizationalUnit $unit): bool
    {
        return $this->sameTenant($user, $unit) && $user->hasPermission('settings.manage');
    }

    public function delete(User $user, OrganizationalUnit $unit): bool
    {
        return $this->sameTenant($user, $unit) && $user->hasPermission('settings.manage');
    }
}

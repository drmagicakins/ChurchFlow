<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('departments.manage') || $user->hasPermission('members.view');
    }

    public function view(User $user, Department $department): bool
    {
        return $this->sameTenant($user, $department);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('departments.manage');
    }

    public function update(User $user, Department $department): bool
    {
        return $this->sameTenant($user, $department) && $user->hasPermission('departments.manage');
    }

    public function delete(User $user, Department $department): bool
    {
        return $this->sameTenant($user, $department) && $user->hasPermission('departments.manage');
    }
}

<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true; // any authenticated church staff member can create a task
    }

    public function update(User $user, Task $task): bool
    {
        return $this->sameTenant($user, $task)
            && ($user->id === $task->assigned_to || $user->id === $task->created_by || $user->hasPermission('settings.manage'));
    }
}

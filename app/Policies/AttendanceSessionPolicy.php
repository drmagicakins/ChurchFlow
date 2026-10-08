<?php

namespace App\Policies;

use App\Models\AttendanceSession;
use App\Models\User;

class AttendanceSessionPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance.manage') || $user->hasPermission('members.view');
    }

    public function view(User $user, AttendanceSession $session): bool
    {
        return $this->sameTenant($user, $session);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('attendance.manage');
    }

    public function update(User $user, AttendanceSession $session): bool
    {
        return $this->sameTenant($user, $session) && $user->hasPermission('attendance.manage');
    }
}

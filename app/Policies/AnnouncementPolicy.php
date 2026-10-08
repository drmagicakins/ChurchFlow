<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $this->sameTenant($user, $announcement);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('announcements.manage');
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $this->sameTenant($user, $announcement) && $user->hasPermission('announcements.manage');
    }
}

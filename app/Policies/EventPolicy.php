<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // any authenticated church user can browse events
    }

    public function view(User $user, Event $event): bool
    {
        return $this->sameTenant($user, $event);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('events.manage');
    }

    public function update(User $user, Event $event): bool
    {
        return $this->sameTenant($user, $event) && $user->hasPermission('events.manage');
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->sameTenant($user, $event) && $user->hasPermission('events.manage');
    }
}

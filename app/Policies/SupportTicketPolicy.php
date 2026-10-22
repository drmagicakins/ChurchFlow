<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

class SupportTicketPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // every church user can see their own church's tickets; platform admins see all via tenant.disabled
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return $user->is_platform_admin || $this->sameTenant($user, $ticket);
    }

    public function create(User $user): bool
    {
        return true; // any authenticated church user can raise a ticket
    }

    /** "update" here = reply/assign/resolve — platform staff only; a church user's own reply has its own route. */
    public function update(User $user, SupportTicket $ticket): bool
    {
        return $user->is_platform_admin;
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        return $user->is_platform_admin || $this->sameTenant($user, $ticket);
    }
}

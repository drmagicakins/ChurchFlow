<?php

namespace App\Policies;

use App\Models\PrayerRequest;
use App\Models\User;

/**
 * §21: pastoral information must NOT be exposed to ordinary administrators.
 * Deliberately does NOT fall back to members.view, settings.manage, or any
 * other general-admin permission the way most other policies in this app
 * do — only pastoral.manage or being the assignee grants access.
 */
class PrayerRequestPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('pastoral.manage') || $this->hasAnyAssigned($user);
    }

    public function view(User $user, PrayerRequest $request): bool
    {
        return $this->sameTenant($user, $request)
            && ($user->hasPermission('pastoral.manage') || $request->assigned_to === $user->id);
    }

    public function create(User $user): bool
    {
        return true; // any authenticated church user/staff can log a prayer request on someone's behalf
    }

    public function update(User $user, PrayerRequest $request): bool
    {
        return $this->sameTenant($user, $request)
            && ($user->hasPermission('pastoral.manage') || $request->assigned_to === $user->id);
    }

    private function hasAnyAssigned(User $user): bool
    {
        return PrayerRequest::query()->where('assigned_to', $user->id)->exists();
    }
}

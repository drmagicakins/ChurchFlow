<?php

namespace App\Policies;

use App\Models\PastoralCase;
use App\Models\User;

/** §21: same deliberately narrow permission model as PrayerRequestPolicy. */
class PastoralCasePolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('pastoral.manage') || $this->hasAnyAssigned($user);
    }

    public function view(User $user, PastoralCase $case): bool
    {
        return $this->sameTenant($user, $case)
            && ($user->hasPermission('pastoral.manage') || $case->assigned_to === $user->id);
    }

    public function create(User $user): bool
    {
        return true; // any authenticated church staff member can flag a member for pastoral follow-up
    }

    public function update(User $user, PastoralCase $case): bool
    {
        return $this->sameTenant($user, $case)
            && ($user->hasPermission('pastoral.manage') || $case->assigned_to === $user->id);
    }

    private function hasAnyAssigned(User $user): bool
    {
        return PastoralCase::query()->where('assigned_to', $user->id)->exists();
    }
}

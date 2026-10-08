<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // scopeVisibleTo() on the model does the real filtering
    }

    public function view(User $user, Appointment $appointment): bool
    {
        if (!$this->sameTenant($user, $appointment)) {
            return false;
        }

        if ($appointment->pastoral_case_id === null) {
            return true;
        }

        return $user->hasPermission('pastoral.manage') || $appointment->pastor_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->sameTenant($user, $appointment)
            && ($user->hasPermission('pastoral.manage') || $appointment->pastor_id === $user->id);
    }
}

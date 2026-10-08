<?php

namespace App\Policies;

use App\Models\SubventionSubmission;
use App\Models\User;

class SubventionSubmissionPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('subvention.submit') || $user->hasPermission('subvention.approve');
    }

    public function view(User $user, SubventionSubmission $submission): bool
    {
        return $this->sameTenant($user, $submission)
            && ($user->hasPermission('subvention.submit') || $user->hasPermission('subvention.approve'));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('subvention.submit');
    }

    /** "update" here = editing figures while still in draft/returned. */
    public function update(User $user, SubventionSubmission $submission): bool
    {
        return $this->sameTenant($user, $submission)
            && $user->hasPermission('subvention.submit')
            && in_array($submission->status, ['draft', 'returned'], true);
    }

    public function review(User $user, SubventionSubmission $submission): bool
    {
        return $this->sameTenant($user, $submission) && $user->hasPermission('subvention.approve');
    }
}

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
        if (! $this->sameTenant($user, $submission)) {
            return false;
        }

        if (! ($user->hasPermission('subvention.submit') || $user->hasPermission('subvention.approve'))) {
            return false;
        }

        // A pastor attached to a parish may only open their own parish's
        // submission — never another parish's. The registrar (and anyone
        // church-wide) sees every submission.
        if ($user->isParishScoped()) {
            return $submission->organizational_unit_id === $user->organizational_unit_id;
        }

        return true;
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
            && in_array($submission->status, ['draft', 'returned'], true)
            // A pastor may only edit their own parish's submission.
            && (! $user->isParishScoped() || $submission->organizational_unit_id === $user->organizational_unit_id);
    }

    /**
     * Approving/reviewing is the REGISTRAR's authority.
     *
     * The permission alone is not enough: a user attached to a parish may not
     * approve subventions (that is precisely the "user should not have access
     * to approve options on their dashboard" rule) even if their role happens
     * to carry subvention.approve. Only a registrar — or a church-wide user —
     * may decide.
     */
    public function review(User $user, SubventionSubmission $submission): bool
    {
        return $this->sameTenant($user, $submission)
            && $user->hasPermission('subvention.approve')
            && ! $user->isParishScoped();
    }
}

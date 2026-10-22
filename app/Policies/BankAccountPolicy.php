<?php

namespace App\Policies;

use App\Models\BankAccount;
use App\Models\User;

/**
 * The pastor's settlement bank account.
 *
 *  - Any user may view and set their OWN bank account (that is the "bank
 *    account area for the pastor to input their account number").
 *  - Someone with `settings.manage` — the church owner / registrar / super
 *    admin — may view and verify everyone's, because they are the ones who
 *    read it to make the transfer.
 */
class BankAccountPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        // The list of settlement accounts is a finance/settings concern.
        return $user->hasPermission('finance.view')
            || $user->hasPermission('settings.manage');
    }

    public function view(User $user, BankAccount $account): bool
    {
        if (! $this->sameTenant($user, $account)) {
            return false;
        }

        return $account->user_id === $user->id
            || $user->hasPermission('settings.manage')
            || $user->hasPermission('finance.view');
    }

    /** A pastor sets their own; an administrator may set one on their behalf. */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, BankAccount $account): bool
    {
        return $this->sameTenant($user, $account)
            && ($account->user_id === $user->id || $user->hasPermission('settings.manage'));
    }

    /** Verification is an administrative act, not something a pastor self-grants. */
    public function verify(User $user, BankAccount $account): bool
    {
        return $this->sameTenant($user, $account) && $user->hasPermission('settings.manage');
    }

    public function delete(User $user, BankAccount $account): bool
    {
        return $this->sameTenant($user, $account)
            && ($account->user_id === $user->id || $user->hasPermission('settings.manage'));
    }
}

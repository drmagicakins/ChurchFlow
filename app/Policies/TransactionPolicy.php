<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('finance.view');
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $this->sameTenant($user, $transaction) && $user->hasPermission('finance.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('finance.record');
    }

    /** "Update" here only ever means voiding — see TransactionService. */
    public function update(User $user, Transaction $transaction): bool
    {
        return $this->sameTenant($user, $transaction) && $user->hasPermission('finance.approve');
    }
}

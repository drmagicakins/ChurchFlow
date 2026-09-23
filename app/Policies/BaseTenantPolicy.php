<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Every tenant-scoped model's policy extends this and adds permission-name
 * checks on top. The church_id check here is a deliberate SECOND layer on
 * top of TenantScope (defense in depth): TenantScope stops a cross-tenant
 * row from ever being fetched; this stops a request that somehow got a
 * model instance (e.g. from a queued job, a cross-relation eager load, or a
 * future refactor that bypasses the scope) from acting on it anyway.
 */
abstract class BaseTenantPolicy
{
    protected function sameTenant(User $user, Model $model): bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        return $user->church_id !== null && $user->church_id === $model->church_id;
    }
}

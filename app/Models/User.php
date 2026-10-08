<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    // Deliberately NOT using BelongsToTenant here: users legitimately need
    // to be looked up by email during login BEFORE a tenant is known
    // (church_id is what we're resolving). Every other query path (listing
    // a church's staff, etc.) filters by church_id explicitly in that
    // service/controller instead of relying on a global scope.

    protected $fillable = [
        'church_id', 'name', 'email', 'password', 'is_platform_admin', 'is_active',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            // §14 max_admins. Only counts once a user is actually attached
            // to a church — registration (Phase 1's RegisterController)
            // creates the very first User with church_id still null, before
            // any plan has even been chosen, so this never blocks signup
            // itself. A staff member invited later (adding a second admin
            // to an existing church) is exactly what this guards.
            if ($user->church_id && !$user->is_platform_admin) {
                app(\App\Domains\Subscriptions\Services\PlanLimitService::class)->assertCanAdd(
                    Church::findOrFail($user->church_id),
                    'admins',
                    static::withoutGlobalScopes()->where('church_id', $user->church_id)->where('is_platform_admin', false)->count(),
                );
            }
        });
    }

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->is_platform_admin) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('name', $permission))
            ->exists();
    }
}

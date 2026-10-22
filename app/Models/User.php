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
        'church_id', 'organizational_unit_id', 'name', 'email', 'password',
        'is_platform_admin', 'is_registrar', 'is_active',
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
            'is_registrar' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    /** The parish (OrganizationalUnit) this user belongs to, if any. */
    public function organizationalUnit()
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    /**
     * Is this user the church's registrar / super-admin?
     *
     * The registrar is the account that receives, views and approves the
     * subventions pastors submit, and that may manage other users. It is the
     * church-level equivalent of an owner and is deliberately not the same
     * thing as the platform admin (who owns no church).
     *
     * This is the explicit flag, NOT any permission: making it "settings.manage
     * or registrar" would let every church administrator delete users, which is
     * exactly the authority the brief reserves for the registrar alone.
     */
    public function isRegistrar(): bool
    {
        // The account that REGISTERED the church is always the super admin,
        // even if the explicit flag never got written. Churches created before
        // this flag existed have an owner with no registrar row, and the
        // account-creation restriction would otherwise lock the real owner out
        // of their own settings screen. The role check is the fallback: the
        // first user of a church holds the Church Owner role.
        if ($this->is_registrar) {
            return true;
        }

        return $this->roles()->where('name', 'Church Owner')->exists();
    }

    /**
     * Should this user's view be limited to their own parish?
     *
     * A registrar sees every parish; so does anyone without a parish of their
     * own (they are church-wide). Only a user explicitly attached to a parish
     * and without registrar authority is scoped down.
     */
    public function isParishScoped(): bool
    {
        return $this->organizational_unit_id !== null && ! $this->isRegistrar();
    }

    /**
     * Is this the church's owner — the account that registered it?
     *
     * Used where "the person who registered the main church" is the rule
     * (creating accounts). Kept separate from isRegistrar() only in intent;
     * both currently resolve the same way, and isRegistrar() is the canonical
     * check so the two can never drift apart.
     */
    public function isChurchOwner(): bool
    {
        return $this->isRegistrar();
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

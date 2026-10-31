<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    // CanResetPassword supplies sendPasswordResetNotification() and the
    // token plumbing Laravel's broker expects. The trait is safe here because
    // the tenant question is already settled elsewhere: this model is
    // deliberately unscoped (see below), so a reset lookup by email cannot be
    // filtered away by a tenant that is not known yet.
    use HasFactory, Notifiable, SoftDeletes, \Illuminate\Auth\Passwords\CanResetPassword;

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

    /**
     * Create (or replace) this account's password-reset token.
     *
     * The table is keyed by EMAIL, not by user id, which is what makes it safe
     * in this app: `users` is unique on (church_id, email), so one email could
     * in principle exist in two churches — and a token keyed by email would
     * them let collide. Rather than change the schema (Laravel's own), the
     * caller only ever offers a reset when the email identifies exactly ONE
     * active account; see PasswordResetTokenService.
     *
     * Only the HASH is stored. A dump of this table cannot be replayed into a
     * password change, which matters because the plain token travels by email.
     */
    public function createPasswordResetToken(): string
    {
        $plain = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $this->email],
            ['token' => hash('sha256', $plain), 'created_at' => now()],
        );

        return $plain;
    }

    /** Does a submitted reset token match the one we issued for this email? */
    public static function passwordResetTokenIsValid(string $email, string $plainToken): bool
    {
        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $row || ! $row->created_at) {
            return false;
        }

        // Same reason the login path has one: a reset link must not stay
        // useful forever. config/auth.php's passwords.expire already expresses
        // this in minutes, so the two cannot drift apart.
        //
        // The sign matters. On Carbon 3 `diffInMinutes()` returns a SIGNED
        // value, and calling it as `now()->diffInMinutes($createdAt)` yields a
        // NEGATIVE number when the token is in the past — so `-1500 > 1440` is
        // false and an expired link was accepted. Probed, not assumed:
        //   $now->diffInMinutes($past)  ->  -1500.0   (token is past expiry)
        // The comparison is therefore made on an absolute, second-precision
        // value, so it cannot depend on argument order or the Carbon major.
        $expiresAt = \Illuminate\Support\Carbon::parse($row->created_at)->addMinutes(
            (int) config('auth.passwords.users.expire', 60)
        );

        if (now()->greaterThan($expiresAt)) {
            return false;
        }

        // hash_equals: constant-time, so a token cannot be guessed by timing.
        return hash_equals($row->token, hash('sha256', $plainToken));
    }

    /** Spend the token — a link is single-use. */
    public function forgetPasswordResetToken(): void
    {
        DB::table('password_reset_tokens')->where('email', $this->email)->delete();
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    /**
     * The parish (OrganizationalUnit) this user belongs to, if any.
     */
    public function organizationalUnit()
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    /**
     * The MAIN church unit for this user's church, if one has been marked.
     *
     * "Grace Chapel — Main" is an ordinary OrganizationalUnit in this schema
     * (the hierarchy is generic, §4), but the brief treats it specially: it is
     * the church itself, and a pastor attached to it must see ONLY Main — not
     * the parishes branching off it. A unit is the main one when it has no
     * parent; the church's top-level unit is the only one that answers yes.
     */
    public function mainUnit(): ?OrganizationalUnit
    {
        if (! $this->church_id) {
            return null;
        }

        return OrganizationalUnit::query()
            ->where('church_id', $this->church_id)
            ->whereNull('parent_id')
            ->orderBy('id')
            ->first();
    }

    /**
     * Is this user attached to the MAIN church (rather than a parish)?
     *
     * Used by the subvention screen: the account that created the main church
     * receives every parish's submission, while the pastor based at Main sees
     * only Main's own.
     */
    public function isAttachedToMainChurch(): bool
    {
        if ($this->organizational_unit_id === null) {
            return false;
        }

        $main = $this->mainUnit();

        return $main !== null && (int) $main->id === (int) $this->organizational_unit_id;
    }

    /**
     * Should this user's SUBVENTION view cover the whole church?
     *
     * This is the §14 subvention rule, and it is narrower than isRegistrar():
     *
     *   - the church owner (the account that registered the church) always
     *     receives every parish's subvention and approves or rejects them;
     *   - anyone church-wide (no parish) keeps the oversight the app already
     *     gave them;
     *   - a pastor attached to Main sees Main only — "parish pastor should see
     *     only parish not main church" cuts both ways;
     *   - any other parish pastor sees their own parish only.
     */
    public function seesWholeChurchSubventions(): bool
    {
        if ($this->isRegistrar()) {
            return true;
        }

        return $this->organizational_unit_id === null;
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

<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = ['church_id', 'organizational_unit_id', 'name', 'description'];

    /**
     * The whole-church sentinel for `organizational_unit_id`.
     *
     * The column is nullable and NULL means "belongs to the whole church".
     * NULL cannot carry a UNIQUE constraint, though: SQL treats every NULL as
     * distinct, so `UNIQUE(church_id, organizational_unit_id, name)` let
     * "Choir" be created three times for the same church. Storing 0 instead
     * (the same sentinel SmsWallet already uses) makes the index real —
     * exactly one church-wide "Choir" per church.
     */
    public const WHOLE_CHURCH = 0;

    /**
     * Whether THIS database can store WHOLE_CHURCH in the column.
     *
     * Two shapes of `departments` exist in the wild:
     *
     *  - a fresh/test schema, where 2026_10_25_000001 has run and dropped the
     *    branch foreign key so the 0 sentinel is storable;
     *  - a database migrated earlier, where the foreign key
     *    `organizational_unit_id → organizational_units(id)` is still enforced.
     *    Writing 0 there fails with `SQLSTATE[23000] ... FOREIGN KEY constraint
     *    failed` — which is exactly what creating a department in the dev
     *    database did, because 0 is not a branch id.
     *
     * So the sentinel is only written where the column can hold it. Where the
     * key is still enforced, a whole-church department keeps the original NULL,
     * which is what the screen already prints as "Whole church" and what
     * visibleTo() already reads.
     */
    private static ?bool $wholeChurchSentinelAllowed = null;

    /**
     * The value a whole-church department has to be stored as on this database.
     *
     * Kept next to the sentinel so the saving hook, the setter and the duplicate
     * rule all ask the same question and can never disagree.
     */
    public static function wholeChurchValue(): int|null
    {
        if (self::$wholeChurchSentinelAllowed === null) {
            self::$wholeChurchSentinelAllowed = ! self::columnHasBranchForeignKey();
        }

        return self::$wholeChurchSentinelAllowed ? self::WHOLE_CHURCH : null;
    }

    /**
     * Does this database still enforce `organizational_unit_id → branches`?
     *
     * Asked once, then cached in the static above. `PRAGMA foreign_key_list` on
     * SQLite and `information_schema` on MySQL answer the same question; the
     * exception fallback answers "assume it IS enforced", which is the safe
     * direction: NULL is valid in both shapes, 0 is not.
     */
    private static function columnHasBranchForeignKey(): bool
    {
        $connection = (new static())->getConnection();

        try {
            if ($connection->getDriverName() === 'sqlite') {
                foreach ($connection->select("pragma foreign_key_list('departments')") as $foreign) {
                    if (($foreign->from ?? null) === 'organizational_unit_id') {
                        return true;
                    }
                }

                return false;
            }

            foreach ($connection->getSchemaBuilder()->getForeignKeys('departments') as $foreign) {
                if (($foreign['columns'] ?? []) === ['organizational_unit_id']) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * The comparable form of a department name.
     *
     * Two names that differ only by case or surrounding whitespace are the
     * same department to a human ("Choir", "choir", "Choir "), so they must
     * compare equal here as well. Applied when saving, when validating and in
     * the migration that cleans up existing rows, so the three cannot drift.
     */
    public static function normaliseName(?string $name): string
    {
        return \Illuminate\Support\Str::of((string) $name)->squish()->lower()->toString();
    }

    /**
     * The branch a department is filed against, in stored form.
     *
     * A blank/absent branch is the whole church, which is stored as
     * WHOLE_CHURCH rather than NULL so the unique index can see it.
     */
    public static function unitIdFor(mixed $organizationalUnitId): int|null
    {
        return blank($organizationalUnitId) ? self::wholeChurchValue() : (int) $organizationalUnitId;
    }

    /**
     * Departments are listed alphabetically.
     *
     * Nothing was ordering them, so SQLite handed them back in DEPARTMENT table
     * order and MySQL in whatever its index chose. A department created later
     * could therefore appear at the top of the list, which is how a new one
     * looked like it had "disappeared" — it was simply above the fold. Ordering
     * at the model means every screen and picker agrees.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('alphabetical', fn ($query) => $query->orderBy('name'));

        static::$wholeChurchSentinelAllowed = null;

        // The column has no PHP cast, so a model created without an
        // organizational_unit_id in its attributes has NOTHING set for it and
        // the setter below never runs — the row landed as SQL NULL, which is
        // exactly the value the unique index cannot see. Normalising on save
        // covers every write path (create, fill, update) with one rule.
        static::saving(function (Department $department): void {
            $department->attributes['organizational_unit_id'] = self::unitIdFor($department->organizational_unit_id);
        });
    }

    /**
     * Only the departments this user should see.
     *
     * TenantScope narrows a parish-scoped user to `organizational_unit_id =
     * their parish`, and a department filed against the WHOLE church (the
     * column is nullable and that is a supported choice) has NULL there — so
     * every church-wide department was invisible to every parish pastor, even
     * though the rest of their church structure was not. Like TenantScope, this
     * is a scope rather than a controller rule so no screen can forget it.
     *
     * The church-wide administrator (owner, or anyone with no parish) is
     * unaffected. Use it where a parish pastor is choosing which departments to
     * act on; it REPLACES TenantScope's parish filter with the wider one above,
     * and keeps the church filter.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isParishScoped()) {
            return $query;
        }

        // The global TenantScope has already ANDed `organizational_unit_id = ?`
        // onto this query. Adding an OR to it would still be ANDed with that
        // equality, so NULL rows would remain excluded — the filter has to be
        // replaced, not widened. The church id stays: that is the tenant guard,
        // and it is what keeps another church's rows out.
        return $query
            ->withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('departments.church_id', $user->church_id)
            ->where(fn (Builder $inner) => $inner
                ->whereNull('departments.organizational_unit_id')
                // Rows saved since the whole-church sentinel became 0 carry
                // that value rather than NULL; both spell "whole church", so
                // both must stay visible.
                ->orWhere('departments.organizational_unit_id', self::WHOLE_CHURCH)
                ->orWhere('departments.organizational_unit_id', $user->organizational_unit_id));
    }

    /**
     * Resolve `{department}` for route-model binding with the same rule as the
     * index list.
     *
     * TenantScope narrows a parish-scoped user to `organizational_unit_id =
     * their parish`, but a department filed against the WHOLE church stores
     * NULL — so every bound route (`show`, `update`, `destroy`, the member
     * routes) 404'd for a parish pastor, even though the department was
     * listed on the index. That is why `GET /departments/1` failed for a
     * pastor while `GET /departments` returned 200. The index only worked
     * because it calls `visibleTo()` by hand; the binding never did.
     *
     * Doing it here rather than in each controller means no route can forget
     * it, and TenantScope itself stays untouched. Any route NOT bound to a
     * model (the index) is unchanged.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $user = auth()->user();

        if ($user instanceof User && $user->church_id !== null) {
            $query = $query->visibleTo($user);
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }

    public function organizationalUnit()
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    /**
     * Every write goes through these two setters, so a department can never be
     * saved in a shape the unique index cannot see: the name is trimmed and
     * whitespace-collapsed, and a blank branch becomes WHOLE_CHURCH (0) rather
     * than NULL. Left in the model so a controller, a seeder or a test cannot
     * bypass it by writing the attributes directly.
     */
    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = \Illuminate\Support\Str::of((string) $value)->squish()->toString();
    }

    public function setOrganizationalUnitIdAttribute($value): void
    {
        $this->attributes['organizational_unit_id'] = self::unitIdFor($value);
    }

    /**
     * A branch-scoped user may only file a department against their own branch.
     *
     * The form hides the Branch select from them, so the posted body carries no
     * branch at all; without this the row would land as "whole church".
     * TenantScope already fills the column in on save, but that leaves the
     * duplicate-name rule looking at the wrong branch and depends on the tenant
     * binding being present. Setting it from the user is explicit and survives
     * a request where the binding is not there.
     */
    public static function mayAssignTo(User $user): int|null
    {
        return $user->isParishScoped() ? (int) $user->organizational_unit_id : null;
    }
    public function members()
    {
        return $this->belongsToMany(Member::class, 'department_member')
            ->withPivot('role')
            ->withTimestamps();
    }
}

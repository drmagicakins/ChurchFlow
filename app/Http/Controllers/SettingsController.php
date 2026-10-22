<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The church's own Settings area (§14, §37).
 *
 * Three things live here, and they are kept on one screen on purpose — a
 * church administrator setting up their account wants "who we are", "who has
 * access" and "what each of them can do" in one place, not scattered across
 * three routes they have to discover.
 *
 *  1. Profile      — name, denomination, timezone, currency, and the
 *                    terminology overrides (church.settings['terminology'])
 *                    that let a branch call a "Province" a "Diocese".
 *  2. Team         — every user attached to this church; the credentials to
 *                    log in are exactly the email addresses listed here.
 *  3. Roles        — the permission matrix, so an administrator can see
 *                    precisely what each role may do.
 *
 * Every list is scoped explicitly by church_id. This controller does NOT rely
 * on a global scope for the user list: User deliberately does not use
 * BelongsToTenant (it has to be findable by email before a tenant is known),
 * so the church filter here is the isolation, not a safety net on top of one.
 */
class SettingsController extends Controller
{
    /** The terminology keys the app supports. */
    private const TERMINOLOGY_KEYS = [
        'organizational_unit' => 'Branch',
        'province' => 'Province',
        'group' => 'Group',
    ];

    /**
     * The prefix of a private per-user role, e.g. "Pastor · Jane Doe · #12".
     *
     * A unique KEY, not display text. It lives here so the controller and the
     * screen cannot drift apart on what counts as "this person has individually
     * assigned permissions".
     */
    public const PRIVATE_ROLE_PREFIX = 'Pastor · ';

    public function index(Request $request): View
    {
        $this->authorizeSettings($request);

        $users = $this->usersInScope($request);
        $parishes = $this->parishesInScope($request)->orderBy('name')->get();

        $team = $users
            // Roles in a fixed order. The view and the controller both ask for
            // "the person's role" with ->roles->first(), and that relation has no
            // ORDER BY of its own: without this, a person holding more than one
            // role could be described by a different role on the screen than the
            // one the controller's owner-only checks see.
            ->with(['roles' => fn ($q) => $q->orderBy('roles.id'), 'organizationalUnit', 'roles.permissions'])
            ->orderBy('name')
            // Same name = same place on every load. Without a unique tiebreak the
            // row order can shift between requests.
            ->orderBy('id')
            ->get();

        // What each person can currently DO, flattened to permission names.
        // Precomputed here rather than in the view so the ticked boxes come from
        // the same roles/permissions the policies read — if the two ever
        // disagreed, the screen would be describing an app that does not exist.
        $permissionsByUser = $team->mapWithKeys(fn (User $u) => [
            $u->id => $u->roles->flatMap->permissions->pluck('name')->unique()->values()->all(),
        ]);

        return view('settings.index', [
            // Which tab is showing. Computed HERE, not in the template.
            //
            // `<x-layout>` captures its slot the moment the tag opens, which runs
            // this template's body AFTER the layout has already rendered. A `$tab`
            // assigned at the top of the body therefore did not exist yet when the
            // tab strip was built, so every tab rendered as inactive and the body's
            // @if fell through to the Profile branch. Verified: on a first load of
            // /settings?tab=team the Team tab is now the active one.
            'tab' => (string) $request->query('tab', 'profile'),
            'church' => $request->user()->church,
            'terminologyKeys' => self::TERMINOLOGY_KEYS,
            'terminology' => $request->user()->church?->settings['terminology'] ?? [],
            'team' => $team,
            'permissionsByUser' => $permissionsByUser,
            // The ids of team members who hold a private per-user role, i.e. whose
            // permissions were ticked by the owner. The view uses THIS (one
            // eager-loaded query for the whole page) to decide whether to show a
            // role dropdown, rather than reading a role name off a model whose
            // relation may not be loaded yet.
            'privateRoleUsers' => Role::query()
                ->where('church_id', $request->user()->church_id)
                ->where('name', 'like', self::PRIVATE_ROLE_PREFIX.'%')
                ->with('users:id')
                ->get()
                ->flatMap->users
                ->pluck('id')
                ->unique()
                ->values()
                ->all(),
            'roles' => Role::query()
                ->where('church_id', $request->user()->church_id)
                ->with('permissions')
                ->withCount('users')
                ->orderBy('name')
                ->get(),
            // §14/§37: the parishes a team member can be attached to, so the
            // "parish" selector on the team form has something to offer. A
            // parish-scoped admin is only offered their OWN parish.
            'parishes' => $parishes,
            'permissionGroups' => Permission::query()
                ->orderBy('group')
                ->orderBy('name')
                ->get()
                ->groupBy('group'),
        ]);
    }

    /**
     * The team members THIS user is allowed to see.
     *
     * A church-wide administrator (the owner/registrar, or anyone with no
     * parish) sees the whole church. An administrator attached to a parish sees
     * only that parish's people — the same rule TenantScope applies to members,
     * loans, events and the rest, applied here where it cannot be: `users` is
     * deliberately not a BelongsToTenant model (it has to be findable by email
     * before a tenant is known), so this controller is the isolation.
     */
    private function usersInScope(Request $request)
    {
        $user = $request->user();

        $query = User::query()->where('church_id', $user->church_id);

        if ($user->isParishScoped()) {
            // Their own account stays visible to themselves, so a pastor can
            // always see who they are signed in as even if the parish changes.
            $query->where(function ($q) use ($user) {
                $q->where('organizational_unit_id', $user->organizational_unit_id)
                    ->orWhere('id', $user->id);
            });
        }

        return $query;
    }

    /**
     * The parishes THIS user is allowed to see (and assign people to).
     *
     * Previously this listed every OrganizationalUnit of the church by id, which
     * meant a branch pastor opening Settings could see the other parishes' names
     * in the parish picker — while every other page correctly hid their people
     * and money. A parish-scoped user now gets only their own.
     */
    private function parishesInScope(Request $request)
    {
        $user = $request->user();

        return \App\Models\OrganizationalUnit::query()
            ->where('church_id', $user->church_id)
            ->when($user->isParishScoped(), fn ($q) => $q->whereKey($user->organizational_unit_id));
    }

    /** Church profile + terminology. */
    public function updateProfile(Request $request): RedirectResponse
    {
        $this->authorizeSettings($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'denomination' => ['nullable', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:64'],
            'currency' => ['required', 'string', 'size:3'],
            'terminology' => ['nullable', 'array'],
            'terminology.*' => ['nullable', 'string', 'max:60'],
        ]);

        $church = $request->user()->church;

        // Merge rather than replace: a key the form did not submit (a future
        // terminology option, or a value set by another screen) must survive
        // this save instead of being wiped back to default.
        $settings = $church->settings ?? [];
        $settings['terminology'] = array_filter(
            array_merge($settings['terminology'] ?? [], $data['terminology'] ?? []),
            fn ($value) => $value !== null && $value !== '',
        );

        $church->update([
            'name' => $data['name'],
            'denomination' => $data['denomination'] ?? null,
            'timezone' => $data['timezone'],
            'currency' => strtoupper($data['currency']),
            'settings' => $settings,
        ]);

        return back()->with('status', 'Church profile updated.');
    }

    /**
     * Invite (create) a team member.
     *
     * The password is set here rather than mailed, because this deployment has
     * no outbound mail configured and a "check your inbox" flow with no inbox
     * is a dead end. The administrator shares the credentials directly, and
     * every account created this way is visible in the team list below.
     */
    public function storeTeamMember(Request $request): RedirectResponse
    {
        $this->authorizeSettings($request);

        // §14: creating an ACCOUNT is the super-admin's authority alone.
        //
        // A branch pastor normally holds settings.manage so they can keep their
        // own parish's profile and see the role matrix — but that must not let
        // them mint new sign-ins, or a branch could produce accounts (and, via
        // role assignment, authority) the church owner never sanctioned. The
        // check is by the explicit registrar flag, not by permission, for the
        // same reason user deletion is: it is an ownership right, not a
        // capability that happens to be granted.
        abort_unless(
            $request->user()->isRegistrar(),
            403,
            'Only the church owner (the account that registered this church) can create user accounts.',
        );

        $church = $request->user()->church;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('church_id', $church?->id)],
            // §14/§37: the parish this user belongs to. Blank = church-wide
            // (they see everything their role allows, not scoped to a parish).
            'organizational_unit_id' => ['nullable', 'integer', Rule::exists('organizational_units', 'id')->where('church_id', $church?->id)],
            'is_registrar' => ['nullable', 'boolean'],
        ], [
            'email.unique' => 'An account with that email already exists.',
        ]);

        // Only the registrar decides who else is a registrar. Otherwise the
        // checkbox in the form would be a way for any administrator to hand
        // themselves the ownership right this change exists to protect.
        if (! empty($data['is_registrar']) && ! $request->user()->isRegistrar()) {
            abort(403, 'Only the church owner can grant registrar authority.');
        }

        $member = DB::transaction(function () use ($data, $church) {
            $member = User::create([
                'church_id' => $church->id,
                'organizational_unit_id' => $data['organizational_unit_id'] ?? null,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_registrar' => (bool) ($data['is_registrar'] ?? false),
                'is_active' => true,
            ]);

            if (! empty($data['role_id'])) {
                $member->roles()->attach($data['role_id']);
            }

            return $member;
        });

        return back()->with('status', "{$member->name} can now sign in with {$member->email}.");
    }

    /** Change a team member's role. */
    public function updateTeamMemberRole(Request $request, User $member): RedirectResponse
    {
        $this->authorizeSettings($request);
        $this->assertSameChurch($request, $member);
        $this->assertOwnerMayChangeAssignment($request, $member);

        $data = $request->validate([
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('church_id', $request->user()->church_id)],
        ]);

        $member->roles()->sync([$data['role_id']]);

        return back()->with('status', "{$member->name}'s role updated.");
    }

    /** §14/§37: change which parish a team member belongs to. */
    public function updateTeamMemberParish(Request $request, User $member): RedirectResponse
    {
        $this->authorizeSettings($request);
        $this->assertSameChurch($request, $member);
        $this->assertOwnerMayChangeAssignment($request, $member);

        $data = $request->validate([
            'organizational_unit_id' => ['nullable', 'integer', Rule::exists('organizational_units', 'id')->where('church_id', $request->user()->church_id)],
        ]);

        $member->update(['organizational_unit_id' => $data['organizational_unit_id'] ?? null]);

        return back()->with('status', $member->organizational_unit_id
            ? "{$member->name} now belongs to ".($member->organizationalUnit?->name ?? 'their parish').' and will see only that parish\'s information.'
            : "{$member->name} is now church-wide (not scoped to a parish).");
    }

    /**
     * §14/§37: set exactly which capabilities a parish pastor has.
     *
     * WHAT THIS IS AND IS NOT
     * -----------------------
     * Capabilities in this app hang off ROLES (`permission_role`), and a user's
     * access is the union of the permissions of the roles they hold —
     * `User::hasPermission()` joins through `role_user`. So "tick the boxes for
     * this one pastor" is implemented as a PRIVATE ROLE owned by that one user:
     * a church-scoped role named after them, holding exactly the ticked
     * permissions, and the only role they hold. That reuses the existing model
     * rather than inventing a second, parallel permission source, which would
     * mean every policy in the app could disagree with what this screen says.
     *
     * It is deliberately NOT a free-for-all:
     *   - only the church owner (registrar) may call it — a pastor must never be
     *     able to widen their own access;
     *   - the permission list is validated against the real `permissions` table,
     *     so an unknown name is rejected rather than silently stored;
     *   - the owner's own account is never rewriteable from here, so the church
     *     cannot lock itself out of its own settings screen;
     *   - an empty set is allowed (a pastor with no capabilities) because that is
     *     a legitimate state, and it is spelled out in the confirmation.
     */
    public function updateTeamMemberPermissions(Request $request, User $member): RedirectResponse
    {
        $this->authorizeSettings($request);
        $this->assertSameChurch($request, $member);

        // Only the owner edits capabilities. A parish pastor holding
        // settings.manage reaches this screen, but must not be able to grant
        // themselves finance, approvals or anything else.
        abort_unless(
            $request->user()->isRegistrar(),
            403,
            'Only the church owner can set what a parish pastor is allowed to do.',
        );

        abort_if(
            $member->id === $request->user()->id,
            409,
            'You cannot change your own permissions here.',
        );

        abort_if(
            $member->isRegistrar(),
            409,
            'That account is the church owner and always has full access.',
        );

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        // De-duplicate: a checkbox list can legitimately repeat a name, and a
        // duplicate would hit the compound primary key on permission_role.
        $names = array_values(array_unique($data['permissions'] ?? []));

        DB::transaction(function () use ($member, $names) {
            $role = $this->privateRoleFor($member);

            $role->permissions()->sync(
                Permission::whereIn('name', $names)->pluck('id')->all()
            );

            // The private role is the ONLY one this user holds, so what the
            // checkboxes show is exactly what the app enforces. Any previous
            // role (including one the form never showed) is dropped here —
            // otherwise an unticked capability could survive through it.
            $member->roles()->sync([$role->id]);
        });

        return back()->with('status', $names === []
            ? "{$member->name} now has no permissions. They can sign in but will not see anything."
            : "Permissions updated for {$member->name} (".count($names).')');
    }

    /**
     * The per-user role that holds a pastor's ticked permissions.
     *
     * Named deterministically so it is recognisable in the Roles tab and cannot
     * be confused with a shared role somebody else also holds:
     *   "Pastor · <name> · #<id>"
     * Keeping it church-scoped means it is invisible to every other tenant, and
     * deleting the user cascades the role link away rather than leaving a
     * privileged role behind for the next account to inherit.
     */
    private function privateRoleFor(User $member): Role
    {
        $name = self::PRIVATE_ROLE_PREFIX.$member->name.' · #'.$member->id;

        return Role::withoutGlobalScopes()->firstOrCreate(
            ['church_id' => $member->church_id, 'name' => $name],
            ['is_system_default' => false],
        );
    }

    /**
     * Parish and role are the OWNER's decisions, not a pastor's.
     *
     * A parish pastor keeps settings.manage so they can maintain their own
     * parish's profile and see what they are allowed to do. That permission
     * must not extend to re-assigning people, because either field is a way to
     * escape the branch: changing your parish to "church-wide" removes the
     * TenantScope filter over every other branch's people and money, and
     * changing your role is a straight privilege escalation. So these two
     * actions are owner-only even though the screen itself is shared.
     *
     * (The new permission checkboxes are owner-only for the same reason — see
     * updateTeamMemberPermissions().)
     */
    private function assertOwnerMayChangeAssignment(Request $request, User $member): void
    {
        abort_unless(
            $request->user()->isRegistrar(),
            403,
            'Only the church owner can change a team member\'s parish or role.',
        );

        abort_if(
            $member->id === $request->user()->id,
            409,
            'You cannot change your own parish or role.',
        );
    }

    /**
     * §14: delete a team member (soft delete).
     *
     * Only the registrar / super-admin may do this, and never themselves. The
     * user is soft-deleted so any records they created (a transaction's
     * recorded_by, a case's assigned_to) stay attributed to them; the email
     * is released for reuse because the unique index ignores soft-deleted rows.
     */
    public function destroyTeamMember(Request $request, User $member): RedirectResponse
    {
        $this->authorizeSettings($request);
        $this->assertSameChurch($request, $member);

        abort_unless($request->user()->isRegistrar(), 403, 'Only the registrar can delete a user.');
        abort_if($member->id === $request->user()->id, 409, 'You cannot delete your own account.');
        abort_if($member->isRegistrar(), 409, 'The registrar account cannot be deleted.');

        $name = $member->name;
        $member->delete();

        return back()->with('status', "{$name}'s account has been deleted.");
    }

    /** Reset a team member's password (a re-issued credential). */
    public function resetTeamMemberPassword(Request $request, User $member): RedirectResponse
    {
        $this->authorizeSettings($request);
        $this->assertSameChurch($request, $member);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $member->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', "Password reset for {$member->name}.");
    }

    /**
     * Deactivate or reactivate a team member.
     *
     * Deactivation, not deletion: a church's records reference the people who
     * created them (a transaction's recorded_by, a case's assigned_to), and
     * deleting the user would either orphan or cascade those. Turning off
     * is_active blocks sign-in while keeping every historical reference intact.
     */
    public function toggleTeamMember(Request $request, User $member): RedirectResponse
    {
        $this->authorizeSettings($request);
        $this->assertSameChurch($request, $member);

        abort_if($member->id === $request->user()->id, 409, 'You cannot deactivate your own account.');

        $member->update(['is_active' => ! $member->is_active]);

        return back()->with('status', $member->name.($member->is_active ? ' reactivated.' : ' deactivated.'));
    }

    private function authorizeSettings(Request $request): void
    {
        abort_unless(
            $request->user()?->hasPermission('settings.manage'),
            403,
            'You do not have permission to manage church settings.',
        );
    }

    /** A member can only be touched by an administrator of the same church. */
    private function assertSameChurch(Request $request, User $member): void
    {
        abort_unless(
            $member->church_id !== null && $member->church_id === $request->user()->church_id,
            403,
            'That account does not belong to your church.',
        );

        // Same rule as the list: a parish-scoped administrator manages only
        // their own parish's accounts. Without this the form hides them but a
        // crafted request with another parish's id would still be accepted.
        $actor = $request->user();
        if ($actor->isParishScoped() && $member->id !== $actor->id) {
            abort_unless(
                $member->organizational_unit_id === $actor->organizational_unit_id,
                403,
                'That account belongs to another parish.',
            );
        }
    }
}

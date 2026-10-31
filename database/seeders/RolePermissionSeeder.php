<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * The default role for a PARISH PASTOR — the church's second, ordinary
     * kind of account, next to the church owner.
     *
     * The set is deliberately the day-to-day running of one parish and no more:
     *
     *   people      members, families, departments, groups
     *   activities  events, attendance
     *   the running announcements, tasks, and subventions (with the periods and
     *               rule sets a parish needs to submit one)
     *
     * It EXCLUDES everything that belongs to the church rather than the parish:
     * finance recording and approval, budgets, loans, billing, SMS campaigns,
     * church settings, and the church-wide pastoral register. `subvention.approve`
     * stays with the owner too — a parish that could approve its own subvention
     * would be marking its own homework.
     *
     * Stated once, here, so the role a church gets at signup, the role the
     * seeder installs, and the set the Team screen offers are the same set.
     *
     * @var array<int, string>
     */
    public const PARISH_PASTOR_PERMISSIONS = [
        // People
        'members.view', 'members.create', 'members.edit',
        'families.manage', 'departments.manage', 'groups.manage',
        // Activities
        'events.manage', 'attendance.manage',
        // The running of the parish
        'announcements.manage', 'subvention.submit',
        'subvention.manage', // periods and rule sets, so a parish can submit
    ];

    /** The role name as it reads in the Team form and the team table. */
    public const PARISH_PASTOR_ROLE = 'Parish Pastor';

    public function run(): void
    {
        $permissions = [
            ['name' => 'members.view', 'group' => 'members', 'label' => 'View members'],
            ['name' => 'members.create', 'group' => 'members', 'label' => 'Add members'],
            ['name' => 'members.edit', 'group' => 'members', 'label' => 'Edit members'],
            ['name' => 'members.delete', 'group' => 'members', 'label' => 'Remove members'],
            ['name' => 'families.manage', 'group' => 'members', 'label' => 'Manage families'],
            ['name' => 'departments.manage', 'group' => 'members', 'label' => 'Manage departments'],
            ['name' => 'groups.manage', 'group' => 'members', 'label' => 'Manage groups'],
            ['name' => 'events.manage', 'group' => 'activities', 'label' => 'Create and manage events'],
            ['name' => 'attendance.manage', 'group' => 'activities', 'label' => 'Take and manage attendance'],
            ['name' => 'announcements.manage', 'group' => 'communication', 'label' => 'Post announcements'],
            ['name' => 'sms.manage', 'group' => 'communication', 'label' => 'Send bulk SMS campaigns and manage SMS credits'],
            ['name' => 'finance.view', 'group' => 'finance', 'label' => 'View finance'],
            ['name' => 'finance.record', 'group' => 'finance', 'label' => 'Record income and expenses'],
            ['name' => 'finance.approve', 'group' => 'finance', 'label' => 'Approve financial transactions'],
            ['name' => 'budgets.manage', 'group' => 'finance', 'label' => 'Manage budgets'],
            ['name' => 'loans.manage', 'group' => 'finance', 'label' => 'Manage loans'],
            ['name' => 'subvention.submit', 'group' => 'subvention', 'label' => 'Submit subvention'],
            ['name' => 'subvention.approve', 'group' => 'subvention', 'label' => 'Approve subvention'],
            ['name' => 'subvention.manage', 'group' => 'subvention', 'label' => 'Configure subvention rule sets and periods'],
            // Deliberately its own permission, never implied by
            // members.view/settings.manage/any other admin permission —
            // see §21 and PrayerRequestPolicy/PastoralCasePolicy. Only the
            // system-default Church Owner role gets it automatically below,
            // as a sensible starting point for a brand-new church; a real
            // deployment should assign it sparingly to pastors only.
            ['name' => 'pastoral.manage', 'group' => 'pastoral', 'label' => 'View and manage all pastoral care records'],
            // §14/§37: subscription, invoices and buying SMS credit with real
            // money. Deliberately NOT implied by settings.manage or any other
            // permission — spending the church's money is its own authority,
            // the same reasoning that keeps finance.approve off every other
            // admin permission.
            ['name' => 'billing.manage', 'group' => 'billing', 'label' => 'Manage subscription, invoices and SMS credit purchases'],
            ['name' => 'settings.manage', 'group' => 'settings', 'label' => 'Manage church settings'],
        ];

        foreach ($permissions as $permission) {
            // updateOrCreate, not firstOrCreate: the group and label are part of
            // what this seeder promises, and firstOrCreate would leave a row
            // that already existed with WRONG values forever. That is not
            // hypothetical — the catalogue once carried placeholder
            // `group = 'x', label = 'x'` rows, which made the Team screen's
            // permission editor render every capability under one meaningless
            // heading, so the owner could not tell what they were granting.
            // Re-running the seeder now repairs the catalogue instead of
            // silently accepting it.
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                ['group' => $permission['group'], 'label' => $permission['label']],
            );
        }

        // System-default role, church_id = null, visible to every tenant
        // (see Role model's global scope) and cloned into a real
        // church-scoped role when a church registers.
        $owner = Role::firstOrCreate(
            ['church_id' => null, 'name' => 'Church Owner'],
            ['is_system_default' => true],
        );
        $owner->permissions()->sync(Permission::pluck('id'));

        // The parish pastor's default role, same shape: system default,
        // cloned into each church's own copy when the church registers or
        // when the owner first opens the Team screen (see ensureParishPastorRole).
        // sync() rather than attach(): re-running the seeder must REPAIR the
        // set, not pile a second copy of it on top.
        $parishPastor = Role::firstOrCreate(
            ['church_id' => null, 'name' => self::PARISH_PASTOR_ROLE],
            ['is_system_default' => true],
        );
        $parishPastor->permissions()->sync(
            Permission::whereIn('name', self::PARISH_PASTOR_PERMISSIONS)->pluck('id')
        );
    }

    /**
     * This church's own "Parish Pastor" role, created from the default set if
     * it does not exist yet.
     *
     * Needed for churches that registered BEFORE the default existed: their
     * Team form had no parish role to offer, and they must not be left with a
     * role list that can only describe the owner. Idempotent — an existing role
     * is returned untouched, so an owner who has since edited their copy keeps
     * their edit.
     */
    public static function ensureParishPastorRole(int $churchId): Role
    {
        $role = Role::withoutGlobalScopes()
            ->where('church_id', $churchId)
            ->where('name', self::PARISH_PASTOR_ROLE)
            ->first();

        if ($role) {
            return $role;
        }

        $role = Role::create([
            'church_id' => $churchId,
            'name' => self::PARISH_PASTOR_ROLE,
        ]);

        $role->permissions()->sync(
            Permission::whereIn('name', self::PARISH_PASTOR_PERMISSIONS)->pluck('id')
        );

        return $role;
    }
}

<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
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
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }

        // System-default role, church_id = null, visible to every tenant
        // (see Role model's global scope) and cloned into a real
        // church-scoped role when a church registers.
        $owner = Role::firstOrCreate(
            ['church_id' => null, 'name' => 'Church Owner'],
            ['is_system_default' => true],
        );
        $owner->permissions()->sync(Permission::pluck('id'));
    }
}

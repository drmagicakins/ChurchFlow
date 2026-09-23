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
            ['name' => 'finance.view', 'group' => 'finance', 'label' => 'View finance'],
            ['name' => 'finance.approve', 'group' => 'finance', 'label' => 'Approve financial transactions'],
            ['name' => 'subvention.submit', 'group' => 'subvention', 'label' => 'Submit subvention'],
            ['name' => 'subvention.approve', 'group' => 'subvention', 'label' => 'Approve subvention'],
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

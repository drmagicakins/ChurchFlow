<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §14/§37: the church owner ticks a parish pastor's permissions, and a pastor
 * can neither re-scope themselves nor change their own role.
 *
 * Two properties are being protected, and they are different:
 *
 *   1. The owner decides what each pastor MAY DO. Each pastor gets their own
 *      role, so ticking a box for one pastor must not affect another, and an
 *      untick must actually remove the capability (not merely stop showing it).
 *
 *   2. The owner decides WHERE each pastor works and AS WHAT. Parish and role
 *      are escape hatches if a pastor controls them: clearing the parish
 *      removes the TenantScope filter over every other branch, and changing
 *      the role is a straight privilege escalation.
 */
class ParishPastorPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private OrganizationalUnit $north;

    private OrganizationalUnit $south;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::factory()->create();
        app()->instance('tenant.church_id', $this->church->id);

        $type = UnitType::create(['church_id' => $this->church->id, 'name' => 'Branch', 'level' => 1]);

        $this->north = OrganizationalUnit::create([
            'church_id' => $this->church->id, 'unit_type_id' => $type->id, 'name' => 'North Parish',
        ]);
        $this->south = OrganizationalUnit::create([
            'church_id' => $this->church->id, 'unit_type_id' => $type->id, 'name' => 'South Parish',
        ]);

        foreach (['settings.manage', 'members.view', 'members.create', 'finance.view',
            'finance.record', 'budgets.manage', 'subvention.submit'] as $name) {
            Permission::firstOrCreate(['name' => $name], ['group' => 'test', 'label' => ucfirst($name)]);
        }
    }

    private function owner(): User
    {
        $user = User::factory()->create([
            'church_id' => $this->church->id,
            'is_registrar' => true,
        ]);

        $role = Role::create(['church_id' => $this->church->id, 'name' => 'Church Owner']);
        $role->permissions()->sync(Permission::pluck('id'));
        $user->roles()->attach($role);
        $user->load('roles.permissions');

        return $user;
    }

    private function pastor(array $permissions = ['settings.manage', 'members.view']): User
    {
        $user = User::factory()->create([
            'church_id' => $this->church->id,
            'organizational_unit_id' => $this->north->id,
            'is_registrar' => false,
        ]);

        $role = Role::create(['church_id' => $this->church->id, 'name' => 'Parish Pastor '.uniqid()]);
        $role->permissions()->attach(Permission::whereIn('name', $permissions)->pluck('id'));
        $user->roles()->attach($role);
        $user->load('roles.permissions');

        return $user;
    }

    // ------------------------------------------- the owner sets permissions

    public function test_the_owner_can_grant_a_single_permission_by_checking_it(): void
    {
        $owner = $this->owner();
        $pastor = $this->pastor(['settings.manage']);

        $this->actingAs($owner)
            ->post(route('settings.team.permissions', $pastor), [
                'permissions' => ['members.view', 'members.create'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $pastor->fresh();
        $this->assertTrue($fresh->hasPermission('members.view'));
        $this->assertTrue($fresh->hasPermission('members.create'));
        $this->assertFalse($fresh->hasPermission('settings.manage'), 'an unticked box must remove the capability');
    }

    public function test_the_ticked_boxes_match_what_the_app_enforces(): void
    {
        $owner = $this->owner();
        $pastor = $this->pastor(['members.view']);

        $this->actingAs($owner)->post(route('settings.team.permissions', $pastor), [
            'permissions' => ['finance.view'],
        ])->assertRedirect();

        // The screen is built from roles->permissions, the same source
        // hasPermission() reads. If these ever diverge, the checkboxes lie.
        $pastor->refresh()->load('roles.permissions');
        $this->assertSame(['finance.view'], $pastor->roles->flatMap->permissions->pluck('name')->all());
        $this->assertTrue($pastor->hasPermission('finance.view'));
        $this->assertFalse($pastor->hasPermission('members.view'));

        $this->actingAs($owner)
            ->get(route('settings.index', ['tab' => 'team']))
            ->assertOk()
            ->assertSee('Parish pastor permissions');
    }

    public function test_a_pastors_permissions_are_theirs_alone(): void
    {
        $owner = $this->owner();
        $first = $this->pastor(['members.view']);
        $second = $this->pastor(['members.view']);

        $this->actingAs($owner)
            ->post(route('settings.team.permissions', $first), ['permissions' => ['budgets.manage']])
            ->assertRedirect();

        $this->assertTrue($first->fresh()->hasPermission('budgets.manage'));
        $this->assertFalse($second->fresh()->hasPermission('budgets.manage'));
        $this->assertTrue($second->fresh()->hasPermission('members.view'));
    }

    public function test_all_boxes_can_be_unchecked(): void
    {
        $owner = $this->owner();
        $pastor = $this->pastor(['members.view', 'finance.view']);

        $this->actingAs($owner)
            ->post(route('settings.team.permissions', $pastor), [])
            ->assertRedirect();

        $this->assertFalse($pastor->fresh()->hasPermission('members.view'));
    }

    public function test_an_unknown_permission_name_is_rejected(): void
    {
        $owner = $this->owner();
        $pastor = $this->pastor();

        $this->actingAs($owner)
            ->post(route('settings.team.permissions', $pastor), [
                'permissions' => ['members.view', 'not.a.real.permission'],
            ])
            ->assertSessionHasErrors('permissions.1');

        $this->assertFalse($pastor->fresh()->hasPermission('not.a.real.permission'));
    }

    public function test_the_owner_cannot_lock_themselves_out(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post(route('settings.team.permissions', $owner), ['permissions' => []])
            ->assertStatus(409);

        $this->assertTrue($owner->fresh()->hasPermission('settings.manage'));
    }

    // --------------------------------- pasture may not change parish or role

    public function test_a_pastor_cannot_change_their_own_parish(): void
    {
        $pastor = $this->pastor();

        $this->actingAs($pastor)
            ->post(route('settings.team.parish', $pastor), ['organizational_unit_id' => $this->south->id])
            ->assertForbidden();

        $this->assertSame($this->north->id, $pastor->fresh()->organizational_unit_id);
    }

    public function test_a_pastor_cannot_make_themselves_church_wide(): void
    {
        $pastor = $this->pastor();

        $this->actingAs($pastor)
            ->post(route('settings.team.parish', $pastor), ['organizational_unit_id' => ''])
            ->assertForbidden();

        $this->assertSame($this->north->id, $pastor->fresh()->organizational_unit_id);
    }

    public function test_a_pastor_cannot_change_their_own_role(): void
    {
        $pastor = $this->pastor();
        $ownerRole = $this->owner()->roles->first();

        $this->actingAs($pastor)
            ->post(route('settings.team.role', $pastor), ['role_id' => $ownerRole->id])
            ->assertForbidden();

        $pastor->refresh()->load('roles');
        $this->assertNotSame($ownerRole->id, $pastor->roles->first()?->id);
    }

    public function test_a_pastor_cannot_change_a_colleagues_role_or_parish(): void
    {
        $pastor = $this->pastor();
        $colleague = $this->pastor(['members.view']);
        $ownerRole = $this->owner()->roles->first();

        $this->actingAs($pastor)
            ->post(route('settings.team.role', $colleague), ['role_id' => $ownerRole->id])
            ->assertForbidden();

        $this->actingAs($pastor)
            ->post(route('settings.team.parish', $colleague), ['organizational_unit_id' => $this->south->id])
            ->assertForbidden();

        $this->assertSame($this->north->id, $colleague->fresh()->organizational_unit_id);
    }

    public function test_a_pastor_cannot_set_anyones_permissions_including_their_own(): void
    {
        $pastor = $this->pastor();
        $colleague = $this->pastor(['members.view']);

        $this->actingAs($pastor)
            ->post(route('settings.team.permissions', $colleague), ['permissions' => ['finance.record']])
            ->assertForbidden();

        $this->actingAs($pastor)
            ->post(route('settings.team.permissions', $pastor), ['permissions' => ['finance.record']])
            ->assertForbidden();

        $this->assertFalse($pastor->fresh()->hasPermission('finance.record'));
    }

    // ------------------------------------------------------------ the screen

    public function test_a_pastor_sees_no_parish_role_or_permission_editors(): void
    {
        $pastor = $this->pastor();
        $colleague = $this->pastor(['members.view']);

        $response = $this->actingAs($pastor)->get(route('settings.index', ['tab' => 'team']));

        $response->assertOk();
        $response->assertDontSee(route('settings.team.parish', $colleague), false);
        $response->assertDontSee(route('settings.team.role', $colleague), false);
        $response->assertDontSee(route('settings.team.permissions', $colleague), false);
        $response->assertDontSee('Parish pastor permissions');
    }

    public function test_the_owner_sees_the_checkboxes_for_each_pastor(): void
    {
        $owner = $this->owner();
        $pastor = $this->pastor(['members.view']);

        $response = $this->actingAs($owner)->get(route('settings.index', ['tab' => 'team']));

        $response->assertOk();
        $response->assertSee('Parish pastor permissions');
        $response->assertSee(route('settings.team.permissions', $pastor), false);
        $response->assertSee('name="permissions[]"', false);
        $response->assertSee('value="members.create"', false);
    }
}

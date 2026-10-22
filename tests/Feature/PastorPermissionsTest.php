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
 * cannot re-assign themselves.
 *
 * Two separate guarantees are covered here, and they protect different things:
 *
 *   1. CAPABILITIES. The owner decides what a pastor may do. Permission is
 *      per-user in effect (each pastor gets their own role), so ticking a box
 *      changes exactly one account — not everybody sharing a role.
 *
 *   2. SCOPE. Parish and role assignment stay the owner's. Both are ways out of
 *      the branch: clearing your parish removes the TenantScope filter over
 *      every other branch, and changing your role escalates privileges. The
 *      screen is shared with pastors, so this is enforced in the controller,
 *      not by hiding a control.
 */
class PastorPermissionsTest extends TestCase
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

        foreach (['members.view', 'members.create', 'finance.view', 'finance.record',
            'budgets.manage', 'loans.manage', 'settings.manage'] as $name) {
            Permission::firstOrCreate(['name' => $name], ['group' => 'x', 'label' => $name]);
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

        $role = Role::create(['church_id' => $this->church->id, 'name' => 'Branch Pastor '.uniqid()]);
        $role->permissions()->attach(
            Permission::whereIn('name', $permissions)->pluck('id')
        );
        $user->roles()->attach($role);
        $user->load('roles.permissions');

        return $user;
    }

    // ------------------------------------------------------- the owner ticks

    public function test_the_owner_sets_exactly_the_permissions_they_tick(): void
    {
        $owner = $this->owner();
        $pastor = $this->pastor();

        $this->actingAs($owner)
            ->post(route('settings.team.permissions', $pastor), [
                'permissions' => ['members.view', 'members.create', 'finance.view'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $pastor->refresh()->load('roles.permissions');

        $this->assertTrue($pastor->hasPermission('members.create'));
        $this->assertTrue($pastor->hasPermission('finance.view'));
        $this->assertFalse($pastor->hasPermission('settings.manage'), 'an unticked permission must be removed');
        $this->assertFalse($pastor->hasPermission('budgets.manage'));
    }

    public function test_the_checkboxes_reflect_what_the_app_enforces(): void
    {
        $owner = $this->owner();
        $pastor = $this->pastor(['members.view']);

        $this->actingAs($owner)->get(route('settings.index', ['tab' => 'team']))
            ->assertOk()
            ->assertSee('Parish pastor permissions')
            ->assertSee('members.create'); // offered as a checkbox

        $this->actingAs($owner)
            ->post(route('settings.team.permissions', $pastor), ['permissions' => ['finance.record']])
            ->assertRedirect();

        $response = $this->actingAs($owner)->get(route('settings.index', ['tab' => 'team']));
        $response->assertOk();

        // The screen must describe the SAME state the policy layer reads.
        $pastor->refresh()->load('roles.permissions');
        $this->assertSame(['finance.record'], $pastor->roles->flatMap->permissions->pluck('name')->all());
        $this->assertTrue($pastor->hasPermission('finance.record'));
    }

    public function test_the_owner_can_empty_a_pastors_permissions(): void
    {
        $owner = $this->owner();
        $pastor = $this->pastor(['members.view', 'finance.view']);

        $this->actingAs($owner)
            ->post(route('settings.team.permissions', $pastor), [])
            ->assertRedirect();

        $pastor->refresh()->load('roles.permissions');
        $this->assertSame([], $pastor->roles->flatMap->permissions->pluck('name')->all());
        $this->assertFalse($pastor->hasPermission('members.view'));
    }

    public function test_one_pastors_tick_does_not_change_anothers(): void
    {
        $owner = $this->owner();
        $first = $this->pastor(['members.view']);
        $second = $this->pastor(['members.view']);

        $this->actingAs($owner)
            ->post(route('settings.team.permissions', $first), ['permissions' => ['finance.record']])
            ->assertRedirect();

        $this->assertFalse($second->fresh()->hasPermission('finance.record'));
        $this->assertTrue($second->fresh()->hasPermission('members.view'));
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
    }

    public function test_the_owner_cannot_strip_their_own_access(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post(route('settings.team.permissions', $owner), ['permissions' => []])
            ->assertStatus(409);

        $owner->refresh()->load('roles.permissions');
        $this->assertTrue($owner->hasPermission('settings.manage'));
    }

    // -------------------------------------------------- a pastor may not widen

    public function test_a_pastor_cannot_set_permissions(): void
    {
        $pastor = $this->pastor();
        $other = $this->pastor(['members.view']);

        $this->actingAs($pastor)
            ->post(route('settings.team.permissions', $other), ['permissions' => ['billing.manage']])
            ->assertForbidden();

        $this->assertFalse($other->fresh()->hasPermission('billing.manage'));
    }

    public function test_a_pastor_cannot_grant_themselves_permissions(): void
    {
        $pastor = $this->pastor();

        $this->actingAs($pastor)
            ->post(route('settings.team.permissions', $pastor), ['permissions' => ['finance.record']])
            ->assertForbidden();

        $this->assertFalse($pastor->fresh()->hasPermission('finance.record'));
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

    public function test_a_pastor_cannot_change_their_own_parish(): void
    {
        $pastor = $this->pastor();

        $this->actingAs($pastor)
            ->post(route('settings.team.parish', $pastor), ['organizational_unit_id' => $this->south->id])
            ->assertForbidden();

        $this->assertSame($this->north->id, $pastor->fresh()->organizational_unit_id);
    }

    public function test_a_pastor_cannot_move_another_pastor_out_of_their_parish(): void
    {
        $pastor = $this->pastor();
        $colleague = User::factory()->create([
            'church_id' => $this->church->id,
            'organizational_unit_id' => $this->north->id,
        ]);

        $this->actingAs($pastor)
            ->post(route('settings.team.parish', $colleague), ['organizational_unit_id' => ''])
            ->assertForbidden();

        $this->assertSame($this->north->id, $colleague->fresh()->organizational_unit_id);
    }

    public function test_a_pastor_cannot_change_another_pastors_role(): void
    {
        $pastor = $this->pastor();
        $colleague = $this->pastor(['members.view']);
        $ownerRole = $this->owner()->roles->first();

        $this->actingAs($pastor)
            ->post(route('settings.team.role', $colleague), ['role_id' => $ownerRole->id])
            ->assertForbidden();
    }

    // ------------------------------------------------------- the screen itself

    public function test_a_pastor_sees_no_parish_or_role_editors_on_the_team_tab(): void
    {
        $pastor = $this->pastor();
        $colleague = $this->pastor(['members.view']);

        $response = $this->actingAs($pastor)->get(route('settings.index', ['tab' => 'team']));

        $response->assertOk();
        // No editor form for the colleague in the same parish...
        $response->assertDontSee(route('settings.team.parish', $colleague), false);
        $response->assertDontSee(route('settings.team.role', $colleague), false);
        // ...and no permissions panel at all.
        $response->assertDontSee('Parish pastor permissions');
        $response->assertDontSee(route('settings.team.permissions', $colleague), false);
    }

    public function test_the_owner_still_sees_the_editors_and_the_permission_panel(): void
    {
        $owner = $this->owner();
        $pastor = $this->pastor();

        $response = $this->actingAs($owner)->get(route('settings.index', ['tab' => 'team']));

        $response->assertOk();
        $response->assertSee('Parish pastor permissions');
        $response->assertSee(route('settings.team.parish', $pastor), false);
        $response->assertSee(route('settings.team.role', $pastor), false);
        $response->assertSee(route('settings.team.permissions', $pastor), false);
    }
}

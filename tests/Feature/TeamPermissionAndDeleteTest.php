<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Team & credentials: the owner must be able to SET a pastor's permissions,
 * and must be able to DELETE a pastor.
 *
 * The reported symptom was that the "Parish pastor permissions" panel showed
 * only the Church Owner's set. Two separate causes, both covered here:
 *
 *  1. the permission CATALOGUE had placeholder rows (`group = 'x'`,
 *     `label = 'x'`), so the editor rendered every permission under one
 *     meaningless heading and the useful names looked missing;
 *  2. a pastor created by the owner held a shared role, so the panel's
 *     "is this person editable" answer depended on the role rather than on
 *     the person.
 */
class TeamPermissionAndDeleteTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->church = Church::factory()->create(['status' => 'active']);
        app()->instance('tenant.church_id', $this->church->id);

        $type = UnitType::create(['church_id' => $this->church->id, 'name' => 'Parish', 'level' => 1]);
        $parish = OrganizationalUnit::create([
            'church_id' => $this->church->id, 'unit_type_id' => $type->id, 'name' => 'Wisdom Parish',
        ]);

        $this->owner = User::factory()->create([
            'church_id' => $this->church->id, 'is_registrar' => true,
        ]);
        $ownerRole = Role::firstOrCreate(['church_id' => null, 'name' => 'Church Owner']);
        $ownerRole->permissions()->sync(Permission::pluck('id'));
        $this->owner->roles()->attach($ownerRole);

        $this->parish = $parish;
    }

    private OrganizationalUnit $parish;

    private function pastor(): User
    {
        return User::factory()->create([
            'church_id' => $this->church->id,
            'organizational_unit_id' => $this->parish->id,
        ]);
    }

    public function test_the_permission_catalogue_is_usable_for_the_editor(): void
    {
        // Every permission must carry a real group and label. The seeder is the
        // source of truth, so a placeholder value anywhere means the editor
        // would render a meaningless heading and an unreadable checkbox.
        $this->assertGreaterThan(0, Permission::count());

        foreach (Permission::all() as $permission) {
            $this->assertNotSame('x', $permission->group, "{$permission->name} has a placeholder group.");
            $this->assertNotSame('x', $permission->label, "{$permission->name} has a placeholder label.");
            $this->assertNotSame($permission->name, $permission->label, "{$permission->name} has no human label.");
        }

        $this->assertGreaterThan(
            3,
            Permission::distinct()->count('group'),
            'Permissions must be spread over meaningful groups, not lumped into one.'
        );
    }

    public function test_re_running_the_seeder_repairs_placeholder_permission_rows(): void
    {
        // The exact corruption found in the dev database: group and label both
        // left as the placeholder 'x'. firstOrCreate would never have fixed it;
        // the seeder must now correct an existing row rather than skip it.
        Permission::where('name', 'loans.manage')->update(['group' => 'x', 'label' => 'x']);

        $this->seed(RolePermissionSeeder::class);

        $repaired = Permission::where('name', 'loans.manage')->firstOrFail();

        $this->assertSame('finance', $repaired->group);
        $this->assertSame('Manage loans', $repaired->label);
        $this->assertSame(0, Permission::where('group', 'x')->orWhere('label', 'x')->count());
    }

    public function test_the_owner_sees_the_permission_editor_for_a_pastor(): void
    {
        $pastor = $this->pastor();

        $response = $this->actingAs($this->owner)
            ->get(route('settings.index', ['tab' => 'team']))
            ->assertOk();

        $response->assertSee('Parish pastor permissions');
        $response->assertSee('permissions-'.$pastor->id, false);
        $response->assertSee('name="permissions[]"', false);

        // The real capabilities must be offered, grouped sensibly.
        $response->assertSee('members.view');
        $response->assertSee('events.manage');
        $response->assertSee('attendance.manage');
        $response->assertSee('finance.record');
        $response->assertSee('loans.manage');
    }

    public function test_the_owner_can_set_a_pastors_permissions(): void
    {
        $pastor = $this->pastor();

        $this->actingAs($this->owner)
            ->post(route('settings.team.permissions', $pastor), [
                'permissions' => ['members.view', 'members.create', 'events.manage'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $pastor = $pastor->fresh();
        app()->instance('tenant.church_id', $this->church->id);

        $this->assertTrue($pastor->hasPermission('members.view'));
        $this->assertTrue($pastor->hasPermission('members.create'));
        $this->assertTrue($pastor->hasPermission('events.manage'));

        // Unticked capabilities must NOT survive.
        $this->assertFalse($pastor->hasPermission('finance.record'));
        $this->assertFalse($pastor->hasPermission('billing.manage'));

        // And the person really is limited by it.
        $this->actingAs($pastor)->get('/members')->assertOk();
        $this->actingAs($pastor)->get('/finance/accounts')->assertForbidden();
    }

    public function test_setting_permissions_replaces_a_previous_shared_role_rather_than_adding_to_it(): void
    {
        $pastor = $this->pastor();

        // Start them on a shared church role that grants finance.
        $shared = Role::create(['church_id' => $this->church->id, 'name' => 'Finance Team']);
        $shared->permissions()->sync(Permission::whereIn('name', ['finance.view', 'finance.record'])->pluck('id'));
        $pastor->roles()->sync([$shared->id]);

        $this->actingAs($this->owner)
            ->post(route('settings.team.permissions', $pastor), [
                'permissions' => ['members.view'],
            ])
            ->assertRedirect();

        $pastor = $pastor->fresh();
        app()->instance('tenant.church_id', $this->church->id);

        $this->assertTrue($pastor->hasPermission('members.view'));
        $this->assertFalse($pastor->hasPermission('finance.record'), 'An unticked capability must not survive through a shared role.');
    }

    public function test_the_owner_can_clear_all_of_a_pastors_permissions(): void
    {
        $pastor = $this->pastor();

        $this->actingAs($this->owner)
            ->post(route('settings.team.permissions', $pastor), ['permissions' => ['members.view']]);

        $this->actingAs($this->owner)
            ->post(route('settings.team.permissions', $pastor), [])
            ->assertRedirect();

        $pastor = $pastor->fresh();
        app()->instance('tenant.church_id', $this->church->id);

        $this->assertSame([], $pastor->roles->flatMap->permissions->pluck('name')->unique()->values()->all());
    }

    public function test_a_pastor_cannot_set_their_own_or_anyones_permissions(): void
    {
        $pastor = $this->pastor();
        $other = $this->pastor();

        $settingsRole = Role::create(['church_id' => $this->church->id, 'name' => 'Pastor Settings']);
        $settingsRole->permissions()->sync(Permission::where('name', 'settings.manage')->pluck('id'));
        $pastor->roles()->sync([$settingsRole->id]);

        $this->actingAs($pastor->fresh())
            ->post(route('settings.team.permissions', $other), ['permissions' => ['billing.manage']])
            ->assertForbidden();

        $this->actingAs($pastor->fresh())
            ->post(route('settings.team.permissions', $pastor), ['permissions' => ['billing.manage']])
            ->assertForbidden();
    }

    public function test_the_owner_can_delete_a_pastor(): void
    {
        $pastor = $this->pastor();

        $this->actingAs($this->owner)
            ->delete(route('settings.team.destroy', $pastor))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Soft-deleted: gone from sign-in and from the team list, but the row
        // survives so records they created stay attributed to them.
        $this->assertSoftDeleted('users', ['id' => $pastor->id]);
        $this->assertNull(User::find($pastor->id));

        $this->actingAs($this->owner)
            ->get(route('settings.index', ['tab' => 'team']))
            ->assertOk()
            ->assertDontSee($pastor->email);
    }

    public function test_the_owner_cannot_delete_themselves_or_another_owner(): void
    {
        $this->actingAs($this->owner)
            ->delete(route('settings.team.destroy', $this->owner))
            ->assertStatus(409);

        $coOwner = User::factory()->create([
            'church_id' => $this->church->id, 'is_registrar' => true,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('settings.team.destroy', $coOwner))
            ->assertStatus(409);

        $this->assertNotSoftDeleted('users', ['id' => $this->owner->id]);
        $this->assertNotSoftDeleted('users', ['id' => $coOwner->id]);
    }

    public function test_a_pastor_cannot_delete_anyone(): void
    {
        $pastor = $this->pastor();
        $victim = $this->pastor();

        $settingsRole = Role::create(['church_id' => $this->church->id, 'name' => 'Pastor Settings 2']);
        $settingsRole->permissions()->sync(Permission::where('name', 'settings.manage')->pluck('id'));
        $pastor->roles()->sync([$settingsRole->id]);

        $this->actingAs($pastor->fresh())
            ->delete(route('settings.team.destroy', $victim))
            ->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $victim->id]);
    }

    public function test_one_church_cannot_delete_another_churchs_user(): void
    {
        $otherChurch = Church::factory()->create();
        $outsider = User::factory()->create(['church_id' => $otherChurch->id]);

        $this->actingAs($this->owner)
            ->delete(route('settings.team.destroy', $outsider))
            ->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $outsider->id]);
    }
}

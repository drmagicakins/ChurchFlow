<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Group;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Groups belong to a parish, not to the church owner.
 *
 * Before this, a group had no parish at all: every pastor saw every group in
 * the church, and a group created by one parish belonged to nobody in
 * particular. These guards lock down the three things that makes true —
 * listing, creating, and opening by id.
 */
class GroupParishScopeTest extends TestCase
{
    use RefreshDatabase;

    private function userIn(Church $church, ?OrganizationalUnit $parish, bool $registrar): User
    {
        $user = User::factory()->create([
            'church_id' => $church->id,
            'organizational_unit_id' => $parish?->id,
            'is_registrar' => $registrar,
        ]);
        $role = Role::create(['church_id' => $church->id, 'name' => 'R'.uniqid()]);
        $role->permissions()->attach(
            collect(['members.view', 'groups.manage'])->map(
                fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'x', 'label' => $n])->id
            )
        );
        $user->roles()->attach($role);
        $user->load('roles.permissions');

        return $user;
    }

    private function parish(Church $church, string $name, ?OrganizationalUnit $parent = null): OrganizationalUnit
    {
        $type = UnitType::firstOrCreate(
            ['church_id' => $church->id, 'name' => 'Parish'],
            ['level' => 1],
        );

        return OrganizationalUnit::create([
            'church_id' => $church->id,
            'unit_type_id' => $type->id,
            'parent_id' => $parent?->id,
            'name' => $name,
        ]);
    }

    private function group(Church $church, string $name, ?OrganizationalUnit $unit): Group
    {
        return Group::withoutGlobalScopes()->create([
            'church_id' => $church->id,
            'organizational_unit_id' => $unit?->id,
            'name' => $name,
        ]);
    }

    public function test_a_parish_pastor_only_lists_their_own_parishs_groups(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $mine = $this->parish($church, 'Wisdom Parish');
        $other = $this->parish($church, 'Faith Parish');

        $this->group($church, 'Wisdom Youth', $mine);
        $this->group($church, 'Faith Cells', $other);
        $this->group($church, 'Owner Fellowship', null);

        $pastor = $this->userIn($church, $mine, false);

        $this->actingAs($pastor)
            ->get('/groups')
            ->assertOk()
            ->assertSee('Wisdom Youth')
            ->assertDontSee('Faith Cells')
            ->assertDontSee('Owner Fellowship');
    }

    public function test_the_owner_still_lists_every_group(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $main = $this->parish($church, 'Main');
        $parish = $this->parish($church, 'Wisdom Parish', $main);

        $this->group($church, 'Wisdom Youth', $parish);
        $this->group($church, 'Owner Fellowship', null);

        $owner = $this->userIn($church, $main, true);

        $this->actingAs($owner)
            ->get('/groups')
            ->assertOk()
            ->assertSee('Wisdom Youth')
            ->assertSee('Owner Fellowship');
    }

    public function test_a_group_created_by_a_parish_pastor_belongs_to_that_parish(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $mine = $this->parish($church, 'Wisdom Parish');

        $pastor = $this->userIn($church, $mine, false);
        app()->instance('tenant.organizational_unit_id', $mine->id);

        $this->actingAs($pastor)
            ->post('/groups', ['name' => 'Wisdom Cells'])
            ->assertRedirect();

        $group = Group::withoutGlobalScopes()->where('name', 'Wisdom Cells')->firstOrFail();

        $this->assertSame($mine->id, (int) $group->getRawOriginal('organizational_unit_id'));
    }

    public function test_a_parish_pastor_cannot_open_another_parishs_group(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $mine = $this->parish($church, 'Wisdom Parish');
        $other = $this->parish($church, 'Faith Parish');

        app()->instance('tenant.organizational_unit_id', $mine->id);
        $foreign = $this->group($church, 'Faith Cells', $other);
        $pastor = $this->userIn($church, $mine, false);

        $this->actingAs($pastor)
            ->get("/groups/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_the_owner_can_file_a_group_against_a_parish(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $main = $this->parish($church, 'Main');
        $parish = $this->parish($church, 'Wisdom Parish', $main);

        $owner = $this->userIn($church, $main, true);

        $this->actingAs($owner)
            ->post('/groups', ['name' => 'Wisdom Cells', 'organizational_unit_id' => $parish->id])
            ->assertRedirect();

        $group = Group::withoutGlobalScopes()->where('name', 'Wisdom Cells')->firstOrFail();

        $this->assertSame($parish->id, (int) $group->getRawOriginal('organizational_unit_id'));
    }

    public function test_a_group_cannot_be_filed_against_another_churchs_parish(): void
    {
        $church = Church::factory()->create();
        $otherChurch = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $main = $this->parish($church, 'Main');
        $foreignParish = $this->parish($otherChurch, 'Foreign Parish');

        $owner = $this->userIn($church, $main, true);

        $this->actingAs($owner)
            ->post('/groups', ['name' => 'Sneaky', 'organizational_unit_id' => $foreignParish->id])
            ->assertForbidden();

        $this->assertSame(0, Group::withoutGlobalScopes()->where('name', 'Sneaky')->count());
    }

    public function test_an_existing_whole_church_group_keeps_its_meaning(): void
    {
        // The migration is additive: every group that existed before has no
        // parish, and must still be visible to the owner rather than vanish.
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $main = $this->parish($church, 'Main');

        $legacy = $this->group($church, 'Legacy Fellowship', null);
        $this->assertNull($legacy->getRawOriginal('organizational_unit_id'));

        $owner = $this->userIn($church, $main, true);

        $this->actingAs($owner)->get('/groups')->assertOk()->assertSee('Legacy Fellowship');
    }
}

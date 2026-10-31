<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Department;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Department access and duplicate names.
 *
 * Three bugs are locked down here, each of which was reproducible against the
 * real dev database before the fix:
 *
 *  1. `GET /departments/{id}` returned 404 for a parish pastor. TenantScope
 *     narrows a parish-scoped user to `organizational_unit_id = their parish`,
 *     but a whole-church department stores the WHOLE_CHURCH sentinel, so the
 *     bound model was never found. The index worked; nothing else did.
 *  2. The duplicate-name rule never fired: `where('organizational_unit_id',
 *     null)` compiles to `= NULL`, which is never true. "Choir" existed three
 *     times for one church.
 *  3. The edit form had no duplicate rule at all, so a department could be
 *     renamed onto an existing name.
 */
class DepartmentAccessAndUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private function role(Church $church, array $permissions): Role
    {
        $ids = collect($permissions)->map(
            fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'x', 'label' => 'x'])->id
        );
        $role = Role::create(['church_id' => $church->id, 'name' => 'Role '.uniqid()]);
        $role->permissions()->attach($ids);

        return $role;
    }

    private function userIn(Church $church, array $permissions, ?OrganizationalUnit $parish = null, bool $registrar = false): User
    {
        $user = User::factory()->create([
            'church_id' => $church->id,
            'organizational_unit_id' => $parish?->id,
            'is_registrar' => $registrar,
        ]);
        $user->roles()->attach($this->role($church, $permissions));
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

    // ---------------------------------------------------------------- 1. 404

    public function test_a_parish_pastor_can_open_a_whole_church_department(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $wisdom = $this->parish($church, 'Wisdom Parish');

        $choir = Department::create(['church_id' => $church->id, 'name' => 'Choir']);

        $pastor = $this->userIn($church, ['members.view', 'departments.manage'], $wisdom);

        // The index listed it, so the page behind it must open too.
        $this->actingAs($pastor)
            ->get('/departments')
            ->assertOk()
            ->assertSee('Choir');

        $this->actingAs($pastor)
            ->get("/departments/{$choir->id}")
            ->assertOk()
            ->assertSee('Choir');
    }

    public function test_a_parish_pastor_cannot_open_another_parishs_department(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $wisdom = $this->parish($church, 'Wisdom Parish');
        $faith = $this->parish($church, 'Faith Parish');

        $other = Department::create([
            'church_id' => $church->id,
            'organizational_unit_id' => $faith->id,
            'name' => 'Faith Choir',
        ]);

        $pastor = $this->userIn($church, ['members.view', 'departments.manage'], $wisdom);

        // Negative assertion: the isolation still holds server-side.
        $this->actingAs($pastor)
            ->get("/departments/{$other->id}")
            ->assertNotFound();
    }

    public function test_a_parish_pastor_can_still_remove_a_whole_church_department(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $wisdom = $this->parish($church, 'Wisdom Parish');

        $choir = Department::create(['church_id' => $church->id, 'name' => 'Choir']);
        $pastor = $this->userIn($church, ['members.view', 'departments.manage'], $wisdom);

        $this->actingAs($pastor)
            ->delete("/departments/{$choir->id}")
            ->assertRedirect('/departments');

        $this->assertSoftDeleted('departments', ['id' => $choir->id]);
    }

    // -------------------------------------------------------- 2. duplicates

    public function test_the_same_department_name_cannot_be_created_twice(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $owner = $this->userIn($church, ['members.view', 'departments.manage'], null, true);

        // Created through the FORM, not the model: the model insert is now
        // refused by the database index, which would make this test pass
        // without proving the controller's own rule ever ran.
        $this->actingAs($owner)
            ->post('/departments', ['name' => 'Choir'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post('/departments', ['name' => 'Choir'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Department::where('name', 'Choir')->count());
    }

    public function test_a_duplicate_is_caught_regardless_of_case_or_spacing(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $owner = $this->userIn($church, ['members.view', 'departments.manage'], null, true);

        // Every row is created through the controller so the test fails if the
        // controller rule is removed, rather than passing on the DB index.
        $this->actingAs($owner)->post('/departments', ['name' => 'Youth Choir'])->assertRedirect();

        foreach (['youth choir', 'YOUTH CHOIR', 'Youth  Choir   '] as $variant) {
            $this->actingAs($owner)
                ->post('/departments', ['name' => $variant])
                ->assertSessionHasErrors('name');
        }

        $this->assertSame(1, Department::count());
    }

    public function test_the_same_name_is_allowed_in_a_different_parish(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $wisdom = $this->parish($church, 'Wisdom Parish');
        $owner = $this->userIn($church, ['members.view', 'departments.manage'], null, true);

        $this->actingAs($owner)
            ->post('/departments', ['name' => 'Choir'])
            ->assertRedirect();

        // A branch-scoped "Choir" is a genuinely different department.
        $this->actingAs($owner)
            ->post('/departments', ['name' => 'Choir', 'organizational_unit_id' => $wisdom->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Department::count());
    }

    /**
     * The model, not just the controller, must write the sentinel.
     *
     * Laravel only runs a set mutator for keys actually present in
     * `$attributes` (see HasAttributes::setAttribute and HasMutatedAttributes::
     * setMutatedAttributeValue, which mutate and then write the key), so a
     * department built without `organizational_unit_id` never reaches
     * setOrganizationalUnitIdAttribute() — the probe showed it holding NULL in
     * memory — and relied on the `saving` hook for the stored value. Lock that
     * hook down: if it is removed, the row goes back to NULL and the unique
     * index stops seeing whole-church departments.
     */
    public function test_a_department_built_without_a_branch_is_stored_with_the_sentinel(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $dept = Department::create(['church_id' => $church->id, 'name' => 'Choir']);

        // No (int) cast on the stored value: casting would turn a NULL column
        // into 0 and make this assertion pass even with the saving hook gone.
        // 0 as an integer is what the hook must have written.
        $this->assertSame(
            Department::WHOLE_CHURCH,
            DB::table('departments')->where('id', $dept->id)->value('organizational_unit_id'),
            'A department created without a branch must be stored as WHOLE_CHURCH, not NULL.'
        );
    }

    public function test_the_database_itself_refuses_a_duplicate(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        Department::create(['church_id' => $church->id, 'name' => 'Choir']);

        // Bypasses the controller rule entirely — the index must still hold.
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        Department::create(['church_id' => $church->id, 'name' => 'Choir']);
    }

    // -------------------------------------------------- 3. edit-form guard

    public function test_a_department_cannot_be_renamed_onto_an_existing_name(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $owner = $this->userIn($church, ['members.view', 'departments.manage'], null, true);

        $this->actingAs($owner)->post('/departments', ['name' => 'Choir'])->assertRedirect();
        $this->actingAs($owner)->post('/departments', ['name' => 'Ushering'])->assertRedirect();

        $ushering = Department::where('name', 'Ushering')->firstOrFail();

        $this->actingAs($owner)
            ->put("/departments/{$ushering->id}", ['name' => 'choir'])
            ->assertSessionHasErrors('name');

        $this->assertSame('Ushering', $ushering->fresh()->name);
    }

    public function test_a_department_can_be_renamed_and_re_saved_with_its_own_name(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $owner = $this->userIn($church, ['members.view', 'departments.manage'], null, true);

        $dept = Department::create(['church_id' => $church->id, 'name' => 'Choir']);

        // Saving the form unchanged must not trip the "already exists" rule.
        $this->actingAs($owner)
            ->put("/departments/{$dept->id}", ['name' => 'Choir', 'description' => 'Singers'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Singers', $dept->fresh()->description);
    }

    // ----------------------------------------------- whole-church sentinel

    public function test_a_whole_church_department_is_stored_with_the_sentinel_not_null(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $dept = Department::create(['church_id' => $church->id, 'name' => 'Choir', 'organizational_unit_id' => null]);

        // Read raw rather than through the model cast/accessor, and without an
        // (int) cast, so a NULL stored value stays NULL here and fails.
        $this->assertSame(
            Department::WHOLE_CHURCH,
            DB::table('departments')->where('id', $dept->id)->value('organizational_unit_id')
        );
    }
}

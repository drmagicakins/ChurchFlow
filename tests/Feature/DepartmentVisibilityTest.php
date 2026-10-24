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
use Tests\TestCase;

/**
 * §14: which departments a parish pastor sees, and in what order.
 *
 * A browser QA run logged in as a parish pastor opened /departments and got an
 * empty table while the departments screen's own empty state promised
 * "Departments created by your team will appear here." Two separate causes:
 *
 *  1. TenantScope narrows a parish-scoped user to `organizational_unit_id =
 *     their parish`, but a department filed against the WHOLE church stores
 *     NULL there (the create form offers "Whole church" on purpose). Every
 *     church-wide department was therefore invisible to every parish pastor,
 *     including in the SMS / announcement / attendance pickers.
 *  2. Nothing ordered the query, so the list came back in table order. A
 *     department created later could be pushed off the visible page.
 */
class DepartmentVisibilityTest extends TestCase
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
    }

    private function pastor(): User
    {
        $user = User::factory()->create([
            'church_id' => $this->church->id,
            'organizational_unit_id' => $this->north->id,
            'is_registrar' => false,
        ]);

        $role = Role::create(['church_id' => $this->church->id, 'name' => 'Parish Pastor']);
        $role->permissions()->attach(
            collect(['members.view', 'sms.manage'])->map(
                fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'x', 'label' => $n])->id
            )
        );
        $user->roles()->attach($role);
        $user->load('roles.permissions');

        return $user;
    }

    private function owner(): User
    {
        $user = User::factory()->create([
            'church_id' => $this->church->id,
            'organizational_unit_id' => $this->north->id,
            'is_registrar' => true,
        ]);

        // The owner holds every capability via the Church Owner role; the
        // DepartmentPolicy reads members.view and the campaign screen reads
        // sms.manage, so grant both.
        $role = Role::create(['church_id' => $this->church->id, 'name' => 'Church Owner']);
        $role->permissions()->attach(
            collect(['members.view', 'sms.manage'])->map(
                fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'x', 'label' => $n])->id
            )
        );
        $user->roles()->attach($role);
        $user->load('roles.permissions');

        return $user;
    }

    private function department(string $name, ?OrganizationalUnit $unit = null): Department
    {
        return Department::create([
            'church_id' => $this->church->id,
            'organizational_unit_id' => $unit?->id,
            'name' => $name,
        ]);
    }

    /** Bind the parish the way IdentifyTenant does, then read the page. */
    private function asPastorOnTheirParish(User $pastor): static
    {
        app()->instance('tenant.church_id', $this->church->id);
        app()->instance('tenant.organizational_unit_id', $pastor->organizational_unit_id);

        return $this->actingAs($pastor);
    }

    public function test_a_parish_pastor_sees_their_own_parishs_departments(): void
    {
        $this->department('North Ushering', $this->north);

        $this->asPastorOnTheirParish($this->pastor())
            ->get(route('departments.index'))
            ->assertOk()
            ->assertSee('North Ushering');
    }

    public function test_a_parish_pastor_sees_whole_church_departments(): void
    {
        // This is the bug the browser QA found: the form's "Whole church"
        // option writes NULL, and NULL never matched a parish.
        $this->department('Choir', null);

        $this->asPastorOnTheirParish($this->pastor())
            ->get(route('departments.index'))
            ->assertOk()
            ->assertSee('Choir');
    }

    public function test_a_parish_pastor_never_sees_another_parishs_departments(): void
    {
        $this->department('South Media', $this->south);
        $this->department('North Media', $this->north);

        $this->asPastorOnTheirParish($this->pastor())
            ->get(route('departments.index'))
            ->assertOk()
            ->assertSee('North Media')
            ->assertDontSee('South Media');
    }

    public function test_a_parish_pastor_cannot_open_another_parishs_department_by_id(): void
    {
        $foreign = $this->department('South Media', $this->south);

        $this->asPastorOnTheirParish($this->pastor())
            ->get(route('departments.show', $foreign))
            ->assertNotFound();
    }

    public function test_the_church_owner_still_sees_every_department(): void
    {
        $this->department('Choir', null);
        $this->department('South Media', $this->south);

        $this->actingAs($this->owner())
            ->get(route('departments.index'))
            ->assertOk()
            ->assertSee('Choir')
            ->assertSee('South Media');
    }

    public function test_departments_are_listed_alphabetically_however_they_were_created(): void
    {
        // Created out of alphabetical order on purpose: the table used to hand
        // them back in insertion order, so the newest one could sit above the
        // fold and look missing.
        $this->department('Ushering', null);
        $this->department('Media', null);
        $this->department('Choir', null);

        $names = Department::query()->pluck('name')->all();

        $this->assertSame(['Choir', 'Media', 'Ushering'], $names);
    }

    public function test_a_whole_church_department_appears_once_in_the_sms_picker(): void
    {
        // The picker is a dropdown built from the same tenant-scoped query, so
        // it was empty for a parish pastor. It must offer their parish AND the
        // church-wide departments, each exactly once.
        $this->department('Choir', null);
        $this->department('North Ushering', $this->north);
        $this->department('South Media', $this->south);

        $pastor = $this->pastor();
        app()->instance('tenant.church_id', $this->church->id);
        app()->instance('tenant.organizational_unit_id', $pastor->organizational_unit_id);

        $visible = Department::query()->visibleTo($pastor)->pluck('name')->all();

        $this->assertSame(['Choir', 'North Ushering'], $visible);
    }

    public function test_the_sms_audience_picker_offers_a_parish_pastor_only_their_own_parish(): void
    {
        // OrganizationalUnit has no parish column, so TenantScope let the whole
        // church through and the browser QA saw "South Parish" in the picker of
        // a North pastor. The audience would have been forced back to North on
        // save, but the form still named a branch they have no authority over.
        $pastor = $this->pastor();

        $this->asPastorOnTheirParish($pastor)
            ->get(route('sms.campaigns.index'))
            ->assertOk()
            ->assertSee('North Parish')
            ->assertDontSee('South Parish');
    }

    public function test_the_owner_can_still_target_any_parish_by_sms(): void
    {
        $this->actingAs($this->owner())
            ->get(route('sms.campaigns.index'))
            ->assertOk()
            ->assertSee('North Parish')
            ->assertSee('South Parish');
    }
}

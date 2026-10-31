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
            collect(['members.view', 'sms.manage', 'departments.manage'])->map(
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
            collect(['members.view', 'sms.manage', 'departments.manage'])->map(
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

    /**
     * INVERTED 2026-10-26 at the developer's request.
     *
     * This used to assert the opposite ("a parish pastor SEES whole-church
     * departments"), and that was the behaviour the browser showed and the
     * developer rejected: /departments displayed the owner's whole-church
     * departments inside a parish pastor's screen. Only the parish's own
     * departments are listed now.
     *
     * The capability that test was really protecting — a church-wide department
     * staying REACHABLE for a pastor — is still covered:
     * DepartmentAccessAndUniquenessTest::test_a_parish_pastor_can_open_a_whole_church_department
     * covers route binding, and Department::visibleTo() is unchanged and still
     * used by the SMS/attendance pickers.
     */
    public function test_a_parish_pastor_is_not_shown_whole_church_departments(): void
    {
        // A name that appears NOWHERE else on the page — the create form's own
        // placeholder says "e.g. Ushering, Choir, Media", so asserting on
        // "Choir" would fail for a reason that has nothing to do with scoping.
        $this->department('Cathedral Bell Ringers', null);
        $this->department('North Ushering', $this->north);

        // Stored as NULL on purpose: that is the shape the developer's database
        // had (the branch foreign key is still enforced there, see
        // Department::wholeChurchValue()), and `organizational_unit_id = 4`
        // never matches NULL, so the global scope alone cannot hide it — which
        // is exactly what made it appear on the pastor's page. Raw SQL, because
        // the model's saving hook would rewrite it back to the 0 sentinel.
        DB::table('departments')
            ->where('church_id', $this->church->id)
            ->where('name', 'Cathedral Bell Ringers')
            ->update(['organizational_unit_id' => null]);

        $this->asPastorOnTheirParish($this->pastor())
            ->get(route('departments.index'))
            ->assertOk()
            ->assertSee('North Ushering')
            ->assertDontSee('Cathedral Bell Ringers');
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

    public function test_the_owner_still_sees_whole_church_departments(): void
    {
        $this->department('Cathedral Bell Ringers', null);
        DB::table('departments')
            ->where('church_id', $this->church->id)
            ->where('name', 'Cathedral Bell Ringers')
            ->update(['organizational_unit_id' => null]);

        $this->actingAs($this->owner())
            ->get(route('departments.index'))
            ->assertOk()
            ->assertSee('Cathedral Bell Ringers');
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

    // ------------------------------------------- creating, from either side

    /**
     * A branch pastor creates a department from the form.
     *
     * The form hides the Branch select from a branch-scoped user on purpose, so
     * the posted body carries no `organizational_unit_id` at all. The row must
     * still be filed against that pastor's branch, not against the whole church
     * — and it must be insertable: the dev database has a real foreign key on
     * `departments.organizational_unit_id`, which refused the WHOLE_CHURCH
     * sentinel (0) with "FOREIGN KEY constraint failed".
     */
    public function test_a_branch_pastor_can_create_a_department_for_their_branch(): void
    {
        $pastor = $this->pastor();

        $this->asPastorOnTheirParish($pastor)
            ->post(route('departments.store'), [
                'name' => 'Sunday School Teachers',
                'description' => 'Sunday School teachers department',
            ])
            ->assertRedirect();

        $dept = Department::withoutGlobalScopes()->firstOrFail();

        $this->assertSame($this->north->id, (int) $dept->getRawOriginal('organizational_unit_id'));
        $this->assertSame('Sunday School Teachers', $dept->name);
    }

    /**
     * The church owner creates a department and files it against a branch.
     *
     * The owner sees the Branch select, so the branch id is posted explicitly;
     * it must be stored as that branch, and the page must open afterwards.
     */
    public function test_the_owner_can_create_a_department_for_a_branch(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post(route('departments.store'), [
                'name' => 'Youth',
                'organizational_unit_id' => $this->south->id,
            ])
            ->assertRedirect();

        $dept = Department::withoutGlobalScopes()->firstOrFail();

        $this->assertSame($this->south->id, (int) $dept->getRawOriginal('organizational_unit_id'));
        $this->actingAs($owner)
            ->get(route('departments.show', $dept))
            ->assertOk()
            ->assertSee('Youth');
    }

    /**
     * Same name in two branches, from the owner's form.
     *
     * Regression: asking two databases' worth of "is this name taken?" at once
     * (0 OR NULL, for the whole-church spelling) made any branch look taken as
     * soon as a church-wide department with that name existed.
     */
    public function test_the_owner_may_reuse_a_name_in_a_different_branch(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('departments.store'), ['name' => 'Choir'])->assertRedirect();
        $this->actingAs($owner)
            ->post(route('departments.store'), ['name' => 'Choir', 'organizational_unit_id' => $this->south->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Department::withoutGlobalScopes()->count());
    }

    /**
     * The owner creates a church-wide department (the default choice).
     */
    public function test_the_owner_can_still_create_a_whole_church_department(): void
    {
        $this->actingAs($this->owner())
            ->post(route('departments.store'), ['name' => 'Ushering'])
            ->assertRedirect();

        $dept = Department::withoutGlobalScopes()->firstOrFail();

        // Whatever this schema stores a whole-church department as (0 with the
        // sentinel, NULL where the branch foreign key is still enforced), it
        // must be a value the database accepted and the screen reads back as
        // "whole church" — never a branch id.
        $this->assertSame(Department::unitIdFor(null), $dept->getRawOriginal('organizational_unit_id'));
    }
}

/**
 * The department LIST is strictly parish-owned for a parish pastor.
 *
 * `test_a_parish_pastor_sees_whole_church_departments` above locks down
 * `visibleTo()`, which ADDS whole-church departments and is still what route
 * binding and the SMS/attendance pickers use. The /departments page itself must
 * not show them: those are the owner's, not the parish's.
 */
class DepartmentListIsParishOnlyTest extends \Tests\TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    private function parishUser(Church $church, OrganizationalUnit $parish, bool $registrar): User
    {
        $user = User::factory()->create([
            'church_id' => $church->id,
            'organizational_unit_id' => $parish->id,
            'is_registrar' => $registrar,
        ]);
        $role = Role::create(['church_id' => $church->id, 'name' => 'R'.uniqid()]);
        $role->permissions()->attach(
            collect(['members.view', 'departments.manage'])->map(
                fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'x', 'label' => $n])->id
            )
        );
        $user->roles()->attach($role);
        $user->load('roles.permissions');

        return $user;
    }

    public function test_a_parish_pastor_is_not_shown_whole_church_departments(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $type = UnitType::create(['church_id' => $church->id, 'name' => 'Parish', 'level' => 1]);
        $mine = OrganizationalUnit::create(['church_id' => $church->id, 'unit_type_id' => $type->id, 'name' => 'Wisdom Parish']);
        $other = OrganizationalUnit::create(['church_id' => $church->id, 'unit_type_id' => $type->id, 'name' => 'Faith Parish']);

        Department::create(['church_id' => $church->id, 'name' => 'Whole Church Choir']);
        Department::create(['church_id' => $church->id, 'organizational_unit_id' => $other->id, 'name' => 'Faith Media']);
        Department::create(['church_id' => $church->id, 'organizational_unit_id' => $mine->id, 'name' => 'Wisdom Ushering']);

        $pastor = $this->parishUser($church, $mine, false);

        $this->actingAs($pastor)
            ->get('/departments')
            ->assertOk()
            ->assertSee('Wisdom Ushering')
            ->assertDontSee('Whole Church Choir')
            ->assertDontSee('Faith Media');
    }

    public function test_the_owner_is_still_shown_every_department(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $type = UnitType::create(['church_id' => $church->id, 'name' => 'Parish', 'level' => 1]);
        $main = OrganizationalUnit::create(['church_id' => $church->id, 'unit_type_id' => $type->id, 'name' => 'Main']);
        $parish = OrganizationalUnit::create(['church_id' => $church->id, 'unit_type_id' => $type->id, 'name' => 'Wisdom Parish', 'parent_id' => $main->id]);

        Department::create(['church_id' => $church->id, 'name' => 'Whole Church Choir']);
        Department::create(['church_id' => $church->id, 'organizational_unit_id' => $parish->id, 'name' => 'Wisdom Ushering']);

        $owner = $this->parishUser($church, $main, true);

        $this->actingAs($owner)
            ->get('/departments')
            ->assertOk()
            ->assertSee('Whole Church Choir')
            ->assertSee('Wisdom Ushering');
    }
}

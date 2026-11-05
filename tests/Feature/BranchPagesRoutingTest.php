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
 * Reported symptom: creating a branch from a brand-new account showed the bare
 * string "// No routing parameters".
 *
 * That phrase is what Laravel writes when a named route is called without the
 * parameters it declares. The risk on these pages is always the same: a route
 * call in a view that does not pass every required parameter, and a route
 * parameter name that does not match the variable the controller declares
 * (which a GET never exercises). So this walks the branch pages as a real
 * owner would — list, create, remove, and every category that renders a
 * branch list — and asserts none of them produce that error.
 */
class BranchPagesRoutingTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::factory()->create();
        app()->instance('tenant.church_id', $this->church->id);

        // A church exactly as ActivateChurchFromCheckout leaves it: one
        // "Headquarters" unit type plus the unit named after the church.
        $headquarters = UnitType::create(['church_id' => $this->church->id, 'name' => 'Headquarters', 'level' => 0]);
        OrganizationalUnit::create([
            'church_id' => $this->church->id,
            'unit_type_id' => $headquarters->id,
            'name' => $this->church->name,
        ]);

        $this->owner = $this->userWith([
            'members.view', 'members.create', 'finance.view', 'finance.record',
            'settings.manage', 'events.manage', 'announcements.manage',
            'attendance.manage', 'sms.manage', 'subvention.submit',
            'subvention.manage', 'loans.manage', 'budgets.manage',
        ], ['is_registrar' => true]);
    }

    private function userWith(array $permissions, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['church_id' => $this->church->id], $attributes));

        $role = Role::create(['church_id' => $this->church->id, 'name' => 'R '.uniqid()]);
        $role->permissions()->attach(
            collect($permissions)->map(
                fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'x', 'label' => $n])->id
            )
        );
        $user->roles()->attach($role);

        return $user->load('roles.permissions');
    }

    private function assertNoRoutingError(string $uri): string
    {
        $response = $this->actingAs($this->owner->fresh())->get($uri);
        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringNotContainsString('No routing parameters', $html, "Routing error rendered on {$uri}");

        return $html;
    }

    public function test_the_branches_page_renders_for_a_brand_new_church(): void
    {
        $html = $this->assertNoRoutingError('/branches');

        $this->assertStringContainsString('Branches', $html);
        $this->assertStringContainsString($this->church->name, $html);
        // The remove action must carry its {branch} parameter.
        $this->assertMatchesRegularExpression('#/branches/\d+#', $html);
    }

    public function test_creating_a_branch_from_a_new_account_succeeds_without_a_routing_error(): void
    {
        $this->actingAs($this->owner)
            ->post('/branches', ['name' => 'Ikeja', 'code' => 'IKJ01'])
            ->assertRedirect(route('branches.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('organizational_units', [
            'church_id' => $this->church->id,
            'name' => 'Ikeja',
            'code' => 'IKJ01',
        ]);

        $html = $this->assertNoRoutingError('/branches');
        $this->assertStringContainsString('Ikeja', $html);
    }

    public function test_creating_a_branch_without_a_type_uses_the_churchs_own_default(): void
    {
        // unit_type_id omitted — the ordinary case for a new church.
        $this->actingAs($this->owner)
            ->post('/branches', ['name' => 'Surulere'])
            ->assertRedirect(route('branches.index'));

        $unit = OrganizationalUnit::where('name', 'Surulere')->firstOrFail();
        $this->assertSame('Branch', $unit->unitType->name);
    }

    public function test_removing_a_branch_from_the_page_route_matches_the_controller(): void
    {
        $unit = OrganizationalUnit::create([
            'church_id' => $this->church->id,
            'unit_type_id' => UnitType::where('church_id', $this->church->id)->value('id'),
            'name' => 'Surulere',
        ]);

        $this->actingAs($this->owner)
            ->delete(route('branches.destroy', ['branch' => $unit->id]))
            ->assertRedirect(route('branches.index'));

        $this->assertSoftDeleted('organizational_units', ['id' => $unit->id]);
    }

    /**
     * Every category that lists branches: People, Activities, Finance,
     * Communication, Subvention, Reports and Settings.
     */
    public function test_no_branch_baring_page_shows_a_routing_error(): void
    {
        foreach ([
            '/dashboard',
            '/members',
            '/departments',
            '/groups',
            '/events',
            '/attendance',
            '/announcements',
            '/tasks',
            '/finance/accounts',
            '/finance/loans',
            '/finance/budgets',
            '/subvention/submissions',
            '/sms/campaigns',
            '/reports',
            '/settings',
        ] as $uri) {
            $this->assertNoRoutingError($uri);
        }
    }

    public function test_a_church_term_of_any_case_is_pluralised_correctly(): void
    {
        $this->church->update(['settings' => ['terminology' => ['branch' => 'PARISH']]]);

        $html = $this->assertNoRoutingError('/branches');

        $this->assertStringContainsString('Parishes', $html);
        $this->assertStringNotContainsString('PARISHes', $html);
    }
}

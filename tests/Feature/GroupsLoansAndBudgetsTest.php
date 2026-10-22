<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Group;
use App\Models\Loan;
use App\Models\Member;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Groups, loan requests and borrowerless loans.
 *
 * Each of these covers a reported symptom rather than a unit of code:
 *   - "only one group could be created, the next overwrote it";
 *   - "a pastor has no way to request a loan";
 *   - "submitting the loan form showed // No routing parameters".
 */
class GroupsLoansAndBudgetsTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        $this->church = Church::factory()->create();
        app()->instance('tenant.church_id', $this->church->id);
    }

    private function userWith(array $permissions, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['church_id' => $this->church->id], $attributes));

        if ($permissions !== []) {
            $role = Role::create(['church_id' => $this->church->id, 'name' => 'R '.uniqid()]);
            $role->permissions()->attach(
                collect($permissions)->map(
                    fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'x', 'label' => $n])->id
                )
            );
            $user->roles()->attach($role);
        }

        $user->load('roles.permissions');

        return $user;
    }

    private function branch(string $name = 'North Parish'): OrganizationalUnit
    {
        $type = UnitType::firstOrCreate(
            ['church_id' => $this->church->id, 'name' => 'Branch'],
            ['level' => 1]
        );

        return OrganizationalUnit::create([
            'church_id' => $this->church->id, 'unit_type_id' => $type->id, 'name' => $name,
        ]);
    }

    // ---------------------------------------------------------------- groups

    public function test_an_admin_can_create_unlimited_groups(): void
    {
        $admin = $this->userWith(['groups.manage', 'members.view']);

        for ($i = 1; $i <= 12; $i++) {
            $this->actingAs($admin)
                ->post(route('groups.store'), ['name' => "Group {$i}"])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(12, Group::count());
    }

    public function test_creating_a_group_with_a_name_already_in_use_is_a_field_error_not_a_crash(): void
    {
        $admin = $this->userWith(['groups.manage', 'members.view']);
        Group::create(['church_id' => $this->church->id, 'name' => 'Choir']);

        $this->actingAs($admin)
            ->post(route('groups.store'), ['name' => 'Choir'])
            ->assertRedirect()
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Group::count());
    }

    public function test_a_removed_groups_name_can_be_used_again(): void
    {
        $admin = $this->userWith(['groups.manage', 'members.view']);
        $group = Group::create(['church_id' => $this->church->id, 'name' => 'Choir']);

        $this->actingAs($admin)->delete(route('groups.destroy', $group))->assertRedirect();
        $this->assertSame(0, Group::count());

        $this->actingAs($admin)
            ->post(route('groups.store'), ['name' => 'Choir'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Group::count());
    }

    // ----------------------------------------------------------------- loans

    public function test_a_pastor_without_finance_rights_can_request_a_loan_for_their_branch(): void
    {
        $branch = $this->branch();
        $pastor = $this->userWith(['subvention.submit'], ['organizational_unit_id' => $branch->id]);

        $this->actingAs($pastor)
            ->post(route('finance.loans.store'), [
                'principal_amount' => '500000',
                'monthly_deduction' => '50000',
                'notes' => 'Roof repair',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $loan = Loan::firstOrFail();
        $this->assertSame($branch->id, $loan->organizational_unit_id);
        $this->assertNull($loan->member_id);
        $this->assertSame('pending', $loan->status);
    }

    public function test_a_loan_request_cannot_name_another_parish(): void
    {
        $mine = $this->branch('North Parish');
        $theirs = $this->branch('South Parish');
        $pastor = $this->userWith(['subvention.submit'], ['organizational_unit_id' => $mine->id]);

        $this->actingAs($pastor)->post(route('finance.loans.store'), [
            'organizational_unit_id' => $theirs->id, // ignored on the request path
            'principal_amount' => '1000',
            'monthly_deduction' => '100',
        ])->assertRedirect();

        $this->assertSame($mine->id, Loan::firstOrFail()->organizational_unit_id);
    }

    public function test_a_finance_manager_still_records_a_loan_directly(): void
    {
        $manager = $this->userWith(['loans.manage', 'finance.view']);
        $member = Member::create(['church_id' => $this->church->id, 'full_name' => 'Ada Nwosu']);

        $this->actingAs($manager)->post(route('finance.loans.store'), [
            'member_id' => $member->id,
            'principal_amount' => '1000',
            'monthly_deduction' => '100',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('active', Loan::firstOrFail()->status);
    }

    public function test_a_loan_with_no_borrower_is_cannot_be_created(): void
    {
        $manager = $this->userWith(['loans.manage', 'finance.view']);

        $this->actingAs($manager)
            ->post(route('finance.loans.store'), [
                'principal_amount' => '1000',
                'monthly_deduction' => '100',
            ])
            ->assertStatus(422);

        $this->assertSame(0, Loan::count());
    }

    public function test_opening_a_borrowerless_loan_says_so_instead_of_erroring(): void
    {
        // A row written by the old form before it was fixed. Opening it used to
        // fail on the "No routing parameters" error; now it explains the row.
        $loan = Loan::create([
            'church_id' => $this->church->id,
            'principal_amount' => '1000', 'monthly_deduction' => '100',
        ]);

        $manager = $this->userWith(['loans.manage', 'finance.view']);

        $response = $this->actingAs($manager)->get(route('finance.loans.show', $loan));

        $response->assertStatus(409);
        $response->assertSee('This loan has no borrower recorded', false);
    }

    // --------------------------------------------------------------- budgets

    public function test_a_budget_submits_from_its_form_without_a_csrf_failure(): void
    {
        $manager = $this->userWith(['budgets.manage', 'finance.view']);

        $this->actingAs($manager)
            ->post(route('finance.budgets.store'), [
                'name' => '2026 Operating Budget',
                'period_start' => '2026-01-01',
                'period_end' => '2026-12-31',
                'items' => [['category' => 'Utilities', 'planned_amount' => '5000']],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('budgets', ['name' => '2026 Operating Budget']);
    }
}

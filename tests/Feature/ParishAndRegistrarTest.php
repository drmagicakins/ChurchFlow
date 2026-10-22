<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Church;
use App\Models\FinancialAccount;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SubventionPeriod;
use App\Models\SubventionRuleSet;
use App\Models\SubventionSubmission;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The parish / registrar rules and the finance fixes that the brief calls out:
 *
 *  - a pastor belongs to a parish and sees only that parish's information;
 *  - the registrar (super-admin) sees and approves every parish's subventions;
 *  - a pastor gets NO approve option and, once approved, sees "Approved";
 *  - the finance "Add account" form actually validates and saves (the type
 *    options and opening_balance were mismatched with the validator);
 *  - a pastor can record a bank account from the Nigerian bank list and the
 *    registrar can see it.
 */
class ParishAndRegistrarTest extends TestCase
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

    private function parish(Church $church, string $name): OrganizationalUnit
    {
        $type = UnitType::firstOrCreate(
            ['church_id' => $church->id, 'name' => 'Parish'],
            ['level' => 1],
        );

        return OrganizationalUnit::create(['church_id' => $church->id, 'unit_type_id' => $type->id, 'name' => $name]);
    }

    private function submissionFor(Church $church, OrganizationalUnit $parish): SubventionSubmission
    {
        $ruleSet = SubventionRuleSet::factory()->for($church, 'church')->create();
        $period = SubventionPeriod::factory()->for($church, 'church')->create();

        return SubventionSubmission::create([
            'church_id' => $church->id,
            'subvention_period_id' => $period->id,
            'organizational_unit_id' => $parish->id,
            'subvention_rule_set_id' => $ruleSet->id,
            'figures' => ['general_tithe' => '1000'],
        ]);
    }

    public function test_a_parish_pastor_sees_only_their_own_parishs_submissions(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $mine = $this->parish($church, 'Grace Parish');
        $theirs = $this->parish($church, 'Hope Parish');

        $pastor = $this->userIn($church, ['subvention.submit'], $mine);
        $this->submissionFor($church, $mine);
        $this->submissionFor($church, $theirs);

        $response = $this->actingAs($pastor)->get(route('subvention.submissions.index'));

        $response->assertOk();
        $response->assertSee('Grace Parish');
        $response->assertDontSee('Hope Parish');
    }

    public function test_the_registrar_sees_every_parishs_submissions(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $a = $this->parish($church, 'Grace Parish');
        $b = $this->parish($church, 'Hope Parish');

        $registrar = $this->userIn($church, ['subvention.submit', 'subvention.approve'], null, registrar: true);
        $this->submissionFor($church, $a);
        $this->submissionFor($church, $b);

        $response = $this->actingAs($registrar)->get(route('subvention.submissions.index'));

        $response->assertOk();
        $response->assertSee('Grace Parish');
        $response->assertSee('Hope Parish');
    }

    public function test_a_parish_pastor_cannot_approve_a_subvention_even_with_the_permission(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $parish = $this->parish($church, 'Grace Parish');

        // Deliberately given subvention.approve — the rule is that a
        // parish-scoped user must not be able to approve, regardless of role.
        $pastor = $this->userIn($church, ['subvention.submit', 'subvention.approve'], $parish);
        $submission = $this->submissionFor($church, $parish);

        $this->actingAs($pastor)
            ->post(route('subvention.submissions.approve', $submission))
            ->assertForbidden();
    }

    public function test_a_pastor_sees_approved_on_their_submission_without_an_approve_option(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $parish = $this->parish($church, 'Grace Parish');
        $pastor = $this->userIn($church, ['subvention.submit'], $parish);
        $registrar = $this->userIn($church, ['subvention.submit', 'subvention.approve'], null, registrar: true);

        $submission = $this->submissionFor($church, $parish);

        app(\App\Domains\Subventions\Services\SubventionWorkflowService::class)->submit($submission, $pastor);
        app(\App\Domains\Subventions\Services\SubventionWorkflowService::class)
            ->approve($submission->fresh(), $registrar, 'Approved.');

        $response = $this->actingAs($pastor)
            ->get(route('subvention.submissions.show', $submission->fresh()));

        $response->assertOk();
        $response->assertSee('Approved by the registrar');
        // No approve control is offered to the pastor.
        $response->assertDontSee('name="comments"', false);
    }

    public function test_a_financial_account_can_be_created_with_a_bank_type_and_opening_balance(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $user = $this->userIn($church, ['finance.view', 'finance.record', 'settings.manage']);

        $this->actingAs($user)->post(route('finance.accounts.store'), [
            'name' => 'Main Bank Account',
            'type' => 'bank',
            'opening_balance' => '50000',
            'description' => 'Opening',
        ])->assertRedirect(route('finance.accounts.index'));

        $account = FinancialAccount::where('name', 'Main Bank Account')->firstOrFail();
        $this->assertSame('bank', $account->type);
        // The opening balance is a real income transaction, so it shows in the balance.
        $this->assertSame('50000.00', $account->balance());
    }

    public function test_a_pastor_records_a_bank_account_from_the_nigerian_list_and_the_registrar_sees_it(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $pastor = $this->userIn($church, ['finance.view', 'sms.manage']);
        $registrar = $this->userIn($church, ['finance.view', 'settings.manage'], null, registrar: true);

        $this->actingAs($pastor)->post(route('finance.bank-accounts.store'), [
            'bank_code' => '000013', // Guaranty Trust Bank
            'account_name' => 'John Adeyemi',
            'account_number' => '0123456789',
        ])->assertRedirect();

        $account = BankAccount::firstOrFail();
        $this->assertSame('Guaranty Trust Bank (GTBank)', $account->bank_name);
        $this->assertSame($pastor->id, $account->user_id);
        $this->assertSame('••••••6789', $account->maskedNumber());

        // The registrar sees the pastor's account on the same screen.
        $this->actingAs($registrar)
            ->get(route('finance.bank-accounts.index'))
            ->assertOk()
            ->assertSee('John Adeyemi')
            ->assertSee('GTBank');
    }

    public function test_only_the_registrar_can_delete_a_user(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $manager = $this->userIn($church, ['settings.manage']);
        $victim = $this->userIn($church, ['members.view']);

        // A plain settings manager (not a registrar) cannot delete.
        $this->actingAs($manager)
            ->delete(route('settings.team.destroy', $victim))
            ->assertForbidden();

        $this->assertNull($victim->fresh()->deleted_at);
    }

    public function test_a_registrar_can_delete_a_user(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $registrar = $this->userIn($church, ['settings.manage'], null, registrar: true);
        $victim = $this->userIn($church, ['members.view']);

        $this->actingAs($registrar)
            ->delete(route('settings.team.destroy', $victim))
            ->assertRedirect();

        $this->assertSoftDeleted('users', ['id' => $victim->id]);
    }
}

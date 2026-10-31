<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Member;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Models\FinancialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard's contract: real numbers, only this church's numbers, and only
 * the panels the signed-in user is allowed to see.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(Church $church, array $permissions): User
    {
        $user = User::factory()->create(['church_id' => $church->id]);

        if ($permissions !== []) {
            $ids = collect($permissions)->map(fn ($n) => Permission::firstOrCreate(
                ['name' => $n], ['group' => 'x', 'label' => 'x']
            )->id);
            $role = Role::create(['church_id' => $church->id, 'name' => 'Test role']);
            $role->permissions()->attach($ids);
            $user->roles()->attach($role);
        }

        return $user->load('roles.permissions');
    }

    private function income(Church $church, FinancialAccount $account, User $by, int $amount): void
    {
        app()->instance('tenant.church_id', $church->id);
        Transaction::create([
            'church_id' => $church->id, 'financial_account_id' => $account->id, 'type' => 'income',
            'category' => 'offering', 'amount' => $amount, 'transacted_on' => now()->toDateString(),
            'recorded_by' => $by->id, 'approval_status' => 'not_required',
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect();
    }

    public function test_kpis_reflect_this_churchs_real_records_and_never_another_churchs(): void
    {
        $a = Church::factory()->create();
        $b = Church::factory()->create();

        $userA = $this->userWith($a, ['members.view', 'finance.view']);
        $userB = $this->userWith($b, ['members.view', 'finance.view']);

        app()->instance('tenant.church_id', $a->id);
        Member::factory()->count(3)->for($a, 'church')->create();
        $accountA = FinancialAccount::factory()->for($a, 'church')->create();
        $this->income($a, $accountA, $userA, 250_000);

        app()->instance('tenant.church_id', $b->id);
        Member::factory()->count(7)->for($b, 'church')->create();
        $accountB = FinancialAccount::factory()->for($b, 'church')->create();
        $this->income($b, $accountB, $userB, 9_999_999);

        $html = $this->actingAs($userA)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('₦250,000', $html);
        $this->assertStringNotContainsString('9,999,999', $html);
        $this->assertMatchesRegularExpression('/Total Members<\/p>\s*<p class="cfd-kpi__value">3</', $html);
    }

    public function test_finance_and_activity_panels_are_hidden_without_permission(): void
    {
        $church = Church::factory()->create();
        $user = $this->userWith($church, []); // signed in, but holds no permissions

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('Financial Overview', $html);
        $this->assertStringNotContainsString('Total Members', $html);
        $this->assertStringNotContainsString('Recent Activities', $html);
        $this->assertStringNotContainsString('Record Payment', $html);
    }

    public function test_quick_actions_only_offer_what_the_user_may_do(): void
    {
        $church = Church::factory()->create();
        $user = $this->userWith($church, ['members.create']);

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Add Member', $html);
        $this->assertStringNotContainsString('Record Payment', $html);
        $this->assertStringNotContainsString('Create Event', $html);
    }

    public function test_empty_church_shows_empty_states_instead_of_fake_numbers(): void
    {
        $church = Church::factory()->create();
        $user = $this->userWith($church, ['members.view', 'finance.view', 'settings.manage']);

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('No financial transactions found', $html);
        $this->assertStringContainsString('No upcoming events', $html);
        $this->assertStringContainsString('Nothing scheduled for today', $html);
        $this->assertStringContainsString('No departments yet', $html);
        $this->assertStringContainsString('no prior data', $html);
    }

    /**
     * The sub-heading names the place the user works in, not the owner.
     *
     * A parish pastor's dashboard used to read "Here's what's happening with
     * <church name> today" — i.e. it described the church the owner registered,
     * which is not the parish the pastor is responsible for.
     */
    public function test_a_parish_pastors_dashboard_names_their_parish(): void
    {
        $church = Church::factory()->create(['name' => 'Grace Chapel']);
        app()->instance('tenant.church_id', $church->id);

        $type = \App\Models\UnitType::create(['church_id' => $church->id, 'name' => 'Parish', 'level' => 1]);
        $parish = \App\Models\OrganizationalUnit::create([
            'church_id' => $church->id, 'unit_type_id' => $type->id, 'name' => 'Wisdom Parish',
        ]);

        $pastor = $this->userWith($church, ['members.view']);
        $pastor->forceFill([
            'organizational_unit_id' => $parish->id,
            'is_registrar' => false,
        ])->save();
        $pastor->load('organizationalUnit');

        $html = $this->actingAs($pastor->fresh())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString("Here's what's happening with Wisdom Parish today.", $html);
        $this->assertStringNotContainsString("Here's what's happening with Grace Chapel today.", $html);
    }

    /**
     * The owner is not parish-scoped, so they keep the church's own name.
     */
    public function test_the_church_owners_dashboard_still_names_the_church(): void
    {
        $church = Church::factory()->create(['name' => 'Grace Chapel']);
        app()->instance('tenant.church_id', $church->id);

        $owner = $this->userWith($church, ['members.view']);
        $owner->forceFill(['is_registrar' => true])->save();

        $html = $this->actingAs($owner->fresh())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString("Here's what's happening with Grace Chapel today.", $html);
    }
}

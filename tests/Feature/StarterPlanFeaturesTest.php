<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A Starter church must be able to use every feature the pricing page promises
 * it: members/families/groups, events, attendance, income and expense records,
 * email notifications and standard support — plus moving up to another plan.
 *
 * The four bugs these lock down all had the same shape: a starter church was
 * refused an action it was sold, or shown an error page it could act on.
 */
class StarterPlanFeaturesTest extends TestCase
{
    use RefreshDatabase;

    /** The church owner (registrar) of a Starter church, with the seeded owner role. */
    private function starterOwner(): array
    {
        $this->seed(RolePermissionSeeder::class);

        $church = Church::factory()->create(['status' => 'active']);
        app()->instance('tenant.church_id', $church->id);

        $plan = Plan::factory()->create([
            'name' => 'Starter', 'slug' => 'starter', 'monthly_price' => 10000,
            'features' => ['api_access' => false, 'custom_domain' => false, 'advanced_reports' => false],
        ]);

        Subscription::create([
            'church_id' => $church->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->startOfMonth()->addMonth(),
        ]);

        $user = User::factory()->create([
            'church_id' => $church->id,
            'is_registrar' => true,
        ]);

        $role = \App\Models\Role::firstOrCreate(['church_id' => null, 'name' => 'Church Owner']);
        $role->permissions()->sync(\App\Models\Permission::pluck('id'));
        $user->roles()->attach($role);

        return [$church, $user->fresh(), $plan];
    }

    public function test_a_starter_church_can_reach_members_families_and_groups(): void
    {
        [, $user] = $this->starterOwner();

        $this->actingAs($user)->get('/members')->assertOk();
        $this->actingAs($user)->get('/families')->assertOk();
        $this->actingAs($user)->get('/groups')->assertOk();
    }

    public function test_a_starter_church_can_reach_income_and_expense_records(): void
    {
        [, $user] = $this->starterOwner();

        $this->actingAs($user)->get('/finance/accounts')->assertOk();

        $this->actingAs($user)
            ->post('/finance/accounts', ['name' => 'Main Offering', 'type' => 'general'])
            ->assertRedirect();

        $this->assertSame(1, \App\Models\FinancialAccount::withoutGlobalScopes()->count());

        $account = \App\Models\FinancialAccount::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($user)
            ->post("/finance/accounts/{$account->id}/income", [
                'category' => 'Offering', 'amount' => 5000, 'transacted_on' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(1, \App\Models\Transaction::withoutGlobalScopes()->where('type', 'income')->count());
    }

    public function test_a_starter_church_has_email_notifications_and_standard_support(): void
    {
        [, $user] = $this->starterOwner();

        // Email notifications: the notification inbox is available to every plan.
        $this->actingAs($user)->get('/notifications')->assertOk();
        // Standard support: the support desk is available to every plan.
        $this->actingAs($user)->get('/support')->assertOk();
    }

    public function test_a_starter_church_can_create_an_event(): void
    {
        [$church, $user] = $this->starterOwner();

        $this->actingAs($user)
            ->post('/events', ['title' => 'Harvest Service', 'starts_at' => now()->addWeek()->toDateTimeString()])
            ->assertRedirect();

        $this->assertSame(1, \App\Models\Event::withoutGlobalScopes()->where('church_id', $church->id)->count());
    }

    public function test_a_starter_church_can_create_an_attendance_session(): void
    {
        [$church, $user] = $this->starterOwner();

        $this->actingAs($user)
            ->post('/attendance', ['name' => 'Sunday First Service', 'type' => 'service', 'session_date' => now()->toDateString()])
            ->assertRedirect();

        $this->assertSame(1, \App\Models\AttendanceSession::withoutGlobalScopes()->where('church_id', $church->id)->count());
    }

    public function test_a_starter_church_can_create_a_task(): void
    {
        [$church, $user] = $this->starterOwner();

        $this->actingAs($user)
            ->post('/tasks', ['title' => 'Call the new members'])
            ->assertRedirect();

        $this->assertSame(1, \App\Models\Task::withoutGlobalScopes()->where('church_id', $church->id)->count());
    }

    public function test_a_starter_owner_with_no_form_choice_sees_the_borrower_rule_not_a_crash(): void
    {
        [$church, $user] = $this->starterOwner();

        // The owner CAN manage loans, so the manager path applies: the form
        // omitted both borrower fields. This used to abort(422) into a raw
        // error document; it must re-render Loans with the rule stated.
        $this->actingAs($user)
            ->post(route('finance.loans.store'), [
                'principal_amount' => 50000,
                'monthly_deduction' => 5000,
            ])
            ->assertOk()
            ->assertSee('A loan must belong to a borrower');

        $this->assertSame(0, \App\Models\Loan::withoutGlobalScopes()->where('church_id', $church->id)->count());
    }

    public function test_a_starter_church_can_upgrade_its_plan(): void
    {
        [$church, $user, $starter] = $this->starterOwner();
        $growth = Plan::factory()->create(['name' => 'Growth', 'slug' => 'growth', 'monthly_price' => 25000]);

        $this->actingAs($user)
            ->get(route('billing.change-plan.preview', ['plan_id' => $growth->id]))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('billing.change-plan'), ['plan_id' => $growth->id])
            ->assertRedirect(route('billing.show'));

        $this->assertSame($growth->id, (int) Subscription::withoutGlobalScopes()->where('church_id', $church->id)->latest()->first()->plan_id);
    }

    public function test_the_church_owner_can_still_upgrade_when_their_role_copy_lacks_billing_manage(): void
    {
        // A church-scoped "Church Owner" role whose permission clone predates
        // `billing.manage` (or was edited afterwards) must not leave the owner
        // unable to move off Starter. This is the reported "Starter plan cannot
        // upgrade" shape: the plan page itself answers 403.
        [$church, $user] = $this->starterOwner();

        $ownerRole = \App\Models\Role::withoutGlobalScopes()
            ->where('church_id', null)->where('name', 'Church Owner')->firstOrFail();
        $ownerRole->permissions()->detach(\App\Models\Permission::where('name', 'billing.manage')->value('id'));

        $this->assertFalse($user->fresh()->hasPermission('billing.manage'), 'Precondition: the role copy really lacks billing.manage.');

        $growth = Plan::factory()->create(['name' => 'Growth', 'slug' => 'growth', 'monthly_price' => 25000]);

        $this->actingAs($user->fresh())->get(route('billing.plans'))->assertOk();

        $this->actingAs($user->fresh())
            ->post(route('billing.change-plan'), ['plan_id' => $growth->id])
            ->assertRedirect(route('billing.show'));

        $this->assertSame($growth->id, (int) Subscription::withoutGlobalScopes()->where('church_id', $church->id)->latest()->first()->plan_id);
    }

    public function test_a_pastor_requesting_a_loan_without_a_branch_is_told_why_not_shown_an_authorization_page(): void
    {
        [$church] = $this->starterOwner();

        // The REQUESTER path (no loans.manage) is where the reported
        // "This action is unauthorized" came from: the account had no branch
        // to file the request against. Give this person exactly that shape —
        // an administrator who can SEE loans (finance.view) but cannot manage
        // them, which is what the Loans page offers "Request a Loan" for.
        $pastor = User::factory()->create(['church_id' => $church->id, 'organizational_unit_id' => null]);
        $role = \App\Models\Role::create(['church_id' => $church->id, 'name' => 'Starter Pastor']);
        $role->permissions()->attach(
            \App\Models\Permission::whereIn('name', ['members.view', 'finance.view', 'events.manage', 'attendance.manage'])->pluck('id')
        );
        $pastor->roles()->attach($role);

        $this->actingAs($pastor->fresh())->get(route('finance.loans.index'))->assertOk();

        $this->actingAs($pastor->fresh())
            ->post(route('finance.loans.store'), [
                'principal_amount' => 50000,
                'monthly_deduction' => 5000,
            ])
            ->assertOk()
            ->assertSee('Loans')
            ->assertSee('not attached to a branch or a member record')
            ->assertDontSee('This action is unauthorized');

        $this->assertSame(0, \App\Models\Loan::withoutGlobalScopes()->where('church_id', $church->id)->count());
    }
}

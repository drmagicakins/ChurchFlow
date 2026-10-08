<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Appointment;
use App\Models\Budget;
use App\Models\Church;
use App\Models\Department;
use App\Models\Event;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\Group;
use App\Models\Loan;
use App\Models\Member;
use App\Models\PastoralCase;
use App\Models\Permission;
use App\Models\PrayerRequest;
use App\Models\Role;
use App\Models\SmsCampaign;
use App\Models\SmsWallet;
use App\Models\SubventionPeriod;
use App\Models\SubventionRuleSet;
use App\Models\SubventionSubmission;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Companion to SmokeEveryPageTest: every authenticated POST route, walked with a
 * valid payload as a user holding every permission.
 *
 * A POST route is where the mutation lives, and it is the half a GET sweep can
 * never reach — a controller that validates a field name its own form does not
 * send, or a service whose guard rejects the one input the UI actually
 * produces, passes every read-only assertion in the suite and still fails the
 * first time a real user presses the button. Each POST below is asserted on
 * "not a 5xx and not a validation bounce": a 302/200 means the request was
 * accepted and the work happened.
 */
class SmokeEveryActionTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithEverything(Church $church): User
    {
        $names = [
            'members.view', 'members.create', 'members.edit', 'members.delete',
            'families.manage', 'departments.manage', 'groups.manage',
            'finance.view', 'finance.record', 'finance.approve', 'budgets.manage', 'loans.manage',
            'subvention.submit', 'subvention.approve', 'subvention.manage',
            'events.manage', 'attendance.manage', 'announcements.manage', 'settings.manage',
            'pastoral.manage', 'sms.manage',
            // Phase 8's billing permission.
            'billing.manage',
        ];

        $ids = collect($names)->map(fn ($n) => Permission::firstOrCreate(
            ['name' => $n], ['group' => 'x', 'label' => 'x']
        )->id);

        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::create(['church_id' => $church->id, 'name' => 'Everything']);
        $role->permissions()->attach($ids);
        $user->roles()->attach($role);
        $user->load('roles.permissions');

        return $user;
    }

    /** A real, active subscription on the given church, so nothing is redirected. */
    private function activateSubscription(Church $church): void
    {
        $plan = \App\Models\Plan::create([
            'name' => 'Growth', 'slug' => 'growth-actions',
            'monthly_price' => 25000, 'yearly_price' => 250000, 'currency' => 'NGN',
            'max_members' => null, 'max_branches' => null, 'max_admins' => null, 'storage_mb' => 10240,
        ]);

        app(\App\Domains\Subscriptions\Services\SubscriptionService::class)
            ->createInitial($church, $plan, 'monthly');
    }

    public function test_every_authenticated_post_route_accepts_a_valid_submission(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $admin = $this->adminWithEverything($church);

        // Phase 8 gates the app on an active subscription, so every POST below
        // would otherwise be redirected to /billing before it reached its own
        // controller.
        $this->activateSubscription($church);

        $member = Member::factory()->for($church, 'church')->create(['phone' => '08010000000']);
        $other = Member::factory()->for($church, 'church')->create();
        $dept = Department::create(['church_id' => $church->id, 'name' => 'Ushering']);
        $family = Family::create(['church_id' => $church->id, 'name' => 'The Adeyemis']);
        $group = Group::create(['church_id' => $church->id, 'name' => 'Choir']);

        $event = Event::create([
            'church_id' => $church->id, 'title' => 'Sunday Service',
            'starts_at' => now()->addDays(2), 'capacity' => 100,
        ]);
        $announcement = Announcement::create([
            'church_id' => $church->id, 'title' => 'Notice', 'body' => 'Body',
            'audience_type' => 'church_wide', 'created_by' => $admin->id,
        ]);
        $task = Task::create([
            'church_id' => $church->id, 'title' => 'Fix the roof',
            'status' => 'pending', 'due_date' => now()->addDays(3),
        ]);
        $account = FinancialAccount::factory()->for($church, 'church')->create();
        // A transfer needs two DISTINCT accounts — the controller rejects a
        // no-op transfer to the same account by design.
        $secondAccount = FinancialAccount::factory()->for($church, 'church')->create();
        $budget = Budget::create([
            'church_id' => $church->id, 'financial_account_id' => $account->id,
            'name' => 'Q1', 'period_start' => now()->startOfMonth(), 'period_end' => now()->endOfMonth(),
        ]);
        $loan = Loan::create([
            'church_id' => $church->id, 'member_id' => $member->id,
            'principal_amount' => '1000.00', 'monthly_deduction' => '100.00',
        ]);

        $unitType = \App\Models\UnitType::create(['church_id' => $church->id, 'name' => 'Province', 'level' => 0]);
        $branch = \App\Models\OrganizationalUnit::create([
            'church_id' => $church->id, 'unit_type_id' => $unitType->id, 'name' => 'Test Branch',
        ]);
        // The store below is a CREATE, so it needs a branch that has no
        // submission for the period yet — reusing $branch would (correctly)
        // collide with the unique index and the fixture above it.
        $otherBranch = \App\Models\OrganizationalUnit::create([
            'church_id' => $church->id, 'unit_type_id' => $unitType->id, 'name' => 'Second Branch',
        ]);
        $ruleSet = SubventionRuleSet::factory()->for($church, 'church')->create();
        $ruleSet->rules()->createMany([
            ['name' => 'Salary', 'base_field' => 'salary', 'type' => 'percentage', 'rate' => 100,
                'classification' => 'deduction', 'sort_order' => 1],
        ]);
        $period = SubventionPeriod::factory()->for($church, 'church')->create();
        $submission = SubventionSubmission::create([
            'church_id' => $church->id, 'subvention_period_id' => $period->id,
            'organizational_unit_id' => $branch->id,
            'subvention_rule_set_id' => $ruleSet->id,
            'figures' => ['general_tithe' => '100', 'salary' => '0'],
        ]);

        $case = PastoralCase::create([
            'church_id' => $church->id, 'member_id' => $member->id,
            'type' => 'counseling', 'assigned_to' => $admin->id, 'opened_by' => $admin->id,
        ]);
        $prayerRequest = PrayerRequest::create([
            'church_id' => $church->id, 'request' => 'Pray for us',
            'submitted_by_name' => 'A visitor', 'assigned_to' => $admin->id,
        ]);
        $appointment = Appointment::create([
            'church_id' => $church->id, 'pastor_id' => $admin->id,
            'title' => 'Committee meeting', 'scheduled_at' => now()->addDay(),
        ]);

        $wallet = SmsWallet::withoutGlobalScopes()->create(['church_id' => $church->id, 'balance_units' => 100]);
        $campaign = SmsCampaign::factory()->for($church, 'church')->create(['created_by' => $admin->id]);

        // An expense awaiting a decision, so the approve/reject routes have
        // something in 'pending' to act on rather than aborting on a guard.
        $expense = \App\Models\Transaction::create([
            'church_id' => $church->id, 'financial_account_id' => $account->id,
            'type' => 'expense', 'category' => 'Rent', 'amount' => '500.00',
            'transacted_on' => now()->toDateString(), 'approval_status' => 'pending',
        ]);
        $approval = \App\Models\Approval::create([
            'church_id' => $church->id, 'approvable_type' => \App\Models\Transaction::class,
            'approvable_id' => $expense->id, 'status' => 'pending',
            'requested_by' => $admin->id,
        ]);

        $attendanceSession = \App\Models\AttendanceSession::create([
            'church_id' => $church->id, 'name' => 'Sunday First Service',
            'type' => 'service', 'session_date' => now()->toDateString(),
        ]);

        $payloads = [
            ['departments.store', '/departments', ['name' => 'Media Team']],
            ['departments.members.attach', "/departments/{$dept->id}/members", ['member_id' => $other->id]],
            ['families.store', '/families', ['name' => 'The Okafors']],
            ['families.members.attach', "/families/{$family->id}/members", [
                'member_id' => $other->id, 'relationship' => 'head',
            ]],
            ['groups.store', '/groups', ['name' => 'Ushering Team']],
            ['groups.members.attach', "/groups/{$group->id}/members", ['member_id' => $other->id]],
            ['events.store', '/events', [
                'title' => 'Harvest Service', 'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
                'capacity' => 200,
            ]],
            ['events.register', "/events/{$event->id}/register", ['member_id' => $other->id]],
            ['attendance.store', '/attendance', [
                'name' => 'Midweek Service', 'type' => 'service',
                'session_date' => now()->toDateString(),
            ]],
            ['attendance.records', "/attendance/{$attendanceSession->id}/records", [
                'records' => [['member_id' => $member->id, 'status' => 'present']],
            ]],
            ['announcements.store', '/announcements', [
                'title' => 'Harvest', 'body' => 'Come one come all.', 'audience_type' => 'church',
            ]],
            ['tasks.store', '/tasks', [
                'title' => 'Print bulletins', 'due_date' => now()->addDays(5)->toDateString(),
            ]],
            ['finance.accounts.store', '/finance/accounts', ['name' => 'Building Fund', 'type' => 'building']],
            ['finance.transactions.income', "/finance/accounts/{$account->id}/income", [
                'category' => 'Tithe', 'amount' => '1000.00', 'transacted_on' => now()->toDateString(),
            ]],
            ['finance.transactions.expense', "/finance/accounts/{$account->id}/expense", [
                'category' => 'Rent', 'amount' => '300.00', 'transacted_on' => now()->toDateString(),
            ]],
            ['finance.transactions.transfer', '/finance/transfer', [
                'from_account_id' => $account->id, 'to_account_id' => $secondAccount->id,
                'amount' => '50.00', 'transacted_on' => now()->toDateString(),
            ]],
            ['finance.budgets.store', '/finance/budgets', [
                'financial_account_id' => $account->id, 'name' => 'Q2',
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
            ]],
            ['finance.loans.store', '/finance/loans', [
                'member_id' => $other->id, 'principal_amount' => '2000.00', 'monthly_deduction' => '200.00',
            ]],
            ['finance.loans.payments', "/finance/loans/{$loan->id}/payments", [
                'amount' => '100.00', 'paid_on' => now()->toDateString(),
            ]],
            ['approvals.approve', "/approvals/{$approval->id}/approve", []],
            ['subvention.rule-sets.store', '/subvention/rule-sets', [
                'name' => 'Standard Rules',
                'rules' => [[
                    'name' => 'General Retention', 'base_field' => 'general_tithe',
                    'type' => 'percentage', 'rate' => 25, 'classification' => 'retention',
                ]],
            ]],
            ['subvention.periods.store', '/subvention/periods', [
                'name' => 'A Distinct Period Name',
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
            ]],
            ['subvention.submissions.store', '/subvention/submissions', [
                'subvention_period_id' => $period->id,
                'organizational_unit_id' => $otherBranch->id,
                'subvention_rule_set_id' => $ruleSet->id,
                'figures' => ['general_tithe' => '500', 'salary' => '100'],
            ]],
            // Runs LAST among the subvention writes: the store above already
            // created a second draft, and submit() on it is the same code path
            // with a different precondition, which is worth hitting too.
            ['subvention.submissions.submit', "/subvention/submissions/{$submission->id}/submit", []],
            ['pastoral.prayer-requests.store', '/pastoral/prayer-requests', [
                'request' => 'Healing for my mother', 'submitted_by_name' => 'A member',
            ]],
            ['pastoral.prayer-requests.update', "/pastoral/prayer-requests/{$prayerRequest->id}", [
                'status' => 'praying',
            ], 'PUT'],
            ['pastoral.cases.store', '/pastoral/cases', [
                'member_id' => $member->id, 'type' => 'welfare', 'summary' => 'Needs support',
            ]],
            ['pastoral.cases.notes', "/pastoral/cases/{$case->id}/notes", ['note' => 'First session held.']],
            ['pastoral.appointments.store', '/pastoral/appointments', [
                'pastor_id' => $admin->id, 'title' => 'Follow-up',
                'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            ]],
            ['pastoral.appointments.update', "/pastoral/appointments/{$appointment->id}", [
                'status' => 'completed',
            ], 'PUT'],
            ['sms.campaigns.store', '/sms/campaigns', [
                'name' => 'Harvest Invite', 'message' => 'Join us this Sunday!',
                'audience_type' => 'church',
            ]],
            ['sms.campaigns.confirm', "/sms/campaigns/{$campaign->id}/confirm", []],
            ['sms.wallet.credit', '/sms/wallet/credit', ['units' => 500, 'reference' => 'manual-topup']],
        ];

        $failures = [];

        // A 500 comes back as a rendered response, not a thrown exception, so the
        // only way to see WHAT broke is to hook the reporter the framework uses
        // for uncaught exceptions. The message is appended to the failure entry.
        $caught = null;
        $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->reportable(function (\Throwable $e) use (&$caught) {
                if ($caught === null) {
                    $caught = get_class($e).': '.$e->getMessage()
                        .' @ '.basename($e->getFile()).':'.$e->getLine();
                }
            });

        foreach ($payloads as $entry) {
            [$name, $uri, $payload] = $entry;
            $verb = $entry[3] ?? 'POST';

            $caught = null;

            try {
                $response = $this->actingAs($admin)->json($verb, $uri, $payload);
            } catch (\Throwable $e) {
                throw new \RuntimeException("{$verb} {$name} ({$uri}) threw ".get_class($e).': '.$e->getMessage(), 0, $e);
            }

            // ->json() keeps the request inside the same session, so the
            // server-side validation bag is populated for a 422 but a 302 is
            // returned as-is rather than being followed.
            $status = $response->getStatusCode();

            // 302 (redirect after success) and 200 are both fine. A 422 means the
            // controller rejected the payload we believe the UI sends, and a 5xx
            // is an outright break — both are recorded.
            if (! in_array($status, [200, 201, 302], true)) {
                $detail = '';

                if ($status === 422) {
                    $bag = $response->json('errors');
                    $detail = $bag ? json_encode($bag) : '';
                }

                if ($status >= 500 && $caught !== null) {
                    $detail = $caught;
                }

                $failures[] = "{$verb} {$name} ({$uri}) -> {$status} {$detail}";
            }
        }

        $this->assertSame([], $failures, "These POST routes did not accept a valid submission:\n".implode("\n", $failures));
    }
}

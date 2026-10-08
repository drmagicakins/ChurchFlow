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
 * Throwaway smoke sweep: render EVERY authenticated GET page as a user holding
 * every permission, and assert none of them 500.
 *
 * The per-phase feature tests each exercise the pages their own phase owns. What
 * none of them do is walk the whole route table, so a view that references a
 * variable its controller never passes — or a Blade directive that only breaks
 * with real data — stays invisible until someone clicks it in a browser. This
 * test is the cheapest way to make that class of bug fail loudly here instead.
 */
class SmokeEveryPageTest extends TestCase
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
            // Phase 8 — without this the billing pages 403 and the sweep
            // reports a failure that is really just a stale fixture.
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
            'name' => 'Growth', 'slug' => 'growth-smoke',
            'monthly_price' => 25000, 'yearly_price' => 250000, 'currency' => 'NGN',
            'max_members' => null, 'max_branches' => null, 'max_admins' => null, 'storage_mb' => 10240,
        ]);

        app(\App\Domains\Subscriptions\Services\SubscriptionService::class)
            ->createInitial($church, $plan, 'monthly');
    }

    public function test_every_authenticated_get_page_renders_without_error(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $admin = $this->adminWithEverything($church);

        // Phase 8 gates the whole application on an active subscription, so a
        // tenant without one is redirected to /billing on every page. Give the
        // church a live subscription here and let the billing tests own the
        // "no subscription" case.
        $this->activateSubscription($church);

        // Enough real data that every @forelse takes its non-empty branch —
        // the empty branch is the easy one, and the one the other tests cover.
        $member = Member::factory()->for($church, 'church')->create();
        $dept = Department::create(['church_id' => $church->id, 'name' => 'Ushering']);
        $member->departments()->attach($dept);
        $family = Family::create(['church_id' => $church->id, 'name' => 'The Adeyemis']);
        $group = Group::create(['church_id' => $church->id, 'name' => 'Choir']);

        Event::create([
            'church_id' => $church->id, 'title' => 'Sunday Service',
            'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHours(2),
            'capacity' => 100,
        ]);
        $session = \App\Models\AttendanceSession::create([
            'church_id' => $church->id, 'name' => 'Sunday First Service',
            'type' => 'service', 'session_date' => now()->toDateString(),
        ]);
        $session->records()->create([
            'church_id' => $church->id, 'member_id' => $member->id,
            'status' => 'present', 'recorded_by' => $admin->id,
        ]);
        Announcement::create([
            'church_id' => $church->id, 'title' => 'Notice', 'body' => 'Body',
            'audience_type' => 'church_wide', 'created_by' => $admin->id,
        ]);
        Task::create([
            'church_id' => $church->id, 'title' => 'Fix the roof',
            'status' => 'pending', 'due_date' => now()->addDays(3),
        ]);
        $account = FinancialAccount::factory()->for($church, 'church')->create();
        Budget::create([
            'church_id' => $church->id, 'financial_account_id' => $account->id,
            'name' => 'Q1', 'period_start' => now()->startOfMonth(), 'period_end' => now()->endOfMonth(),
        ]);
        Loan::create([
            'church_id' => $church->id, 'member_id' => $member->id,
            'principal_amount' => '1000.00', 'monthly_deduction' => '100.00',
        ]);

        $ruleSet = SubventionRuleSet::factory()->for($church, 'church')->create();
        $period = SubventionPeriod::factory()->for($church, 'church')->create();
        $unitType = \App\Models\UnitType::create(['church_id' => $church->id, 'name' => 'Province', 'level' => 0]);
        $branch = \App\Models\OrganizationalUnit::create([
            'church_id' => $church->id, 'unit_type_id' => $unitType->id, 'name' => 'Test Branch',
        ]);
        SubventionSubmission::create([
            'church_id' => $church->id, 'subvention_period_id' => $period->id,
            'organizational_unit_id' => $branch->id,
            'subvention_rule_set_id' => $ruleSet->id,
            'figures' => ['general_tithe' => '100', 'salary' => '0'],
        ]);

        PastoralCase::create([
            'church_id' => $church->id, 'member_id' => $member->id,
            'type' => 'counseling', 'assigned_to' => $admin->id, 'opened_by' => $admin->id,
        ]);
        PrayerRequest::create([
            'church_id' => $church->id, 'request' => 'Pray for us',
            'submitted_by_name' => 'A visitor', 'assigned_to' => $admin->id,
        ]);
        Appointment::create([
            'church_id' => $church->id, 'pastor_id' => $admin->id,
            'title' => 'Committee meeting', 'scheduled_at' => now()->addDay(),
        ]);

        SmsWallet::withoutGlobalScopes()->create(['church_id' => $church->id, 'balance_units' => 100]);
        SmsCampaign::factory()->for($church, 'church')->create(['created_by' => $admin->id]);

        // Phase 9: the only swept GET tour route is tours.should-show, which
        // looks a tour up by slug. Without a matching row it correctly 404s, so
        // the sweep needs the same 'welcome' tour TourSeeder ships.
        $tour = \App\Models\Tour::create([
            'slug' => 'welcome', 'name' => 'Welcome tour',
            'version' => 1, 'is_active' => true,
        ]);
        $tour->steps()->create([
            'title' => 'Dashboard', 'description' => 'Your overview',
            'target_selector' => '#dashboard', 'sort_order' => 1, 'is_enabled' => true,
        ]);

        $this->actingAs($admin)->get('/dashboard')->assertOk();

        // Every named GET route, walked rather than listed by hand, so a route
        // added in a later phase is picked up automatically.
        $skip = [
            'home', 'features', 'pricing', 'about', 'demo', 'contact', 'support',
            'resources.blog', 'resources.help-center', 'resources.guides',
            'legal.privacy', 'legal.terms', 'login', 'register', 'logout',
            'platform-admin.dashboard', 'dashboard',
            // Requires a ?plan= query parameter; with none it redirects back to
            // billing by design, so a 302 here is correct rather than a failure.
            'billing.change-plan.preview',
        ];

        $checked = 0;
        $failures = [];

        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name || in_array($name, $skip, true)) {
                continue;
            }

            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            // Only routes inside the auth+IdentifyTenant group are in scope.
            if (! in_array(\App\Http\Middleware\IdentifyTenant::class, $route->gatherMiddleware(), true)) {
                continue;
            }

            $uri = '/'.ltrim(preg_replace('/\{[^}]+\}/', $this->sampleIdFor($route), $route->uri()), '/');

            $status = $this->actingAs($admin)->get($uri)->getStatusCode();

            if ($status !== 200) {
                $failures[] = "{$name} ({$uri}) -> {$status}";
            }

            $checked++;
        }

        $this->assertSame([], $failures, "These GET pages did not return 200:\n".implode("\n", $failures));
        $this->assertGreaterThan(20, $checked, 'The sweep should have covered most of the route table.');
        fwrite(STDERR, "\n[smoke] swept {$checked} authenticated GET pages\n");
    }

    /** A real row id for the route's bound model, so route-model binding resolves. */
    private function sampleIdFor(\Illuminate\Routing\Route $route): string
    {
        $first = null;
        foreach ($route->parameterNames() as $parameter) {
            $first = $parameter;
            break;
        }

        return match ($first) {
            'member' => (string) Member::query()->value('id'),            'family' => (string) Family::query()->value('id'),
            'department' => (string) Department::query()->value('id'),
            'group' => (string) Group::query()->value('id'),
            'event' => (string) Event::query()->value('id'),
            'session' => (string) (\App\Models\AttendanceSession::query()->value('id') ?? 1),
            'announcement' => (string) Announcement::query()->value('id'),
            'account' => (string) FinancialAccount::query()->value('id'),
            'budget' => (string) Budget::query()->value('id'),
            'loan' => (string) Loan::query()->value('id'),
            'ruleSet' => (string) SubventionRuleSet::query()->value('id'),
            'submission' => (string) SubventionSubmission::query()->value('id'),
            'prayerRequest' => (string) PrayerRequest::query()->value('id'),
            'case' => (string) PastoralCase::query()->value('id'),
            'appointment' => (string) Appointment::query()->value('id'),
            'campaign' => (string) SmsCampaign::query()->value('id'),
            'notification' => '1',
            'transaction' => '1',
            'approval' => '1',
            // Tours bind on {slug}, not id — substituting '1' produced a 404
            // that looked like a broken page but was only a wrong sample value.
            'slug' => (string) (\App\Models\Tour::query()->value('slug') ?? 'welcome'),
            default => '1',
        };
    }
}

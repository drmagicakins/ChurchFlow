<?php

namespace Tests\Feature;

use App\Domains\Subscriptions\Services\CheckoutService;
use App\Domains\Subscriptions\Services\PaymentWebhookHandler;
use App\Models\AttendanceSession;
use App\Models\Church;
use App\Models\Event;
use App\Models\FinancialAccount;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Starter plan on a REAL 14-day trial, driven the way a brand-new church
 * actually arrives: a user registers, picks Starter at checkout, the webhook
 * starts the trial, and THEN they use the app.
 *
 * `StarterPlanFeaturesTest` hand-builds an owner by seeding the role tables
 * and attaching the Church Owner role itself. That is fine for testing a
 * policy, but it silently assumes the signup path produced the right owner —
 * and the signup path is exactly where an entitlement bug hides. This test
 * builds the tenant through `ActivateChurchFromCheckout` and asserts the
 * features against what that actually created, with no manual role wiring.
 *
 * SMS is deliberately OUT of scope: it is billed separately by credits on
 * every plan (config/marketing.php 'billing_notes'), so a Starter trial is
 * expected to reach the campaign screen and to be told it needs credits —
 * not to send for free.
 */
class StarterTrialJourneyTest extends TestCase
{
    use RefreshDatabase;

    /** A new customer on a Starter trial, created by the real checkout path. */
    private function newStarterTrial(): array
    {
        $plan = Plan::factory()->create([
            'name' => 'Starter',
            'slug' => 'starter',
            'monthly_price' => 10000,
            'yearly_price' => 100000,
            'max_members' => 200,
            'max_branches' => 1,
            'max_admins' => 3,
            'features' => ['api_access' => false, 'custom_domain' => false, 'advanced_reports' => false],
        ]);

        $user = User::factory()->create(['church_id' => null]);

        [$checkout] = app(CheckoutService::class)->startSubscriptionCheckout($user, $plan, 'monthly');

        app(PaymentWebhookHandler::class)->handle([
            'event' => 'payment.success',
            'provider' => 'null',
            'provider_event_id' => 'starter-trial-1',
            'reference' => $checkout->provider_reference,
        ]);

        $church = Church::withoutGlobalScopes()->findOrFail($user->fresh()->church_id);

        // Roles carry their own tenant scope, so a role lookup only sees the
        // church's roles once a tenant is bound — exactly what IdentifyTenant
        // does on a real request. Without this, `$user->roles` is empty and
        // every permission check is false, which is a TEST artefact and not an
        // app defect. All feature assertions below therefore bind it first;
        // the assertions that matter most do so through real HTTP requests,
        // where the middleware binds it on its own.
        app()->instance('tenant.church_id', $church->id);

        return [$church, $user->fresh(), $plan];
    }

    public function test_the_signup_path_produces_a_14_day_starter_trial(): void
    {
        [$church, , $plan] = $this->newStarterTrial();

        $subscription = Subscription::withoutGlobalScopes()->where('church_id', $church->id)->latest()->firstOrFail();

        $this->assertSame('trialing', $subscription->status);
        $this->assertSame($plan->id, $subscription->plan_id, 'The trial runs on the plan that was chosen at checkout.');
        $this->assertSame('trial', $church->status);
        $this->assertSame(
            14,
            (int) now()->startOfDay()->diffInDays($subscription->trial_ends_at->startOfDay()),
            'A new church must get the full 14 days.'
        );
    }

    public function test_a_new_trial_owner_holds_the_permissions_every_starter_feature_needs(): void
    {
        // Binding the tenant happens inside newStarterTrial(), because that is
        // what a real request does before any permission is checked.
        [$church, $user] = $this->newStarterTrial();
        $user = $user->fresh();
        app()->instance('tenant.church_id', $church->id);
        $user->load('roles.permissions');

        // Proven against the real account created by signup, not a fixture.
        $this->assertTrue($user->isRegistrar(), 'The account that registers the church is its owner.');

        foreach (['members.view', 'members.create', 'families.manage', 'groups.manage',
            'events.manage', 'attendance.manage', 'finance.view', 'finance.record',
            'billing.manage', 'settings.manage'] as $permission) {
            $this->assertTrue(
                $user->hasPermission($permission),
                "A new Starter trial owner must hold {$permission} — the pricing page sells that capability."
            );
        }
    }

    public function test_a_new_trial_church_can_use_members_families_and_groups(): void
    {
        [, $user] = $this->newStarterTrial();

        $this->actingAs($user)->get('/members')->assertOk();
        $this->actingAs($user)->get('/families')->assertOk();
        $this->actingAs($user)->get('/groups')->assertOk();

        $this->actingAs($user)
            ->post('/members', ['full_name' => 'Grace Adeyemi', 'email' => 'grace@example.test'])
            ->assertRedirect();

        $this->actingAs($user)
            ->post('/groups', ['name' => 'Youth Fellowship'])
            ->assertRedirect();

        $this->assertSame(1, \App\Models\Member::withoutGlobalScopes()->where('church_id', $user->church_id)->count());
        $this->assertSame(1, \App\Models\Group::withoutGlobalScopes()->where('church_id', $user->church_id)->count());
    }

    public function test_a_new_trial_church_can_create_events_and_take_attendance(): void
    {
        [, $user] = $this->newStarterTrial();
        app()->instance('tenant.church_id', $user->church_id);

        $this->actingAs($user)
            ->post('/events', [
                'title' => 'Sunday Celebration Service',
                'starts_at' => now()->addDays(2)->toDateTimeString(),
                'venue' => 'Main Auditorium',
            ])
            ->assertRedirect();

        $event = Event::withoutGlobalScopes()->where('church_id', $user->church_id)->firstOrFail();
        $this->actingAs($user)->get("/events/{$event->id}")->assertOk()->assertSee('Sunday Celebration Service');

        $this->actingAs($user)
            ->post('/attendance', [
                'name' => 'Sunday First Service',
                'type' => 'service',
                'session_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $session = AttendanceSession::withoutGlobalScopes()->where('church_id', $user->church_id)->firstOrFail();
        $this->actingAs($user)->get("/attendance/{$session->id}")->assertOk();
    }

    public function test_a_new_trial_church_can_record_income_and_expenses(): void
    {
        [, $user] = $this->newStarterTrial();
        app()->instance('tenant.church_id', $user->church_id);

        $this->actingAs($user)
            ->post('/finance/accounts', ['name' => 'Main Offering', 'type' => 'general'])
            ->assertRedirect();

        $account = FinancialAccount::withoutGlobalScopes()->where('church_id', $user->church_id)->firstOrFail();

        $this->actingAs($user)
            ->post("/finance/accounts/{$account->id}/income", [
                'category' => 'Offering', 'amount' => 25000, 'transacted_on' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post("/finance/accounts/{$account->id}/expense", [
                'category' => 'Utilities', 'amount' => 4000, 'transacted_on' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(1, Transaction::withoutGlobalScopes()->where('church_id', $user->church_id)->where('type', 'income')->count());
        $this->assertSame(1, Transaction::withoutGlobalScopes()->where('church_id', $user->church_id)->where('type', 'expense')->count());

        $this->actingAs($user)->get('/finance/accounts')->assertOk();
    }

    public function test_a_new_trial_church_can_create_tasks(): void
    {
        [, $user] = $this->newStarterTrial();

        $this->actingAs($user)
            ->post('/tasks', ['title' => 'Call the visitors from Sunday'])
            ->assertRedirect();

        $this->assertSame(1, Task::withoutGlobalScopes()->where('church_id', $user->church_id)->count());
    }

    public function test_a_new_trial_church_has_email_notifications_and_standard_support(): void
    {
        [, $user] = $this->newStarterTrial();

        $this->actingAs($user)->get('/notifications')->assertOk();
        $this->actingAs($user)->get('/support')->assertOk();
    }

    public function test_a_new_trial_church_can_upgrade_its_plan(): void
    {
        [$church, $user] = $this->newStarterTrial();
        app()->instance('tenant.church_id', $church->id);

        $growth = Plan::factory()->create([
            'name' => 'Growth', 'slug' => 'growth', 'monthly_price' => 25000,
        ]);

        $this->actingAs($user)->get(route('billing.plans'))->assertOk();

        $this->actingAs($user)
            ->post(route('billing.change-plan'), ['plan_id' => $growth->id])
            ->assertRedirect(route('billing.show'));

        // During a trial the choice is recorded, not applied — that is the
        // documented Phase 11 rule (see BillingController's docblock).
        $subscription = Subscription::withoutGlobalScopes()->where('church_id', $church->id)->latest()->firstOrFail();
        $this->assertSame($growth->id, $subscription->pending_plan_id);
    }

    public function test_the_starter_trial_reaches_every_module_page_without_an_authorization_error(): void
    {
        [, $user] = $this->newStarterTrial();

        foreach ([
            '/dashboard', '/members', '/families', '/groups', '/events', '/attendance',
            '/tasks', '/finance/accounts', '/billing', '/billing/plans', '/notifications', '/support',
        ] as $uri) {
            $this->actingAs($user)->get($uri)->assertOk();
        }
    }

    /**
     * SMS is explicitly out of scope for this task: it is pay-as-you-go credit
     * on every plan. A Starter trial must still be able to OPEN the wallet —
     * it is where credits are bought — and must not be told the plan lacks it.
     */
    public function test_sms_stays_available_to_buy_credits_on_the_trial_rather_than_being_blocked(): void
    {
        [, $user] = $this->newStarterTrial();

        $this->actingAs($user)->get(route('sms.wallet.show'))->assertOk();
    }

    /**
     * The defect this pass exists for: signup could produce an owner holding
     * ZERO permissions, which refuses every Starter feature to the only person
     * who could fix it. Two churches in the dev database were left that way.
     *
     * The cause was the clone being best-effort — if the system-default
     * "Church Owner" role was missing, `if ($systemOwner)` silently cloned
     * nothing and signup still reported success.
     */
    public function test_signup_repairs_a_missing_permission_catalogue_instead_of_creating_a_powerless_owner(): void
    {
        $this->seed(RolePermissionSeeder::class);

        // Simulate the deployment fault: the catalogue's default role is gone.
        Role::withoutGlobalScopes()->whereNull('church_id')->where('name', 'Church Owner')->delete();
        $this->assertNull(
            Role::withoutGlobalScopes()->whereNull('church_id')->where('name', 'Church Owner')->first(),
            'Precondition: the system-default owner role is missing.'
        );

        $plan = Plan::factory()->create(['name' => 'Starter', 'slug' => 'starter', 'monthly_price' => 10000]);
        $user = User::factory()->create(['church_id' => null]);
        [$checkout] = app(CheckoutService::class)->startSubscriptionCheckout($user, $plan, 'monthly');

        app(PaymentWebhookHandler::class)->handle([
            'event' => 'payment.success', 'provider' => 'null',
            'provider_event_id' => 'starter-trial-repair-1',
            'reference' => $checkout->provider_reference,
        ]);

        $user = $user->fresh();
        $church = Church::withoutGlobalScopes()->findOrFail($user->church_id);
        app()->instance('tenant.church_id', $church->id);

        $ownerRole = Role::withoutGlobalScopes()
            ->where('church_id', $church->id)->where('name', 'Church Owner')->firstOrFail();

        $this->assertGreaterThan(0, $ownerRole->permissions()->count(), 'The new owner role must never be empty.');
        $this->assertTrue($user->fresh()->hasPermission('members.view'));
        $this->assertTrue($user->fresh()->hasPermission('events.manage'));
        $this->assertTrue($user->fresh()->hasPermission('attendance.manage'));
        $this->assertTrue($user->fresh()->hasPermission('finance.record'));
        $this->assertTrue($user->fresh()->hasPermission('billing.manage'));
    }
}

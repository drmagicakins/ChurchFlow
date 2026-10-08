<?php

namespace Tests\Feature;

use App\Domains\Subscriptions\Actions\ActivateChurchFromCheckout;
use App\Models\Checkout;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the "a real church owner actually receives
 * permissions" path.
 *
 * HISTORY, because this file has now moved twice and the reason matters:
 *
 * MemberManagementTest builds its users' permissions by hand
 * (userWithPermissions()), so it could never catch a bug in how a *real*
 * account receives permissions. Phase 2's RegisterController was doing
 * exactly that: it created a church-scoped "Church Owner" role with no
 * permissions attached, so every gated page 403'd for a genuinely new
 * account while the suite stayed green. The Phase 2 fix was to CLONE the
 * system-default role's permissions into the church's own copy.
 *
 * Phase 8 then moved church creation out of registration entirely — a
 * church now only exists after a verified payment, via
 * ActivateChurchFromCheckout. The clone moved WITH it (that file is the one
 * remaining place a church is created), and these tests were repointed at
 * the new flow rather than deleted. The assertion that matters is
 * unchanged: a brand-new church's owner holds a role with real permissions
 * attached, not an empty one.
 */
class RegistrationPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The system-default role the seeder creates is what gets cloned.
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    /** Registers (no church) and pays, so the owner ends up fully activated. */
    private function registerAndPay(string $ownerName, string $email): User
    {
        $this->post('/register', [
            'name' => $ownerName,
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('plans.index'));

        $owner = User::where('email', $email)->firstOrFail();

        $plan = Plan::create([
            'name' => 'Growth', 'slug' => 'growth-'.$owner->id,
            'monthly_price' => 25000, 'yearly_price' => 250000, 'currency' => 'NGN',
            'max_members' => 1000, 'max_branches' => 5, 'max_admins' => 10, 'storage_mb' => 10240,
        ]);

        $checkout = Checkout::create([
            'user_id' => $owner->id,
            'plan_id' => $plan->id,
            'purpose' => 'subscription',
            'billing_interval' => 'monthly',
            'amount' => 25000,
            'currency' => 'NGN',
            'provider' => 'paystack',
            'provider_reference' => 'REF-'.$owner->id,
            'status' => 'pending',
        ]);

        app(ActivateChurchFromCheckout::class)->handle($checkout);

        $owner->refresh();

        // ActivateChurchFromCheckout creates the church but the Role model's
        // global scope only shows a church-scoped role when a tenant is bound
        // (`church_id = ? OR church_id IS NULL`). Without this the eager load
        // below silently returns nothing and the assertions read an owner with
        // no roles — the same trap Phase 7's HTTP test documents. A real
        // request has this bound by IdentifyTenant.
        app()->instance('tenant.church_id', $owner->church_id);
        $owner->load('roles.permissions');

        return $owner;
    }

    public function test_a_newly_paid_owner_receives_the_church_owner_role(): void
    {
        $owner = $this->registerAndPay('Test Owner', 'owner@example.com');

        $this->assertNotNull($owner->church_id, 'Paying must attach the user to a church.');
        $this->assertSame(
            ['Church Owner'],
            $owner->roles->pluck('name')->all(),
            'An activated owner must be attached to a Church Owner role.'
        );
    }

    public function test_the_cloned_owner_role_carries_the_default_permissions(): void
    {
        $owner = $this->registerAndPay('Test Owner', 'owner@example.com');

        $churchScopedRole = Role::query()
            ->withoutGlobalScopes()
            ->where('church_id', $owner->church_id)
            ->where('name', 'Church Owner')
            ->firstOrFail();

        $this->assertTrue(
            $churchScopedRole->permissions()->exists(),
            'The church-scoped Church Owner role must be cloned with permissions, not created empty.'
        );

        // The permission set must match the system default it was cloned from.
        $default = Role::query()
            ->withoutGlobalScopes()
            ->whereNull('church_id')
            ->where('name', 'Church Owner')
            ->firstOrFail();

        $this->assertEqualsCanonicalizing(
            $default->permissions()->pluck('permissions.id')->all(),
            $churchScopedRole->permissions()->pluck('permissions.id')->all()
        );
    }

    public function test_an_activated_owner_can_actually_open_the_people_pages(): void
    {
        $owner = $this->registerAndPay('Test Owner', 'owner@example.com');

        // These are the pages that 403'd before the clone was implemented.
        $this->actingAs($owner)->get(route('members.index'))->assertOk();
        $this->actingAs($owner)->get(route('families.index'))->assertOk();
        $this->actingAs($owner)->get(route('departments.index'))->assertOk();
        $this->actingAs($owner)->get(route('groups.index'))->assertOk();
    }

    public function test_each_activated_church_gets_its_own_owner_role(): void
    {
        $ownerA = $this->registerAndPay('Owner A', 'a@example.com');

        // /register sits behind the 'guest' middleware, so the first registrant
        // has to be fully signed out before a second can register in the same
        // test. Clearing the guard alone is not enough: the session still holds
        // user A, so the guest middleware bounces the next POST /register to
        // /dashboard instead of letting it through. Drop the session too, and
        // the tenant binding from registerAndPay with it, or the second call
        // would keep seeing church A's roles.
        $this->post('/logout');
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->app->forgetInstance('tenant.church_id');

        $ownerB = $this->registerAndPay('Owner B', 'b@example.com');

        $this->assertNotSame($ownerA->church_id, $ownerB->church_id);

        $roleA = Role::query()->withoutGlobalScopes()
            ->where('church_id', $ownerA->church_id)->where('name', 'Church Owner')->firstOrFail();
        $roleB = Role::query()->withoutGlobalScopes()
            ->where('church_id', $ownerB->church_id)->where('name', 'Church Owner')->firstOrFail();

        $this->assertNotSame($roleA->id, $roleB->id);
        $this->assertTrue($roleA->permissions()->exists());
        $this->assertTrue($roleB->permissions()->exists());
    }
}

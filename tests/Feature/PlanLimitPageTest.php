<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\OrganizationalUnit;
use App\Models\Plan;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Domains\Subscriptions\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reported symptom: on the Starter plan, creating a branch says
 *
 *   "You've reached the branches limit for your current plan.
 *    Current: 1 / 1 branches. Upgrade your plan to add more branches."
 *
 * and that message arrived as a bare browser error document ("An Error
 * Occurred: Unprocessable Content") with no way to upgrade.
 *
 * The limit is correct, and it is kept: Starter genuinely allows one branch.
 * What is asserted here is that the refusal is a PAGE of its own that states
 * the limit and offers the upgrade — and that nothing else about the plan
 * limit changed (nothing is created, other limits still behave).
 */
class PlanLimitPageTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::factory()->create();
        app()->instance('tenant.church_id', $this->church->id);

        // Exactly what a Starter signup produces: the plan, the subscription,
        // and the one "Headquarters" branch ActivateChurchFromCheckout creates.
        $plan = Plan::create([
            'name' => 'Starter', 'slug' => 'starter',
            'monthly_price' => 10000, 'yearly_price' => 100000, 'currency' => 'NGN',
            'max_members' => 200, 'max_branches' => 1, 'max_admins' => 3, 'storage_mb' => 1024,
            'features' => ['api_access' => false, 'custom_domain' => false, 'advanced_reports' => false],
        ]);

        app(SubscriptionService::class)->createInitial($this->church, $plan, 'monthly');

        $type = UnitType::create(['church_id' => $this->church->id, 'name' => 'Headquarters', 'level' => 0]);
        OrganizationalUnit::create([
            'church_id' => $this->church->id,
            'unit_type_id' => $type->id,
            'name' => $this->church->name,
        ]);

        $this->owner = $this->userWith(['members.view', 'settings.manage', 'billing.manage']);
    }

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create(['church_id' => $this->church->id]);

        $ids = collect($permissions)->map(
            fn ($n) => \App\Models\Permission::firstOrCreate(['name' => $n], ['group' => 'x', 'label' => $n])->id
        );
        $role = Role::create(['church_id' => $this->church->id, 'name' => 'Owner '.uniqid()]);
        $role->permissions()->attach($ids);
        $user->roles()->attach($role);

        return $user->load('roles.permissions');
    }

    public function test_the_starter_branch_limit_shows_its_own_page_with_the_upgrade_path(): void
    {
        $response = $this->actingAs($this->owner)->post('/branches', ['name' => 'Ikeja']);

        $response->assertStatus(422);

        $html = $response->getContent();

        // The operator's own sentence, verbatim (the apostrophe is escaped by
        // Blade in the HTML, which is correct).
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES);

        $this->assertStringContainsString(
            "You've reached the branches limit for your current plan. Current: 1 / 1 branches. Upgrade your plan to add more branches.",
            $text
        );

        // A page, not a browser error document.
        $this->assertStringContainsString('Your plan limit has been reached', $html);
        $this->assertStringContainsString('Nothing was saved', $html);
        $this->assertStringContainsString('View plans and upgrade', $html);
        $this->assertStringContainsString('href="'.route('billing.show').'"', $html);

        // And not the framework's own error furniture.
        $this->assertStringNotContainsString('Unprocessable Content', $html);
        $this->assertStringNotContainsString('No routing parameters', $html);

        // Nothing was written.
        $this->assertDatabaseMissing('organizational_units', ['name' => 'Ikeja']);
    }

    public function test_the_limit_is_still_enforced_exactly_as_before(): void
    {
        $this->actingAs($this->owner)->post('/branches', ['name' => 'Ikeja'])->assertStatus(422);

        $this->assertSame(1, OrganizationalUnit::where('church_id', $this->church->id)->count());
    }

    public function test_upgrading_lifts_the_limit_on_the_same_page(): void
    {
        $this->actingAs($this->owner)->post('/branches', ['name' => 'Ikeja'])->assertStatus(422);

        $larger = Plan::create([
            'name' => 'Growth', 'slug' => 'growth',
            'monthly_price' => 25000, 'yearly_price' => 250000, 'currency' => 'NGN',
            'max_members' => 1000, 'max_branches' => 5, 'max_admins' => 10, 'storage_mb' => 10240,
            'features' => ['api_access' => false, 'custom_domain' => false, 'advanced_reports' => true],
        ]);

        // The real upgrade path: the church's existing subscription moves to the
        // larger plan (changePlan), which is what the billing page does.
        $service = app(SubscriptionService::class);
        $service->changePlan(\App\Models\Subscription::withoutGlobalScopes()
            ->where('church_id', $this->church->id)->latest()->firstOrFail(), $larger);

        $this->actingAs($this->owner->fresh())
            ->post('/branches', ['name' => 'Ikeja'])
            ->assertRedirect(route('branches.index'));

        $this->assertDatabaseHas('organizational_units', ['name' => 'Ikeja']);
    }

    /**
     * A form with a missing field is still an ordinary validation error that
     * goes back to the form — never this page.
     */
    public function test_a_validation_error_is_not_turned_into_the_limit_page(): void
    {
        $response = $this->actingAs($this->owner)->from('/branches')->post('/branches', ['name' => '']);

        $response->assertRedirect('/branches');
        $response->assertSessionHasErrors('name');

        $this->get('/branches')->assertOk()->assertDontSee('Your plan limit has been reached', false);
    }

    /**
     * API / fetch() callers keep the JSON contract with the same message.
     */
    public function test_json_callers_still_receive_the_json_message(): void
    {
        $response = $this->actingAs($this->owner)->postJson('/branches', ['name' => 'Ikeja']);

        $response->assertStatus(422);
        $this->assertStringContainsString('branches limit', $response->getContent());
        $this->assertStringNotContainsString('Your plan limit has been reached', $response->getContent());
    }
}

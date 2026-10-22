<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SubventionRuleSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Creating and UPDATING a subvention rule set from the dashboard.
 *
 * The policy allowed update() from the start, but there was no route and no
 * form, so a rule set was frozen the moment it was created. These tests cover
 * the create and edit paths a church administrator now has.
 */
class RuleSetManagementTest extends TestCase
{
    use RefreshDatabase;

    private function manager(Church $church): User
    {
        $ids = collect(['subvention.manage', 'subvention.submit'])->map(
            fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'x', 'label' => $n])->id
        );
        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::create(['church_id' => $church->id, 'name' => 'Subvention Manager '.uniqid()]);
        $role->permissions()->attach($ids);
        $user->roles()->attach($role);

        return $user;
    }

    private function rulePayload(string $name = 'RCCG Group 1'): array
    {
        return [
            'name' => $name,
            'rules' => [
                [
                    'name' => 'General Tithe Retention', 'base_field' => 'general_tithe',
                    'type' => 'percentage', 'rate' => 25, 'classification' => 'retention',
                ],
                [
                    'name' => 'Salary', 'base_field' => 'salary',
                    'type' => 'percentage', 'rate' => 100, 'classification' => 'deduction',
                ],
            ],
        ];
    }

    public function test_the_rule_sets_index_shows_the_create_form_to_a_manager(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $this->actingAs($this->manager($church))
            ->get(route('subvention.rule-sets.index'))
            ->assertOk()
            ->assertSee('Create a rule set')
            ->assertSee('New rule set')
            ->assertSee('Add rule');
    }

    public function test_a_manager_can_create_a_rule_set_with_rules(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $this->actingAs($this->manager($church))
            ->post(route('subvention.rule-sets.store'), $this->rulePayload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $ruleSet = SubventionRuleSet::where('church_id', $church->id)->where('name', 'RCCG Group 1')->first();
        $this->assertNotNull($ruleSet);
        $this->assertSame(2, $ruleSet->rules()->count());
        $this->assertSame(25.0, (float) $ruleSet->rules()->where('classification', 'retention')->first()->rate);
    }

    public function test_a_rule_set_requires_at_least_one_rule(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $this->actingAs($this->manager($church))
            ->post(route('subvention.rule-sets.store'), ['name' => 'Empty', 'rules' => []])
            ->assertSessionHasErrors('rules');
    }

    public function test_a_manager_can_update_a_rule_set_and_its_rules(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $manager = $this->manager($church);
        $ruleSet = SubventionRuleSet::factory()->for($church, 'church')->create(['name' => 'Old Name']);
        $ruleSet->rules()->create([
            'name' => 'Stale Rule', 'base_field' => 'general_tithe',
            'type' => 'percentage', 'rate' => 10, 'classification' => 'retention', 'sort_order' => 0,
        ]);

        $this->actingAs($manager)
            ->put(route('subvention.rule-sets.update', $ruleSet), [
                'name' => 'RCCG Group 2',
                'rules' => [
                    [
                        'name' => 'General Tithe Retention', 'base_field' => 'general_tithe',
                        'type' => 'percentage', 'rate' => 20, 'classification' => 'retention',
                    ],
                    [
                        'name' => 'Administrative Fee', 'base_field' => 'admin_base',
                        'type' => 'capped_percentage', 'rate' => 5, 'cap_amount' => 4000, 'classification' => 'deduction',
                    ],
                ],
            ])
            ->assertRedirect(route('subvention.rule-sets.show', $ruleSet))
            ->assertSessionHasNoErrors();

        $ruleSet->refresh();
        $this->assertSame('RCCG Group 2', $ruleSet->name);
        // The removed rule is gone (wholesale replacement), the two new ones exist.
        $this->assertSame(2, $ruleSet->rules()->count());
        $this->assertNull($ruleSet->rules()->where('name', 'Stale Rule')->first());
        $this->assertSame(20.0, (float) $ruleSet->rules()->where('name', 'General Tithe Retention')->first()->rate);
        $this->assertSame(4000.0, (float) $ruleSet->rules()->where('name', 'Administrative Fee')->first()->cap_amount);
    }

    public function test_the_rule_set_show_page_offers_the_edit_form(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $manager = $this->manager($church);
        $ruleSet = SubventionRuleSet::factory()->for($church, 'church')->create(['name' => 'Editable Set']);
        $ruleSet->rules()->create([
            'name' => 'General Tithe Retention', 'base_field' => 'general_tithe',
            'type' => 'percentage', 'rate' => 25, 'classification' => 'retention', 'sort_order' => 0,
        ]);

        $this->actingAs($manager)
            ->get(route('subvention.rule-sets.show', $ruleSet))
            ->assertOk()
            ->assertSee('Edit rules')
            ->assertSee('Save changes');
    }

    public function test_a_user_without_manage_permission_cannot_update_a_rule_set(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $submitterIds = collect(['subvention.submit'])->map(
            fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'x', 'label' => $n])->id
        );
        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::create(['church_id' => $church->id, 'name' => 'Submitter '.uniqid()]);
        $role->permissions()->attach($submitterIds);
        $user->roles()->attach($role);

        $ruleSet = SubventionRuleSet::factory()->for($church, 'church')->create();

        $this->actingAs($user)
            ->put(route('subvention.rule-sets.update', $ruleSet), $this->rulePayload('Hacked'))
            ->assertForbidden();

        $this->assertNotSame('Hacked', $ruleSet->fresh()->name);
    }

    public function test_a_manager_cannot_update_another_churchs_rule_set(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        app()->instance('tenant.church_id', $churchA->id);
        $manager = $this->manager($churchA);

        // A rule set that belongs to church B.
        app()->instance('tenant.church_id', $churchB->id);
        $foreign = SubventionRuleSet::factory()->for($churchB, 'church')->create(['name' => 'B Set']);

        app()->instance('tenant.church_id', $churchA->id);
        // 404, not 403: the tenant scope hides church B's rule set from church
        // A entirely, so route-model binding finds nothing to authorize. That
        // is the stronger answer — a 403 would confirm the record exists.
        $this->actingAs($manager)
            ->put(route('subvention.rule-sets.update', $foreign), $this->rulePayload('Stolen'))
            ->assertNotFound();

        app()->instance('tenant.church_id', $churchB->id);
        $this->assertSame('B Set', $foreign->fresh()->name);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Member;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * IdentifyTenant was not in Laravel's default middleware priority list, so it
 * ran AFTER SubstituteBindings — route-model binding resolved {member} before
 * app('tenant.church_id') was set, so BelongsToTenant's global scope filtered
 * out every implicitly-bound model. Every show/update/destroy route 404'd for
 * every resource in the app, for every tenant, always — not a Members-grid bug.
 * Locking in the fix (bootstrap/app.php: prependToPriorityList).
 */
class RouteModelBindingTenantTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(Church $church, array $permissions): User
    {
        $user = User::factory()->create(['church_id' => $church->id]);
        $ids = collect($permissions)->map(fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'x', 'label' => 'x'])->id);
        $role = Role::create(['church_id' => $church->id, 'name' => 'R']);
        $role->permissions()->attach($ids);
        $user->roles()->attach($role);

        return $user->load('roles.permissions');
    }

    public function test_viewing_a_route_model_bound_resource_succeeds_for_its_own_tenant(): void
    {
        $church = Church::factory()->create();
        $user = $this->userWith($church, ['members.view']);
        app()->instance('tenant.church_id', $church->id);
        $member = Member::factory()->for($church, 'church')->create();

        $this->actingAs($user)->get(route('members.show', $member))->assertOk();
    }

    public function test_a_route_model_bound_resource_from_another_tenant_404s_not_500s(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        $userA = $this->userWith($churchA, ['members.view']);

        app()->instance('tenant.church_id', $churchB->id);
        $memberB = Member::factory()->for($churchB, 'church')->create();

        $this->actingAs($userA)->get(route('members.show', $memberB))->assertNotFound();
    }
}

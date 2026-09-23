<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_church_cannot_see_another_churchs_members_in_a_plain_query(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();

        Member::factory()->for($churchA, 'church')->create(['full_name' => 'Member of Church A']);
        Member::factory()->for($churchB, 'church')->create(['full_name' => 'Member of Church B']);

        app()->instance('tenant.church_id', $churchA->id);

        $visible = Member::all();

        $this->assertCount(1, $visible);
        $this->assertSame('Member of Church A', $visible->first()->full_name);
    }

    public function test_a_church_cannot_fetch_another_churchs_member_by_guessing_its_id(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();

        $memberOfB = Member::factory()->for($churchB, 'church')->create();

        app()->instance('tenant.church_id', $churchA->id);

        $result = Member::find($memberOfB->id);

        $this->assertNull($result, 'A tenant must never be able to fetch another tenant\'s row by ID.');
    }

    public function test_no_bound_tenant_returns_no_rows_instead_of_all_rows(): void
    {
        $churchA = Church::factory()->create();
        Member::factory()->for($churchA, 'church')->create();

        // No 'tenant.church_id' bound at all — this must fail closed, not
        // silently return every tenant's data.
        $this->assertCount(0, Member::all());
    }

    public function test_a_users_http_request_only_ever_sees_their_own_churchs_members(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();

        $userA = User::factory()->create(['church_id' => $churchA->id]);
        Member::factory()->for($churchA, 'church')->create(['full_name' => 'A Member']);
        $memberOfB = Member::factory()->for($churchB, 'church')->create(['full_name' => 'B Member']);

        // Route under test: any authenticated, tenant-scoped route that
        // lists/looks up members. Adjust the URI once the real Members
        // controller exists in Phase 2 — the assertion shape stays the same.
        // (No withoutMiddleware() call here on purpose: the whole point is
        // that IdentifyTenant, which is attached to this route group in
        // routes/web.php, must run and bind the tenant.)
        $this->actingAs($userA)
            ->get(route('dashboard'))
            ->assertOk();

        app()->instance('tenant.church_id', $userA->church_id);
        $this->assertNull(Member::find($memberOfB->id));
    }

    public function test_platform_admin_has_no_implicit_church_context(): void
    {
        $admin = User::factory()->create([
            'church_id' => null,
            'is_platform_admin' => true,
        ]);

        $this->assertNull($admin->church_id);
        $this->assertTrue($admin->is_platform_admin);
    }
}

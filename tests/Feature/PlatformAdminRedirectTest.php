<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A platform admin has no church, so EVERY tenant-scoped route (members,
 * dashboard, finance, ...) is meaningless for them. IdentifyTenant currently
 * aborts those with a bare 403 — a raw error page that names an internal
 * concept ("implicit church context") the operator has no way to act on.
 *
 * The correct behaviour is a redirect to the platform-admin home: the operator
 * asked for a page that cannot exist for their account, and the useful answer
 * is "here is the area that is yours", not an error.
 */
class PlatformAdminRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_platform_admin_is_redirected_to_the_admin_home_from_a_tenant_route(): void
    {
        $admin = User::factory()->create(['church_id' => null, 'is_platform_admin' => true]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertRedirect(route('platform-admin.dashboard'));
    }

    public function test_a_platform_admin_is_redirected_from_other_tenant_routes(): void
    {
        $admin = User::factory()->create(['church_id' => null, 'is_platform_admin' => true]);

        foreach (['/members', '/events', '/finance/accounts', '/help'] as $uri) {
            $this->actingAs($admin)
                ->get($uri)
                ->assertRedirect(route('platform-admin.dashboard'));
        }
    }

    public function test_a_platform_admin_can_still_reach_the_platform_admin_area(): void
    {
        $admin = User::factory()->create(['church_id' => null, 'is_platform_admin' => true]);

        $this->actingAs($admin)->get(route('platform-admin.dashboard'))->assertOk();
    }

    public function test_an_api_caller_still_gets_a_json_403_not_a_redirect(): void
    {
        $admin = User::factory()->create(['church_id' => null, 'is_platform_admin' => true]);

        $this->actingAs($admin)
            ->getJson('/dashboard')
            ->assertForbidden();
    }
}

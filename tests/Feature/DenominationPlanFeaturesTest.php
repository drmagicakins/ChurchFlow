<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Church;
use App\Models\CustomDomain;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §14/§46: the two Denomination-plan features a church owner is sold —
 * API access and a custom domain — must be (a) reachable, (b) usable end to
 * end, and (c) refused for a plan that does not include them.
 *
 * All three of those were missing before this work: there was no screen for
 * either, and the API endpoints the controller provided were never routed, so
 * even a Denomination church with a key could not call them.
 */
class DenominationPlanFeaturesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Church, 1: User} */
    private function ownerOn(string $slug, array $features): array
    {
        $this->seed(RolePermissionSeeder::class);

        $church = Church::factory()->create(['status' => 'active']);
        app()->instance('tenant.church_id', $church->id);

        // Two churches in one test share a plan slug, so reuse the row rather
        // than violating the unique index.
        $plan = Plan::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($slug), 'features' => $features],
        );

        Subscription::create([
            'church_id' => $church->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->startOfMonth()->addMonth(),
        ]);

        $user = User::factory()->create(['church_id' => $church->id, 'is_registrar' => true]);
        $role = \App\Models\Role::firstOrCreate(['church_id' => null, 'name' => 'Church Owner']);
        $role->permissions()->sync(\App\Models\Permission::pluck('id'));
        $user->roles()->attach($role);

        return [$church, $user->fresh()];
    }

    private function denominationOwner(): array
    {
        return $this->ownerOn('denomination', [
            'api_access' => true, 'custom_domain' => true, 'advanced_reports' => true,
        ]);
    }

    private function starterOwner(): array
    {
        return $this->ownerOn('starter', [
            'api_access' => false, 'custom_domain' => false, 'advanced_reports' => false,
        ]);
    }

    // ---------------------------------------------------------------- API access

    public function test_a_denomination_owner_sees_api_access_in_the_menu(): void
    {
        [$church, $user] = $this->denominationOwner();

        app()->instance('tenant.church_id', $church->id);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('API access')
            ->assertSee('Custom domain');
    }

    public function test_a_starter_owner_does_not_see_the_developer_menu(): void
    {
        [$church, $user] = $this->starterOwner();

        app()->instance('tenant.church_id', $church->id);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Custom domain')
            ->assertDontSee('API access</a>', false);
    }

    public function test_the_owner_can_issue_a_key_and_it_authenticates_against_the_api(): void
    {
        [$church, $user] = $this->denominationOwner();
        Member::create([
            'church_id' => $church->id, 'full_name' => 'Ada Okonkwo', 'membership_status' => 'active',
        ]);

        app()->instance('tenant.church_id', $church->id);

        $this->actingAs($user)
            ->post(route('api-access.tokens.store'), ['name' => 'Website widget'])
            ->assertRedirect(route('api-access.index'));

        // The plaintext is flashed once and never stored.
        $plaintext = session('api_token_plaintext');
        $this->assertNotEmpty($plaintext);
        $this->assertStringStartsWith('cf_', $plaintext);

        $token = ApiToken::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('Website widget', $token->name);
        $this->assertNotSame($plaintext, $token->token_hash, 'Only the hash may be stored.');

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$plaintext])
            ->assertOk()
            ->assertJsonPath('data.church.id', $church->id);

        $this->getJson('/api/v1/members', ['Authorization' => 'Bearer '.$plaintext])
            ->assertOk()
            ->assertJsonPath('data.0.full_name', 'Ada Okonkwo');

        $this->assertNotNull($token->fresh()->last_used_at, 'Using a key records its last use.');
    }

    public function test_a_starter_owner_cannot_issue_a_key(): void
    {
        [$church, $user] = $this->starterOwner();
        app()->instance('tenant.church_id', $church->id);

        $this->actingAs($user)
            ->post(route('api-access.tokens.store'), ['name' => 'Nope'])
            ->assertForbidden();

        $this->assertSame(0, ApiToken::withoutGlobalScopes()->count());
    }

    public function test_a_key_from_a_starter_plan_is_refused_by_the_api(): void
    {
        [$church, $user] = $this->starterOwner();
        app()->instance('tenant.church_id', $church->id);

        // Even if a key somehow exists, the plan gate at the API itself refuses it.
        [$token, $plaintext] = ApiToken::issue($church, $user, 'Legacy');

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$plaintext])
            ->assertStatus(402);
    }

    public function test_revoking_a_key_stops_it_working_and_keeps_the_row(): void
    {
        [$church, $user] = $this->denominationOwner();
        app()->instance('tenant.church_id', $church->id);

        [$token, $plaintext] = ApiToken::issue($church, $user, 'To revoke');

        $this->actingAs($user)
            ->post(route('api-access.tokens.revoke', $token))
            ->assertRedirect();

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$plaintext])->assertStatus(401);

        $this->assertNotNull($token->fresh()->revoked_at);
        $this->assertSame(1, ApiToken::withoutGlobalScopes()->count(), 'Revoking must not delete the audit row.');
    }

    public function test_a_church_cannot_revoke_another_churchs_key(): void
    {
        [$churchA, $ownerA] = $this->denominationOwner();

        app()->instance('tenant.church_id', $churchA->id);
        [$tokenA] = ApiToken::issue($churchA, $ownerA, 'Church A key');

        $churchB = Church::factory()->create(['status' => 'active']);
        $ownerB = User::factory()->create(['church_id' => $churchB->id, 'is_registrar' => true]);

        app()->instance('tenant.church_id', $churchB->id);

        $this->actingAs($ownerB)
            ->post(route('api-access.tokens.revoke', $tokenA))
            ->assertNotFound();

        $this->assertNull($tokenA->fresh()->revoked_at);
    }

    // ------------------------------------------------------------ Custom domain

    public function test_the_owner_can_claim_and_release_a_domain(): void
    {
        [$church, $user] = $this->denominationOwner();
        app()->instance('tenant.church_id', $church->id);

        $this->actingAs($user)
            ->get(route('custom-domain.index'))
            ->assertOk()
            ->assertSee('Claim a domain');

        $this->actingAs($user)
            ->post(route('custom-domain.store'), ['hostname' => 'https://App.GraceChapel.org/'])
            ->assertRedirect(route('custom-domain.index'))
            ->assertSessionHas('status');

        // Normalised down to the bare hostname.
        $domain = CustomDomain::query()->firstOrFail();
        $this->assertSame('app.gracechapel.org', $domain->hostname);
        $this->assertSame('pending', $domain->status);

        // The page shows exactly the record the verifier will look for.
        $this->actingAs($user)
            ->get(route('custom-domain.index'))
            ->assertOk()
            ->assertSee('_churchflow.app.gracechapel.org')
            ->assertSee($domain->verification_token);

        $this->actingAs($user)
            ->delete(route('custom-domain.destroy'))
            ->assertRedirect();

        $this->assertSame(0, CustomDomain::query()->count());
    }

    public function test_a_starter_owner_cannot_claim_a_domain(): void
    {
        [$church, $user] = $this->starterOwner();
        app()->instance('tenant.church_id', $church->id);

        $this->actingAs($user)
            ->post(route('custom-domain.store'), ['hostname' => 'app.other.org'])
            ->assertForbidden();

        $this->assertSame(0, CustomDomain::query()->count());
    }

    public function test_a_domain_already_claimed_by_another_church_is_rejected(): void
    {
        [$churchA] = $this->denominationOwner();
        app()->instance('tenant.church_id', $churchA->id);
        CustomDomain::create([
            'church_id' => $churchA->id, 'hostname' => 'taken.org',
            'verification_token' => CustomDomain::newVerificationToken(),
        ]);

        [$churchB, $ownerB] = $this->denominationOwner();
        app()->instance('tenant.church_id', $churchB->id);

        $this->actingAs($ownerB)
            ->post(route('custom-domain.store'), ['hostname' => 'taken.org'])
            ->assertSessionHasErrors('hostname');

        // Counted without the tenant scope: church B is bound now, so the
        // scoped query would only ever see church B's (zero) rows.
        $this->assertSame(1, CustomDomain::withoutGlobalScopes()->count());
    }

    public function test_verification_stays_pending_when_the_dns_record_is_absent(): void
    {
        [$church, $user] = $this->denominationOwner();
        app()->instance('tenant.church_id', $church->id);

        $domain = CustomDomain::create([
            'church_id' => $church->id,
            // A name that cannot resolve, so this asserts the honest failure path.
            'hostname' => 'no-such-record.invalid',
            'verification_token' => CustomDomain::newVerificationToken(),
        ]);

        $this->actingAs($user)
            ->post(route('custom-domain.verify'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $domain->refresh();
        $this->assertSame('pending', $domain->status);
        $this->assertNotNull($domain->last_checked_at);
        $this->assertNotNull($domain->last_error);
        $this->assertNull($domain->verified_at);
    }

    public function test_a_non_owner_cannot_reach_these_screens(): void
    {
        [$church] = $this->denominationOwner();
        app()->instance('tenant.church_id', $church->id);

        $pastor = User::factory()->create(['church_id' => $church->id, 'is_registrar' => false]);

        $this->actingAs($pastor)->get(route('api-access.index'))->assertForbidden();
        $this->actingAs($pastor)->get(route('custom-domain.index'))->assertForbidden();
    }
}

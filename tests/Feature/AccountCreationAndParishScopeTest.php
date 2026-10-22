<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * §14: who may create accounts, and what a branch administrator can see.
 *
 * The rule the church asked for is explicitly "only the super admin — the
 * person who registered the main church — has the right to create an account",
 * and "an added pastor sees only their branch's information". Both were
 * previously unenforced: any settings.manage holder could mint credentials, and
 * Settings listed the whole church plus every parish name in its pickers.
 */
class AccountCreationAndParishScopeTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private OrganizationalUnit $north;

    private OrganizationalUnit $south;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::factory()->create();
        app()->instance('tenant.church_id', $this->church->id);

        $type = UnitType::create(['church_id' => $this->church->id, 'name' => 'Branch', 'level' => 1]);

        $this->north = OrganizationalUnit::create([
            'church_id' => $this->church->id, 'unit_type_id' => $type->id, 'name' => 'North Parish',
        ]);
        $this->south = OrganizationalUnit::create([
            'church_id' => $this->church->id, 'unit_type_id' => $type->id, 'name' => 'South Parish',
        ]);
    }

    /** An administrator who is NOT the church owner. */
    private function branchAdmin(array $permissions = ['settings.manage', 'members.view']): User
    {
        $user = User::factory()->create([
            'church_id' => $this->church->id,
            'organizational_unit_id' => $this->north->id,
            'is_registrar' => false,
        ]);

        $role = Role::create(['church_id' => $this->church->id, 'name' => 'Branch Pastor']);
        $role->permissions()->attach(
            collect($permissions)->map(
                fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'x', 'label' => $n])->id
            )
        );
        $user->roles()->attach($role);
        $user->load('roles.permissions');

        return $user;
    }

    /** The account that registered the church. */
    private function owner(): User
    {
        $user = User::factory()->create([
            'church_id' => $this->church->id,
            'is_registrar' => true,
        ]);

        $role = Role::create(['church_id' => $this->church->id, 'name' => 'Church Owner']);
        $role->permissions()->attach(
            Permission::firstOrCreate(['name' => 'settings.manage'], ['group' => 'x', 'label' => 'x'])->id
        );
        $user->roles()->attach($role);
        $user->load('roles.permissions');

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Pastor New',
            'email' => 'new.pastor@example.test',
            'password' => 'a-strong-password-1',
            'password_confirmation' => 'a-strong-password-1',
        ], $overrides);
    }

    public function test_only_the_owner_can_create_an_account(): void
    {
        $this->actingAs($this->branchAdmin())
            ->post(route('settings.team.store'), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new.pastor@example.test']);
    }

    public function test_the_owner_can_create_an_account(): void
    {
        $this->actingAs($this->owner())
            ->post(route('settings.team.store'), $this->payload(['organizational_unit_id' => $this->south->id]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $member = User::where('email', 'new.pastor@example.test')->firstOrFail();
        $this->assertSame($this->church->id, $member->church_id);
        $this->assertSame($this->south->id, $member->organizational_unit_id);
    }

    public function test_a_branch_admin_sees_only_their_own_parish_in_settings(): void
    {
        $admin = $this->branchAdmin();
        User::factory()->create([
            'church_id' => $this->church->id,
            'organizational_unit_id' => $this->south->id,
            'name' => 'South Pastor',
        ]);

        $response = $this->actingAs($admin)->get(route('settings.index', ['tab' => 'team']));

        $response->assertOk();
        $response->assertSee('North Parish');
        $response->assertDontSee('South Parish');
        $response->assertDontSee('South Pastor');
        // A branch admin cannot create accounts, so the form must not be offered.
        $response->assertSee('Accounts are created by the church owner');
        $response->assertDontSee('Create account');
    }

    public function test_a_branch_admin_cannot_administer_another_parishs_account_by_id(): void
    {
        $other = User::factory()->create([
            'church_id' => $this->church->id,
            'organizational_unit_id' => $this->south->id,
            'password' => Hash::make('their-password'),
        ]);

        $this->actingAs($this->branchAdmin())
            ->post(route('settings.team.password', $other), [
                'password' => 'hijacked-password-1',
                'password_confirmation' => 'hijacked-password-1',
            ])
            ->assertForbidden();
    }

    public function test_a_non_owner_cannot_grant_registrar_authority(): void
    {
        // Even if the route were reachable, the flag itself is the owner's call.
        $this->actingAs($this->branchAdmin())
            ->post(route('settings.team.store'), $this->payload(['is_registrar' => 1]))
            ->assertForbidden();
    }

    public function test_a_church_owner_without_the_registrar_flag_still_counts_as_owner(): void
    {
        // Churches created before the flag existed have an owner holding the
        // Church Owner role and no registrar row. They must not be locked out
        // of their own settings screen.
        $legacy = User::factory()->create([
            'church_id' => $this->church->id,
            'is_registrar' => false,
        ]);
        $role = Role::create(['church_id' => $this->church->id, 'name' => 'Church Owner']);
        $role->permissions()->attach(
            Permission::firstOrCreate(['name' => 'settings.manage'], ['group' => 'x', 'label' => 'x'])->id
        );
        $legacy->roles()->attach($role);
        $legacy->load('roles.permissions');

        $this->assertTrue($legacy->isRegistrar());

        $this->actingAs($legacy)
            ->post(route('settings.team.store'), $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }
}

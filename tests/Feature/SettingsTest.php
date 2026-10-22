<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The church Settings area: profile, team & credentials, roles.
 *
 * The important guarantees: an administrator CAN manage their own church's
 * people and profile, and CANNOT touch another church's people (the user list
 * is scoped by an explicit church_id filter, because User has no tenant scope).
 */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Church $church, array $extra = [], bool $registrar = true): User
    {
        $permissions = array_merge(['settings.manage'], $extra);
        $ids = collect($permissions)->map(
            fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'x', 'label' => $n])->id
        );

        // §14: creating accounts is the registrar's authority, so the default
        // administrator here is the church owner. A test that needs a
        // non-owner administrator passes $registrar = false.
        $user = User::factory()->create(['church_id' => $church->id, 'is_registrar' => $registrar]);
        $role = Role::create(['church_id' => $church->id, 'name' => 'Admin '.uniqid()]);
        $role->permissions()->attach($ids);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_the_settings_page_renders_all_three_tabs(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $this->actingAs($this->admin($church))->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Church profile')
            ->assertSee('Team &amp; credentials', false)
            ->assertSee('Roles &amp; permissions', false);
    }

    public function test_a_user_without_settings_manage_is_forbidden(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user)->get(route('settings.index'))->assertForbidden();
    }

    public function test_an_administrator_can_update_the_church_profile(): void
    {
        $church = Church::factory()->create(['name' => 'Old Name']);
        app()->instance('tenant.church_id', $church->id);

        $this->actingAs($this->admin($church))->post(route('settings.profile.update'), [
            'name' => 'Grace Chapel',
            'denomination' => 'RCCG',
            'timezone' => 'Africa/Lagos',
            'currency' => 'ngn',
            'terminology' => ['province' => 'Diocese'],
        ])->assertRedirect();

        $church->refresh();
        $this->assertSame('Grace Chapel', $church->name);
        $this->assertSame('RCCG', $church->denomination);
        $this->assertSame('NGN', $church->currency);
        $this->assertSame('Diocese', $church->settings['terminology']['province']);
        // The label helper reads it back.
        $this->assertSame('Diocese', $church->label('province', 'Province'));
    }

    public function test_an_administrator_can_create_a_team_member_credential(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $admin = $this->admin($church);
        $role = Role::where('church_id', $church->id)->first();

        $this->actingAs($admin)->post(route('settings.team.store'), [
            'name' => 'Pastor John',
            'email' => 'john@church.test',
            'password' => 'a-strong-password-1',
            'password_confirmation' => 'a-strong-password-1',
            'role_id' => $role->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $member = User::where('email', 'john@church.test')->first();
        $this->assertNotNull($member);
        $this->assertSame($church->id, $member->church_id);
        $this->assertTrue($member->roles->pluck('id')->contains($role->id));
    }

    public function test_the_team_list_shows_the_sign_in_credentials(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $admin = $this->admin($church);
        User::factory()->create(['church_id' => $church->id, 'name' => 'Team Member', 'email' => 'member@church.test']);

        $this->actingAs($admin)->get(route('settings.index', ['tab' => 'team']))
            ->assertOk()
            ->assertSee('member@church.test')
            ->assertSee('Sign-in email');
    }

    public function test_an_administrator_can_reset_a_members_password(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $admin = $this->admin($church);
        $member = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($admin)->post(route('settings.team.password', $member), [
            'password' => 'brand-new-pass-1',
            'password_confirmation' => 'brand-new-pass-1',
        ])->assertRedirect();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('brand-new-pass-1', $member->fresh()->password));
    }

    public function test_an_administrator_can_deactivate_and_reactivate_a_member(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $admin = $this->admin($church);
        $member = User::factory()->create(['church_id' => $church->id, 'is_active' => true]);

        $this->actingAs($admin)->post(route('settings.team.toggle', $member))->assertRedirect();
        $this->assertFalse((bool) $member->fresh()->is_active);

        $this->actingAs($admin)->post(route('settings.team.toggle', $member))->assertRedirect();
        $this->assertTrue((bool) $member->fresh()->is_active);
    }

    public function test_an_administrator_cannot_deactivate_themselves(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $admin = $this->admin($church);

        $this->actingAs($admin)->post(route('settings.team.toggle', $admin))->assertStatus(409);
        $this->assertTrue((bool) $admin->fresh()->is_active);
    }

    public function test_an_administrator_cannot_touch_another_churchs_member(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        app()->instance('tenant.church_id', $churchA->id);
        $admin = $this->admin($churchA);
        $outsider = User::factory()->create(['church_id' => $churchB->id, 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('settings.team.toggle', $outsider))
            ->assertForbidden();

        $this->assertTrue((bool) $outsider->fresh()->is_active);
    }

    public function test_the_roles_tab_shows_the_permission_matrix(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $admin = $this->admin($church);

        $this->actingAs($admin)->get(route('settings.index', ['tab' => 'roles']))
            ->assertOk()
            ->assertSee('Roles and what they can do')
            ->assertSee('settings.manage');
    }

    public function test_every_settings_form_renders_the_submit_loader(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $admin = $this->admin($church);

        foreach (['profile', 'team', 'roles'] as $tab) {
            $this->actingAs($admin)->get(route('settings.index', ['tab' => $tab]))
                ->assertOk()
                ->assertSee('cf-loader-overlay', false);
        }
    }
}

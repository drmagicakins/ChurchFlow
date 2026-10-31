<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §11/§34: the setup checklist's only REQUIRED step (church_profile) used to
 * be markable complete without writing anything, so every church kept the
 * "<owner>'s Church" placeholder created at activation. These tests prove the
 * name is now collected, validated, persisted, and that the step completes as
 * a consequence rather than on its own say-so.
 */
class SetupChurchNameTest extends TestCase
{
    use RefreshDatabase;

    /** A user holding settings.manage on their own church. */
    private function owner(Church $church): User
    {
        $user = User::factory()->create(['church_id' => $church->id]);

        $permission = Permission::firstOrCreate(
            ['name' => 'settings.manage'],
            ['group' => 'settings', 'label' => 'Manage settings'],
        );

        $role = Role::create(['church_id' => $church->id, 'name' => 'Admin '.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_saving_the_name_persists_it_and_completes_the_step(): void
    {
        $church = Church::factory()->create(['name' => "Owner's Church"]);
        $user = $this->owner($church);

        $this->actingAs($user)
            ->post(route('setup.profile'), ['name' => 'Grace Chapel Lagos'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Grace Chapel Lagos', $church->fresh()->name);
        $this->assertDatabaseHas('church_setup_progress', [
            'church_id' => $church->id,
            'step_key' => 'church_profile',
        ]);
    }

    public function test_the_placeholder_headquarters_branch_follows_the_new_name(): void
    {
        $church = Church::factory()->create();
        $user = $this->owner($church);
        $user->update(['name' => 'Owner']);

        $type = UnitType::create(['church_id' => $church->id, 'name' => 'Headquarters', 'level' => 0]);
        $hq = OrganizationalUnit::create([
            'church_id' => $church->id,
            'unit_type_id' => $type->id,
            'name' => "Owner's Church", // the activation placeholder
        ]);

        $this->actingAs($user)
            ->post(route('setup.profile'), ['name' => 'Grace Chapel Lagos'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Grace Chapel Lagos', $hq->fresh()->name);
    }

    public function test_a_deliberately_renamed_branch_is_not_overwritten(): void
    {
        $church = Church::factory()->create();
        $user = $this->owner($church);
        $user->update(['name' => 'Owner']);

        $type = UnitType::create(['church_id' => $church->id, 'name' => 'Headquarters', 'level' => 0]);
        $hq = OrganizationalUnit::create([
            'church_id' => $church->id,
            'unit_type_id' => $type->id,
            'name' => 'Main Campus', // already renamed on purpose
        ]);

        $this->actingAs($user)
            ->post(route('setup.profile'), ['name' => 'Grace Chapel Lagos'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Main Campus', $hq->fresh()->name);
    }

    public function test_a_blank_name_is_rejected_and_nothing_changes(): void
    {
        $church = Church::factory()->create(['name' => 'Original Name']);
        $user = $this->owner($church);

        $this->actingAs($user)
            ->post(route('setup.profile'), ['name' => '   '])
            ->assertSessionHasErrors('name');

        $this->assertSame('Original Name', $church->fresh()->name);
        $this->assertDatabaseMissing('church_setup_progress', [
            'church_id' => $church->id,
            'step_key' => 'church_profile',
        ]);
    }

    public function test_a_name_over_the_length_limit_is_rejected(): void
    {
        $church = Church::factory()->create(['name' => 'Original Name']);
        $user = $this->owner($church);

        $this->actingAs($user)
            ->post(route('setup.profile'), ['name' => str_repeat('a', 121)])
            ->assertSessionHasErrors('name');

        $this->assertSame('Original Name', $church->fresh()->name);
    }

    public function test_a_user_without_settings_manage_is_forbidden(): void
    {
        $church = Church::factory()->create(['name' => 'Original Name']);
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user)
            ->post(route('setup.profile'), ['name' => 'Hijacked Name'])
            ->assertForbidden();

        $this->assertSame('Original Name', $church->fresh()->name);
    }

    public function test_a_user_cannot_rename_another_church(): void
    {
        $mine = Church::factory()->create();
        $theirs = Church::factory()->create(['name' => 'Other Church']);
        $user = $this->owner($mine);

        $this->actingAs($user)
            ->post(route('setup.profile'), ['name' => 'Renamed By Intruder'])
            ->assertSessionHasNoErrors();

        // The controller writes to the authenticated user's church only —
        // there is no church parameter to tamper with.
        $this->assertSame('Other Church', $theirs->fresh()->name);
    }

    public function test_the_setup_page_renders_a_name_field_for_the_profile_step(): void
    {
        $church = Church::factory()->create(['name' => 'Grace Chapel Lagos']);
        $user = $this->owner($church);

        $this->actingAs($user)
            ->get(route('setup.show'))
            ->assertOk()
            ->assertSee('setup-church-name', false)
            ->assertSee('Grace Chapel Lagos');
    }
}

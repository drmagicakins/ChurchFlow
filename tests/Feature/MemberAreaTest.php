<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Department;
use App\Models\Family;
use App\Models\Group;
use App\Models\Member;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The member AREA: the list and the profile page.
 *
 * The profile page was a five-line stub, so a member's families, departments,
 * groups, contact details and photo were all collected and then never shown —
 * the data existed, the screen to read it did not.
 */
class MemberAreaTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(Church $church, array $permissions): User
    {
        $ids = collect($permissions)->map(
            fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'x', 'label' => 'x'])->id
        );
        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::create(['church_id' => $church->id, 'name' => 'Members '.uniqid()]);
        $role->permissions()->attach($ids);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_the_member_list_renders_with_a_member(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = $this->userWith($church, ['members.view', 'members.create']);
        Member::factory()->for($church, 'church')->create(['full_name' => 'Adaeze Okonkwo']);

        $this->actingAs($user)
            ->get(route('members.index'))
            ->assertOk()
            ->assertSee('Adaeze Okonkwo')
            ->assertSee('Add Member');
    }

    public function test_the_member_profile_shows_contact_details_and_belonging(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = $this->userWith($church, ['members.view']);

        $member = Member::factory()->for($church, 'church')->create([
            'full_name' => 'Samuel Adeyemi',
            'email' => 'samuel@example.test',
            'phone' => '+234 800 111 2222',
            'gender' => 'male',
            'address' => '12 Broad Street, Lagos',
            'emergency_contact_name' => 'Ruth Adeyemi',
            'emergency_contact_phone' => '+234 800 333 4444',
        ]);

        $family = Family::create(['church_id' => $church->id, 'name' => 'The Adeyemis']);
        $member->families()->attach($family, ['relationship' => 'Head']);

        $department = Department::create(['church_id' => $church->id, 'name' => 'Ushering']);
        $member->departments()->attach($department, ['role' => 'Coordinator']);

        $group = Group::create(['church_id' => $church->id, 'name' => 'Choir']);
        $member->groups()->attach($group);

        $response = $this->actingAs($user)->get(route('members.show', $member));

        $response->assertOk();
        $response->assertSee('Samuel Adeyemi');
        $response->assertSee('samuel@example.test');
        $response->assertSee('+234 800 111 2222');
        $response->assertSee('12 Broad Street, Lagos');
        $response->assertSee('Ruth Adeyemi');
        // Belonging — the relationships the profile page now surfaces.
        $response->assertSee('The Adeyemis');
        $response->assertSee('Head');
        $response->assertSee('Ushering');
        $response->assertSee('Coordinator');
        $response->assertSee('Choir');
    }

    public function test_the_member_profile_renders_when_the_member_has_no_relationships(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = $this->userWith($church, ['members.view']);
        $member = Member::factory()->for($church, 'church')->create(['full_name' => 'Lone Member']);

        $this->actingAs($user)
            ->get(route('members.show', $member))
            ->assertOk()
            ->assertSee('Not linked to a family yet')
            ->assertSee('Not serving in a department yet')
            ->assertSee('Not in a group yet');
    }

    public function test_a_member_can_be_created_through_the_list_form(): void
    {
        $church = Church::factory()->create(['status' => 'active']);
        app()->instance('tenant.church_id', $church->id);
        $user = $this->userWith($church, ['members.view', 'members.create']);

        $this->actingAs($user)
            ->post(route('members.store'), [
                'full_name' => 'Grace Okafor',
                'email' => 'grace@example.test',
                'membership_status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('members', [
            'church_id' => $church->id,
            'full_name' => 'Grace Okafor',
            'email' => 'grace@example.test',
        ]);
    }
}

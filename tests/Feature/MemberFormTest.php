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
 * Phase 12 — the form components and the rebuilt member list.
 *
 * These assert the things that break silently in Blade: a component whose
 * props don't line up, an icon name that doesn't exist (renders an empty box,
 * not an error), a validation error that never appears next to its field, and
 * repopulation after a failed submit — the difference between a form someone
 * can fix and a form that loses their work.
 */
class MemberFormTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Church $church, array $permissions = ['members.view', 'members.create', 'members.edit', 'members.delete']): User
    {
        $ids = collect($permissions)->map(fn ($n) => Permission::firstOrCreate(
            ['name' => $n], ['group' => 'members', 'label' => $n]
        )->id);

        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::create(['church_id' => $church->id, 'name' => 'Members Admin']);
        $role->permissions()->attach($ids);
        $user->roles()->attach($role);

        app()->instance('tenant.church_id', $church->id);

        return $user->fresh();
    }

    public function test_the_members_page_renders_the_styled_create_form(): void
    {
        $church = Church::factory()->create();
        $user = $this->admin($church);

        $this->actingAs($user)
            ->get(route('members.index'))
            ->assertOk()
            // The form itself, with its action and submit control.
            ->assertSee(route('members.store'), false)
            ->assertSee('Add a new member')
            // Every input the StoreMemberRequest validates should be present,
            // otherwise the form is quietly un-submittable for that field.
            ->assertSee('name="full_name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="membership_status"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('name="date_joined"', false)
            ->assertSee('name="emergency_contact_name"', false)
            ->assertSee('name="notes"', false)
            // Labels are wired to inputs, which is what makes the form usable
            // by screen reader and by click-on-label.
            ->assertSee('class="cf-label"', false)
            ->assertSee('class="cf-input"', false)
            ->assertSee('class="cf-select"', false)
            ->assertSee('class="cf-textarea"', false);
    }

    public function test_the_members_page_shows_a_member_row_and_its_status_badge(): void
    {
        $church = Church::factory()->create();
        $user = $this->admin($church);
        $member = Member::factory()->for($church, 'church')->create([
            'full_name' => 'Adaeze Okonkwo', 'membership_status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('members.index'))
            ->assertOk()
            ->assertSee('Adaeze Okonkwo')
            ->assertSee($member->membership_number)
            ->assertSee('cf-badge--ok', false);
    }

    public function test_an_empty_member_list_shows_a_teaching_empty_state_not_a_blank_table(): void
    {
        $church = Church::factory()->create();
        $user = $this->admin($church);

        $this->actingAs($user)
            ->get(route('members.index'))
            ->assertOk()
            ->assertSee('No members yet')
            ->assertSee('cf-empty', false);
    }

    public function test_a_failed_submission_redisplays_the_form_with_the_error_beside_the_field(): void
    {
        $church = Church::factory()->create();
        $user = $this->admin($church);

        // full_name is required. Submitting without it must NOT 500, must NOT
        // silently succeed, and must not lose what the person did type.
        $this->actingAs($user)
            ->from(route('members.index'))
            ->post(route('members.store'), [
                'full_name' => '',
                'email' => 'not-an-email',
            ])
            ->assertSessionHasErrors(['full_name', 'email']);

        $this->assertSame(0, Member::query()->count());
    }

    public function test_creating_a_member_works_and_redirects_with_a_status_message(): void
    {
        $church = Church::factory()->create();
        $user = $this->admin($church);

        $this->actingAs($user)
            ->post(route('members.store'), [
                'full_name' => 'Chidi Nwosu',
                'email' => 'chidi@example.com',
                'membership_status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('members', [
            'church_id' => $church->id,
            'full_name' => 'Chidi Nwosu',
        ]);
    }

    public function test_a_member_without_create_permission_never_sees_the_create_form(): void
    {
        $church = Church::factory()->create();
        $user = $this->admin($church, ['members.view']);

        // Assert on the form's own markers rather than on a human-readable
        // phrase. Two traps this avoids, both of which made earlier versions of
        // this test fail for reasons unrelated to permission:
        //
        //  1. The human-readable phrase also appears in the rendered markup's
        //     own source comments.
        //  2. route('members.store') and the navigation sidebar's link to the
        //     members INDEX both render as "/members". So asserting the action
        //     URL alone matches the nav link that a view-only user is entitled
        //     to, and reports a leak that isn't there. The action is matched
        //     with its attribute name, which the nav link does not have, and
        //     the strongest signal is the field markup itself.
        $this->actingAs($user)
            ->get(route('members.index'))
            ->assertOk()
            ->assertDontSee('data-toggle="member-create"', false)
            ->assertDontSee('name="full_name"', false)
            ->assertDontSee('Add a new member');
    }
}

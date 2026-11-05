<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Member;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Editing a member.
 *
 * The PUT route, UpdateMemberRequest and MemberController::update() all
 * existed, but no screen ever posted to them: the member page offered only
 * "Find in list" and "Remove member". So "edit member" was unreachable in the
 * UI even though the backend worked. These tests lock both halves in place —
 * the form is on the page, and saving it actually changes the record.
 */
class MemberEditTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::factory()->create();
        app()->instance('tenant.church_id', $this->church->id);
    }

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create(['church_id' => $this->church->id]);

        $ids = collect($permissions)->map(
            fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'x', 'label' => $n])->id
        );
        $role = Role::create(['church_id' => $this->church->id, 'name' => 'R '.uniqid()]);
        $role->permissions()->attach($ids);
        $user->roles()->attach($role);

        return $user->load('roles.permissions');
    }

    private function member(array $attributes = []): Member
    {
        return Member::factory()->for($this->church, 'church')->create(array_merge([
            'full_name' => 'Ada Obi',
            'membership_status' => 'active',
            'is_worker' => false,
        ], $attributes));
    }

    public function test_the_member_page_offers_an_edit_form_that_posts_to_the_update_route(): void
    {
        $member = $this->member();
        $user = $this->userWith(['members.view', 'members.edit']);

        $html = $this->actingAs($user)->get(route('members.show', $member))->assertOk()->getContent();

        // The button and the form are both present…
        $this->assertStringContainsString('data-toggle="member-edit"', $html);
        $this->assertStringContainsString('data-panel="member-edit"', $html);

        // …and the form actually submits to PUT members/{member}.
        $this->assertStringContainsString('action="'.route('members.update', ['member' => $member]).'"', $html);
        $this->assertStringContainsString('name="_method" value="PUT"', $html);

        // Every field the update request validates is on the form.
        foreach ([
            'full_name', 'email', 'phone', 'membership_status', 'gender',
            'date_of_birth', 'date_joined', 'baptism_date',
            'emergency_contact_name', 'emergency_contact_phone',
            'address', 'notes', 'is_worker',
        ] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html, "Missing field: {$field}");
        }
    }

    public function test_saving_the_edit_form_updates_and_persists_every_field(): void
    {
        $type = UnitType::create(['church_id' => $this->church->id, 'name' => 'Branch', 'level' => 0]);
        $branch = OrganizationalUnit::create([
            'church_id' => $this->church->id, 'unit_type_id' => $type->id, 'name' => 'Ikeja',
        ]);

        $member = $this->member();
        $user = $this->userWith(['members.view', 'members.edit']);

        $response = $this->actingAs($user)->put(route('members.update', ['member' => $member]), [
            'full_name' => 'Adaeze Okonkwo',
            'email' => 'adaeze@example.test',
            'phone' => '+2348000000000',
            'membership_status' => 'inactive',
            'organizational_unit_id' => $branch->id,
            'gender' => 'female',
            'date_of_birth' => '1990-05-01',
            'date_joined' => '2020-01-15',
            'baptism_date' => '2019-12-01',
            'emergency_contact_name' => 'Chidi Okonkwo',
            'emergency_contact_phone' => '+2348111111111',
            'address' => '12 Allen Avenue, Ikeja',
            'notes' => 'Prefers a call on Sundays.',
            'is_worker' => '1',
        ]);

        $response->assertRedirect(route('members.show', $member));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'Member updated.');

        $fresh = $member->fresh();

        $this->assertSame('Adaeze Okonkwo', $fresh->full_name);
        $this->assertSame('adaeze@example.test', $fresh->email);
        $this->assertSame('+2348000000000', $fresh->phone);
        $this->assertSame('inactive', $fresh->membership_status);
        $this->assertSame($branch->id, $fresh->organizational_unit_id);
        $this->assertSame('female', $fresh->gender);
        $this->assertSame('1990-05-01', $fresh->date_of_birth->format('Y-m-d'));
        $this->assertSame('2020-01-15', $fresh->date_joined->format('Y-m-d'));
        $this->assertSame('2019-12-01', $fresh->baptism_date->format('Y-m-d'));
        $this->assertSame('Chidi Okonkwo', $fresh->emergency_contact_name);
        $this->assertSame('+2348111111111', $fresh->emergency_contact_phone);
        $this->assertSame('12 Allen Avenue, Ikeja', $fresh->address);
        $this->assertSame('Prefers a call on Sundays.', $fresh->notes);
        $this->assertTrue((bool) $fresh->is_worker);

        // The save is visible on the page afterwards, with the confirmation.
        $page = $this->actingAs($user->fresh())->get(route('members.show', $member->fresh()))->getContent();
        $this->assertStringContainsString('Adaeze Okonkwo', $page);
        $this->assertStringContainsString('Member updated.', $page);
    }

    public function test_the_worker_flag_can_be_turned_off_again(): void
    {
        $member = $this->member(['is_worker' => true]);
        $user = $this->userWith(['members.view', 'members.edit']);

        // An unticked checkbox sends the hidden "0" and nothing else.
        $this->actingAs($user)->put(route('members.update', ['member' => $member]), [
            'full_name' => $member->full_name,
            'is_worker' => '0',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse((bool) $member->fresh()->is_worker);
    }

    public function test_the_form_is_prefilled_with_the_members_current_values(): void
    {
        $member = $this->member([
            'full_name' => 'Grace Adeyemi',
            'email' => 'grace@example.test',
            'phone' => '+2348022222222',
            'notes' => 'Choir leader.',
        ]);
        $user = $this->userWith(['members.view', 'members.edit']);

        $html = $this->actingAs($user)->get(route('members.show', $member))->getContent();

        $this->assertStringContainsString('value="Grace Adeyemi"', $html);
        $this->assertStringContainsString('value="grace@example.test"', $html);
        $this->assertStringContainsString('value="+2348022222222"', $html);
        $this->assertStringContainsString('Choir leader.', $html);
    }

    public function test_a_member_without_edit_permission_cannot_edit_and_sees_no_form(): void
    {
        $member = $this->member();
        $viewer = $this->userWith(['members.view']);

        $html = $this->actingAs($viewer)->get(route('members.show', $member))->assertOk()->getContent();

        // No edit form and no edit button. (The inline script still mentions the
        // panel selector by name — it is the shared runtime toggle — so the
        // assertion is on the elements themselves, not the bare string.)
        $this->assertStringNotContainsString('id="member-edit-panel"', $html);
        $this->assertStringNotContainsString('aria-controls="member-edit-panel"', $html);
        $this->assertStringNotContainsString('action="'.route('members.update', ['member' => $member]).'"', $html);
        // The rest of the page is untouched.
        $this->assertStringContainsString('Profile', $html);

        $this->actingAs($viewer)
            ->put(route('members.update', ['member' => $member]), ['full_name' => 'Hacked'])
            ->assertForbidden();

        $this->assertSame('Ada Obi', $member->fresh()->full_name);
    }

    public function test_a_blank_name_returns_to_the_member_page_with_the_error_and_keeps_old_input(): void
    {
        $member = $this->member();
        $user = $this->userWith(['members.view', 'members.edit']);

        $this->actingAs($user)
            ->from(route('members.show', $member))
            ->put(route('members.update', ['member' => $member]), [
                'full_name' => '',
                'email' => 'kept@example.test',
            ])
            ->assertRedirect(route('members.show', $member))
            ->assertSessionHasErrors('full_name');

        $this->assertSame('Ada Obi', $member->fresh()->full_name);

        // The typed value is preserved and the panel is open so the message is visible.
        $page = $this->actingAs($user)->get(route('members.show', $member))->getContent();
        $this->assertStringContainsString('value="kept@example.test"', $page);
        $this->assertStringNotContainsString('data-panel="member-edit" hidden', $page);
    }

    public function test_a_duplicate_email_is_rejected_but_the_members_own_email_is_fine(): void
    {
        $other = $this->member(['full_name' => 'Someone Else', 'email' => 'taken@example.test']);
        $member = $this->member(['full_name' => 'Ada Obi', 'email' => 'ada@example.test']);
        $user = $this->userWith(['members.view', 'members.edit']);

        $this->actingAs($user)
            ->put(route('members.update', ['member' => $member]), [
                'full_name' => 'Ada Obi',
                'email' => 'taken@example.test',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame('ada@example.test', $member->fresh()->email);

        // Keeping its own email is not a duplicate of itself.
        $this->actingAs($user)
            ->put(route('members.update', ['member' => $member]), [
                'full_name' => 'Ada Obi',
                'email' => 'ada@example.test',
            ])
            ->assertRedirect(route('members.show', $member))
            ->assertSessionHasNoErrors();
    }

    /**
     * Editing must never be able to move a member into another church.
     */
    public function test_a_member_of_another_church_cannot_be_edited(): void
    {
        $otherChurch = Church::factory()->create();
        app()->instance('tenant.church_id', $otherChurch->id);
        $foreign = Member::factory()->for($otherChurch, 'church')->create(['full_name' => 'Foreign Member']);

        app()->instance('tenant.church_id', $this->church->id);
        $user = $this->userWith(['members.view', 'members.edit']);

        $this->actingAs($user)
            ->put(route('members.update', ['member' => $foreign]), ['full_name' => 'Changed'])
            ->assertNotFound();

        $this->assertSame('Foreign Member', Member::withoutGlobalScopes()->find($foreign->id)->full_name);
    }
}

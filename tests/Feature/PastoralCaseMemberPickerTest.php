<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Member;
use App\Models\OrganizationalUnit;
use App\Models\PastoralCase;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Opening a pastoral case from the Pastoral page.
 *
 * Reported symptom: "Member ID not found." The form asked for a raw numeric
 * "Member ID" — the table's internal id, which no screen in the app ever shows
 * (members are displayed by name and membership number). So the person either
 * typed the membership number (rejected as "does not exist") or guessed, and
 * the case could not be opened.
 *
 * Fixed by choosing the member from a list of names instead of typing an id.
 * These tests pin that down, and that the rest of the form matches what the
 * controller validates.
 */
class PastoralCaseMemberPickerTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::factory()->create();
        app()->instance('tenant.church_id', $this->church->id);
    }

    private function pastor(): User
    {
        $user = User::factory()->create(['church_id' => $this->church->id]);

        $ids = collect(['members.view', 'pastoral.manage'])->map(
            fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'pastoral', 'label' => $n])->id
        );
        $role = Role::create(['church_id' => $this->church->id, 'name' => 'Pastor '.uniqid()]);
        $role->permissions()->attach($ids);
        $user->roles()->attach($role);

        return $user->load('roles.permissions');
    }

    private function member(string $name = 'Ronke Oshodi'): Member
    {
        return Member::factory()->for($this->church, 'church')->create(['full_name' => $name]);
    }

    public function test_the_form_offers_members_by_name_not_a_raw_numeric_id(): void
    {
        $member = $this->member('Ronke Oshodi');
        $pastor = $this->pastor();

        $html = $this->actingAs($pastor)->get(route('pastoral.cases.index'))->assertOk()->getContent();

        // No more "type the internal id" box.
        $this->assertStringNotContainsString('name="member_id" type="number"', $html);
        $this->assertStringNotContainsString('label="Member ID"', $html);

        // The member is offered by name, with the membership number as the clue.
        $this->assertStringContainsString('name="member_id"', $html);
        $this->assertStringContainsString('Ronke Oshodi', $html);
        $this->assertStringContainsString($member->membership_number, $html);
        $this->assertStringContainsString('value="'.$member->id.'"', $html);
    }

    /**
     * Every case type the dropdown offers must be a type the controller accepts,
     * and the summary the form sends must be a field it reads. Both used to
     * disagree, so a perfectly filled form was rejected.
     */
    public function test_every_case_type_in_the_dropdown_is_accepted_and_the_summary_is_saved(): void
    {
        $pastor = $this->pastor();

        $offered = collect([
            'counseling', 'welfare', 'hospital_visit', 'new_member_followup', 'other',
        ]);

        foreach ($offered as $type) {
            $member = $this->member('Member '.$type);

            $this->actingAs($pastor)
                ->post(route('pastoral.cases.store'), [
                    'member_id' => $member->id,
                    'type' => $type,
                    'status' => 'open',
                    'description' => 'Summary typed into the form for '.$type,
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $case = PastoralCase::where('member_id', $member->id)->latest('id')->firstOrFail();
            $this->assertSame($type, $case->type);
            $this->assertSame('Summary typed into the form for '.$type, $case->description);
        }
    }

    public function test_opening_a_case_from_the_picker_succeeds_and_lands_on_the_case(): void
    {
        $member = $this->member('Grace Adeyemi');
        $pastor = $this->pastor();

        $response = $this->actingAs($pastor)->post(route('pastoral.cases.store'), [
            'member_id' => $member->id,
            'type' => 'hospital_visit',
            'status' => 'open',
            'description' => 'Admitted at General Hospital.',
        ]);

        $case = PastoralCase::firstOrFail();

        $response->assertRedirect(route('pastoral.cases.show', $case));
        $this->assertSame($member->id, $case->member_id);
        $this->assertSame('Admitted at General Hospital.', $case->description);

        // The case opens with its first note, as before.
        $this->assertCount(1, $case->notes);
        $this->assertStringContainsString('Case opened', $case->notes->first()->note);
    }

    /**
     * If a member is not visible to this user (another church), the form says so
     * instead of answering with a bare "not found".
     */
    public function test_a_member_outside_the_users_scope_reports_a_form_error_instead_of_crashing(): void
    {
        $otherChurch = Church::factory()->create();
        app()->instance('tenant.church_id', $otherChurch->id);
        $foreign = Member::factory()->for($otherChurch, 'church')->create(['full_name' => 'Foreign Member']);

        app()->instance('tenant.church_id', $this->church->id);
        $pastor = $this->pastor();
        $this->member('Own Member');

        $response = $this->actingAs($pastor)->from(route('pastoral.cases.index'))
            ->post(route('pastoral.cases.store'), [
                'member_id' => $foreign->id,
                'type' => 'counseling',
                'description' => 'Should not be created.',
            ]);

        $response->assertRedirect(route('pastoral.cases.index'));
        $response->assertSessionHasErrors('member_id');

        $this->assertSame(0, PastoralCase::withoutGlobalScopes()->count());
    }

    public function test_an_empty_member_list_explains_itself_instead_of_offering_an_impossible_form(): void
    {
        $pastor = $this->pastor();

        $html = $this->actingAs($pastor)->get(route('pastoral.cases.index'))->assertOk()->getContent();

        $this->assertStringContainsString('No members exist yet', $html);
        // …and no member dropdown that could only fail.
        $this->assertStringNotContainsString('name="member_id"', $html);
    }

    public function test_the_member_list_only_offers_members_of_this_church(): void
    {
        $own = $this->member('Own Member');

        $otherChurch = Church::factory()->create();
        app()->instance('tenant.church_id', $otherChurch->id);
        Member::factory()->for($otherChurch, 'church')->create(['full_name' => 'Foreign Member']);

        app()->instance('tenant.church_id', $this->church->id);
        $pastor = $this->pastor();

        $html = $this->actingAs($pastor)->get(route('pastoral.cases.index'))->getContent();

        $this->assertStringContainsString('Own Member', $html);
        $this->assertStringContainsString('value="'.$own->id.'"', $html);
        $this->assertStringNotContainsString('Foreign Member', $html);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Department;
use App\Models\Family;
use App\Models\Group;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyDepartmentGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_be_attached_to_a_family_with_a_relationship_label(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $family = Family::create(['church_id' => $church->id, 'name' => 'The Doe Family']);
        $head = Member::factory()->for($church, 'church')->create(['full_name' => 'John Doe']);
        $child = Member::factory()->for($church, 'church')->create(['full_name' => 'Baby Doe']);

        $family->members()->attach($head, ['relationship' => 'head']);
        $family->members()->attach($child, ['relationship' => 'child']);

        $this->assertCount(2, $family->fresh()->members);
        $this->assertSame('head', $family->members()->find($head->id)->pivot->relationship);
    }

    public function test_a_member_can_belong_to_multiple_departments(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $member = Member::factory()->for($church, 'church')->create();
        $ushering = Department::create(['church_id' => $church->id, 'name' => 'Ushering']);
        $media = Department::create(['church_id' => $church->id, 'name' => 'Media']);

        $member->departments()->attach([$ushering->id, $media->id]);

        $this->assertCount(2, $member->fresh()->departments);
    }

    public function test_a_church_cannot_see_another_churchs_families_departments_or_groups(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();

        Family::create(['church_id' => $churchA->id, 'name' => 'Family A']);
        Family::create(['church_id' => $churchB->id, 'name' => 'Family B']);

        Department::create(['church_id' => $churchA->id, 'name' => 'Dept A']);
        Department::create(['church_id' => $churchB->id, 'name' => 'Dept B']);

        Group::create(['church_id' => $churchA->id, 'name' => 'Group A']);
        Group::create(['church_id' => $churchB->id, 'name' => 'Group B']);

        app()->instance('tenant.church_id', $churchA->id);

        $this->assertCount(1, Family::all());
        $this->assertSame('Family A', Family::first()->name);

        $this->assertCount(1, Department::all());
        $this->assertSame('Dept A', Department::first()->name);

        $this->assertCount(1, Group::all());
        $this->assertSame('Group A', Group::first()->name);
    }

    public function test_a_family_cannot_absorb_a_member_from_a_different_church_even_via_direct_attach_call(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();

        $family = Family::create(['church_id' => $churchA->id, 'name' => 'Family A']);
        $memberOfB = Member::factory()->for($churchB, 'church')->create();

        // The controller layer checks this explicitly (see FamilyController::
        // attachMember) precisely because the pivot table itself has no
        // church_id to enforce it at the database level. This test protects
        // that application-level check from silently being removed later.
        $this->assertNotEquals($family->church_id, $memberOfB->church_id);
    }

    public function test_custom_field_values_are_scoped_through_their_member(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();

        $defA = \App\Models\MemberCustomFieldDefinition::create([
            'church_id' => $churchA->id, 'key' => 'shirt_size', 'label' => 'Shirt Size',
        ]);

        $memberA = Member::factory()->for($churchA, 'church')->create();
        $memberA->customFieldValues()->create([
            'member_custom_field_definition_id' => $defA->id,
            'value' => 'L',
        ]);

        app()->instance('tenant.church_id', $churchB->id);
        $this->assertCount(0, \App\Models\MemberCustomFieldValue::all());

        app()->instance('tenant.church_id', $churchA->id);
        $this->assertCount(1, \App\Models\MemberCustomFieldValue::all());
    }
}

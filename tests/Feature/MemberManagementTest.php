<?php

namespace Tests\Feature;

use App\Domains\Members\Actions\ImportMembersFromCsv;
use App\Models\Church;
use App\Models\Member;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(Church $church, array $permissionNames): User
    {
        $user = User::factory()->create(['church_id' => $church->id]);

        $role = Role::create(['church_id' => $church->id, 'name' => 'Test Role']);
        $role->permissions()->attach(
            Permission::whereIn('name', $permissionNames)->pluck('id')
        );
        $user->roles()->attach($role);

        return $user;
    }

    private function seedPermissions(): void
    {
        foreach (['members.view', 'members.create', 'members.edit', 'members.delete'] as $name) {
            Permission::firstOrCreate(['name' => $name], ['group' => 'members', 'label' => $name]);
        }
    }

    public function test_membership_number_is_generated_automatically_and_unique_per_church(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $first = Member::factory()->for($church, 'church')->create();
        $second = Member::factory()->for($church, 'church')->create();

        $this->assertNotNull($first->membership_number);
        $this->assertNotEquals($first->membership_number, $second->membership_number);
    }

    public function test_search_scope_matches_name_email_phone_or_membership_number(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        Member::factory()->for($church, 'church')->create(['full_name' => 'Jane Doe', 'email' => 'jane@example.com']);
        Member::factory()->for($church, 'church')->create(['full_name' => 'John Smith', 'email' => 'john@example.com']);

        $results = Member::search('Jane')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Jane Doe', $results->first()->full_name);
    }

    public function test_authorized_user_can_list_and_view_members_in_their_own_church(): void
    {
        $this->seedPermissions();
        $church = Church::factory()->create();
        $user = $this->userWithPermissions($church, ['members.view']);

        app()->instance('tenant.church_id', $church->id);
        $member = Member::factory()->for($church, 'church')->create();

        $this->actingAs($user)->get(route('members.index'))->assertOk();
        $this->actingAs($user)->get(route('members.show', $member))->assertOk();
    }

    public function test_user_without_permission_cannot_view_members(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]); // no roles/permissions

        $this->actingAs($user)->get(route('members.index'))->assertForbidden();
    }

    public function test_csv_import_creates_valid_rows_and_reports_invalid_ones_without_aborting_the_batch(): void
    {
        $church = Church::factory()->create();

        $csv = "full_name,email,membership_status\n"
            ."Valid Member,valid@example.com,active\n"
            .",missing-name@example.com,active\n" // invalid: full_name required
            ."Another Valid,another@example.com,not-a-real-status\n" // invalid: bad status
            ."Third Valid,third@example.com,active\n";

        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $csv);

        $result = (new ImportMembersFromCsv($church->id))->handle($path);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(2, $result['skipped']);
        $this->assertCount(2, $result['errors']);

        app()->instance('tenant.church_id', $church->id);
        $this->assertSame(2, Member::count());

        unlink($path);
    }

    public function test_csv_import_scopes_every_created_member_to_the_importing_church_only(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();

        $csv = "full_name,email\nSomeone,someone@example.com\n";
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $csv);

        (new ImportMembersFromCsv($churchA->id))->handle($path);
        unlink($path);

        app()->instance('tenant.church_id', $churchB->id);
        $this->assertCount(0, Member::all());

        app()->instance('tenant.church_id', $churchA->id);
        $this->assertCount(1, Member::all());
    }
}

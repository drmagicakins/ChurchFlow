<?php

namespace Tests\Feature;

use App\Domains\Members\Actions\SampleMembersSheet;
use App\Models\Church;
use App\Models\Member;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The spreadsheet round trip: download the sample sheet, fill it in the way a
 * church office would, upload it back.
 *
 * The first version of the sample download shipped without this test, and the
 * importer then rejected hand-typed values ("Female", "M", "12/03/1990") or
 * silently read the date as 3 December. Both were found by uploading a real
 * filled-in sheet, which is what this asserts.
 */
class MemberSheetImportTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::create(['church_id' => $church->id, 'name' => 'Test '.uniqid()]);
        $role->permissions()->attach(
            collect($permissions)->map(
                fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'x', 'label' => $n])->id
            )
        );
        $user->roles()->attach($role);
        $user->load('roles.permissions');

        return $user;
    }

    public function test_the_sample_sheet_carries_exactly_the_headers_the_importer_reads(): void
    {
        $user = $this->userWith(['members.view', 'members.create']);

        $response = $this->actingAs($user)->get(route('members.sample'));

        $response->assertOk();
        $this->assertSame(
            'attachment; filename="churchflow-members-sample.csv"',
            $response->headers->get('Content-Disposition')
        );

        $csv = $response->streamedContent();
        $firstLine = strtok(ltrim($csv, "\xEF\xBB\xBF"), "\n");

        $this->assertSame(
            implode(',', array_keys(SampleMembersSheet::COLUMNS)),
            trim($firstLine)
        );
    }

    public function test_a_hand_filled_sheet_imports_every_row(): void
    {
        $user = $this->userWith(['members.view', 'members.create']);

        // Exactly what Excel produces: a UTF-8 BOM, CRLF endings, and the
        // spelling people use on paper.
        $sheet = "\xEF\xBB\xBF"
            ."full_name,email,phone,gender,date_of_birth,date_joined,membership_status\r\n"
            ."Ada Nwosu,ada@example.com,,Female,12/03/1990,07/01/2024,Active\r\n"
            ."Big Eze,big@example.com,08000000000,M,1991-05-05,2023-02-02,Member\r\n";

        $response = $this->actingAs($user)->post(route('members.import'), [
            'file' => UploadedFile::fake()->createWithContent('members.csv', $sheet),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame(2, Member::count());
        $this->assertSame('female', Member::where('full_name', 'Ada Nwosu')->value('gender'));
        $this->assertSame('1990-03-12', Member::where('full_name', 'Ada Nwosu')->first()->date_of_birth->toDateString());
        $this->assertSame('active', Member::where('full_name', 'Big Eze')->value('membership_status'));
    }

    public function test_a_row_without_a_name_is_skipped_and_the_rest_still_import(): void
    {
        $user = $this->userWith(['members.view', 'members.create']);

        $sheet = "full_name,email\n"
            ."Good Row,good@example.com\n"
            .",noname@example.com\n";

        $response = $this->actingAs($user)->post(route('members.import'), [
            'file' => UploadedFile::fake()->createWithContent('members.csv', $sheet),
        ]);

        $response->assertRedirect();
        $this->assertSame(1, Member::count());
        $this->assertNotEmpty(session('import_errors'));
    }

    public function test_uploading_a_non_spreadsheet_returns_a_field_error_not_an_error_page(): void
    {
        $user = $this->userWith(['members.view', 'members.create']);

        $this->actingAs($user)
            ->post(route('members.import'), [
                'file' => UploadedFile::fake()->create('statement.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Member::count());
    }
}

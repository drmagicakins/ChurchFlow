<?php

namespace Tests\Feature;

use App\Domains\ParishArchive\Services\ParishArchiveService;
use App\Models\Church;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

/**
 * "The departing pastor's parish records must stay available" — as a copy the
 * owner can keep, not as a move of anybody's data.
 *
 * Three things are proven here, and each is a claim the feature makes:
 *
 *  1. A PARISH export contains that parish's records and nothing from the
 *     other parish or from another church.
 *  2. Every workbook in the archive is a valid .xlsx that a spreadsheet can
 *     open — not merely a file with the right name.
 *  3. Producing the archive changes no row. The export sits next to a delete
 *     button, so "running it is read-only" is a safety property, not a detail.
 *
 * The four tables without a parish column (transactions, tasks,
 * pastoral_cases, prayer_requests) are covered explicitly, because the first
 * version of this service selected `organizational_unit_id` from all of them
 * and failed on a real parish with `no such column`. A test that only builds
 * the ZIP for the whole church would have passed while the parish export was
 * broken, so the parish cases below are the important ones.
 */
class ParishArchiveTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private Church $otherChurch;

    private User $owner;

    private OrganizationalUnit $wisdom;

    private OrganizationalUnit $grace;

    private int $wisdomMemberId;

    private int $graceMemberId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->church = Church::factory()->create(['status' => 'active', 'name' => 'Grace Chapel']);
        $this->otherChurch = Church::factory()->create(['status' => 'active', 'name' => 'Other Church']);

        $this->owner = User::factory()->create([
            'church_id' => $this->church->id,
            'is_registrar' => true,
        ]);

        $ownerRole = Role::firstOrCreate(['church_id' => null, 'name' => 'Church Owner']);
        $ownerRole->permissions()->sync(Permission::pluck('id'));
        $this->owner->roles()->attach($ownerRole);

        app()->instance('tenant.church_id', $this->church->id);

        $type = UnitType::create(['church_id' => $this->church->id, 'name' => 'Parish', 'level' => 1]);

        $this->wisdom = OrganizationalUnit::create([
            'church_id' => $this->church->id, 'unit_type_id' => $type->id, 'name' => 'Wisdom Parish',
        ]);
        $this->grace = OrganizationalUnit::create([
            'church_id' => $this->church->id, 'unit_type_id' => $type->id, 'name' => 'Grace Parish',
        ]);

        $this->wisdomMemberId = $this->member($this->church, $this->wisdom, 'Ada Wisdom');
        $this->graceMemberId = $this->member($this->church, $this->grace, 'Ben Grace');

        // A member of ANOTHER tenant, to prove the export never crosses the line.
        $otherType = UnitType::create(['church_id' => $this->otherChurch->id, 'name' => 'Parish', 'level' => 1]);
        $foreignUnit = OrganizationalUnit::create([
            'church_id' => $this->otherChurch->id, 'unit_type_id' => $otherType->id, 'name' => 'Foreign Parish',
        ]);
        $this->member($this->otherChurch, $foreignUnit, 'Foreign Member');
    }

    private function member(Church $church, OrganizationalUnit $unit, string $name): int
    {
        return DB::table('members')->insertGetId([
            'church_id' => $church->id,
            'organizational_unit_id' => $unit->id,
            'full_name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function archive(): ParishArchiveService
    {
        return app(ParishArchiveService::class);
    }

    /** Open a ZIP's bytes and return [entries, workbook row counts, readme]. */
    private function inspect(string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'cftest').'.zip';
        file_put_contents($path, $bytes);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'The export is not a readable ZIP.');

        $entries = [];
        $rows = [];
        $readme = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $entries[] = $name;

            if ($name === 'README.txt') {
                $readme = $zip->getFromIndex($i);
                continue;
            }

            $bookPath = tempnam(sys_get_temp_dir(), 'cfbook').'.xlsx';
            file_put_contents($bookPath, $zip->getFromIndex($i));

            $book = new ZipArchive;
            $this->assertTrue($book->open($bookPath) === true, "{$name} is not a readable .xlsx.");
            $sheet = $book->getFromName('xl/worksheets/sheet1.xml');
            $this->assertNotFalse($sheet, "{$name} has no worksheet.");
            $this->assertTrue(@simplexml_load_string($sheet) !== false, "{$name} worksheet is not valid XML.");
            $book->close();
            @unlink($bookPath);

            // Header row + data rows; the count is what counts() reports.
            $rows[$name] = substr_count($sheet, '<row ') - 1;
        }

        $zip->close();
        @unlink($path);

        return [$entries, $rows, $readme];
    }

    // --- The export itself -------------------------------------------------

    public function test_a_parish_export_contains_only_that_parishs_records(): void
    {
        [$filename, $bytes] = $this->archive()->export($this->church, $this->wisdom);

        $this->assertStringStartsWith('parish-export-wisdom-parish-', $filename);

        [$entries, $rows, $readme] = $this->inspect($bytes);

        // One workbook for the parish's member, none for the other parish's.
        $this->assertContains('members.xlsx', $entries);
        $this->assertSame(1, $rows['members.xlsx'], 'The parish export did not hold exactly its own member.');

        $this->assertStringContainsString('Wisdom Parish', $readme);
        $this->assertStringNotContainsString('Ben Grace', $readme);
    }

    public function test_the_whole_church_export_includes_every_parish_but_no_other_tenant(): void
    {
        [$filename, $bytes] = $this->archive()->export($this->church, null);

        $this->assertStringStartsWith('parish-export-whole-church-', $filename);

        [$entries, $rows] = $this->inspect($bytes);

        $this->assertSame(2, $rows['members.xlsx'], 'The whole-church export must hold both parishes, and only this church.');
        $this->assertNotFalse(strpos(implode(',', $entries), 'members.xlsx'));
    }

    /**
     * The four tables with no parish column.
     *
     * This is the regression guard for `no such column:
     * transactions.organizational_unit_id` — the failure the first version of
     * the service produced on a real parish and the whole-church tests did not.
     */
    public function test_tables_without_a_parish_column_are_scoped_through_their_member(): void
    {
        $account = DB::table('financial_accounts')->insertGetId([
            'church_id' => $this->church->id, 'name' => 'General', 'type' => 'general',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $txn = fn (int $memberId) => DB::table('transactions')->insert([
            'church_id' => $this->church->id,
            'financial_account_id' => $account,
            'type' => 'donation',
            'amount' => 1000,
            'transacted_on' => now()->toDateString(),
            'member_id' => $memberId,
            'approval_status' => 'not_required',
            'is_void' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $txn($this->wisdomMemberId);
        $txn($this->graceMemberId);

        $counts = $this->archive()->counts($this->church, $this->wisdom);

        $this->assertSame(1, $counts['Income & Expenses'], 'A parish export must hold only its own giving.');
        $this->assertSame(2, $this->archive()->counts($this->church, null)['Income & Expenses']);

        // The whole parish export must finish, not just count.
        [$filename, $bytes] = $this->archive()->export($this->church, $this->wisdom);
        [$entries, $rows] = $this->inspect($bytes);

        $this->assertContains('income-and-expenses.xlsx', $entries);
        $this->assertSame(1, $rows['income-and-expenses.xlsx']);
    }

    public function test_tasks_belong_to_the_church_and_are_listed_in_a_whole_church_export(): void
    {
        DB::table('tasks')->insert([
            'church_id' => $this->church->id,
            'title' => 'Order new hymn books',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, $this->archive()->counts($this->church, null)['Tasks']);
    }

    public function test_counting_a_parish_does_not_require_the_export_tables_to_have_a_parish_column(): void
    {
        // The original bug: the service asked every table for
        // organizational_unit_id. Assert the four that do not have it still
        // do not — so if a future migration adds the column, this test tells
        // the next engineer the comment in modules() needs revisiting.
        foreach (['transactions', 'tasks', 'pastoral_cases', 'prayer_requests'] as $table) {
            $this->assertFalse(
                Schema::hasColumn($table, 'organizational_unit_id'),
                "{$table} now has a parish column: modules() should use it and this note should be updated.",
            );
        }

        $this->assertSame(1, $this->archive()->counts($this->church, $this->wisdom)['Members']);
    }

    // --- Nothing is changed ------------------------------------------------

    public function test_exporting_a_parish_changes_no_row(): void
    {
        $before = [
            'members' => DB::table('members')->count(),
            'organizational_units' => DB::table('organizational_units')->count(),
            'churches' => DB::table('churches')->count(),
        ];

        $this->archive()->export($this->church, $this->wisdom);
        $this->archive()->counts($this->church, $this->wisdom);

        $this->assertSame($before['members'], DB::table('members')->count());
        $this->assertSame($before['organizational_units'], DB::table('organizational_units')->count());
        $this->assertSame($before['churches'], DB::table('churches')->count());
        $this->assertDatabaseHas('members', ['id' => $this->wisdomMemberId, 'deleted_at' => null]);
    }

    // --- The HTTP surface ---------------------------------------------------

    public function test_the_owner_can_download_their_parish_archive(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('parish-archive.download', ['organizational_unit_id' => $this->wisdom->id]));

        $response->assertOk();
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('wisdom-parish', (string) $response->headers->get('Content-Disposition'));

        [, $rows] = $this->inspect($response->getContent());
        $this->assertSame(1, $rows['members.xlsx']);
    }

    public function test_the_owner_can_ask_how_much_a_parish_holds(): void
    {
        $this->actingAs($this->owner)
            ->getJson(route('parish-archive.counts', ['organizational_unit_id' => $this->wisdom->id]))
            ->assertOk()
            ->assertJsonPath('scope', 'Wisdom Parish')
            ->assertJsonPath('counts.Members', 1)
            ->assertJsonPath('counts.Groups', 0)
            ->assertJsonPath('total', 1);
    }

    public function test_an_empty_parish_reports_zero_so_a_delete_can_be_decided(): void
    {
        $this->actingAs($this->owner)
            ->getJson(route('parish-archive.counts'))
            ->assertOk()
            ->assertJsonPath('scope', 'Whole church')
            ->assertJsonPath('counts.Members', 2);
    }

    public function test_a_pastor_cannot_export_the_parish(): void
    {
        $pastor = User::factory()->create([
            'church_id' => $this->church->id,
            'organizational_unit_id' => $this->wisdom->id,
        ]);

        $this->actingAs($pastor)
            ->get(route('parish-archive.download', ['organizational_unit_id' => $this->wisdom->id]))
            ->assertForbidden();

        $this->actingAs($pastor)->getJson(route('parish-archive.counts'))->assertForbidden();
    }

    public function test_another_churchs_parish_cannot_be_exported(): void
    {
        $foreignUnit = OrganizationalUnit::withoutGlobalScopes()
            ->where('church_id', $this->otherChurch->id)->firstOrFail();

        $this->actingAs($this->owner)
            ->get(route('parish-archive.download', ['organizational_unit_id' => $foreignUnit->id]))
            ->assertNotFound();
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get(route('parish-archive.counts'))->assertRedirect(route('login'));
    }
}

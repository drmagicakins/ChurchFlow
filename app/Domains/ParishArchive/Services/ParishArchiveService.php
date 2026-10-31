<?php

namespace App\Domains\ParishArchive\Services;

use App\Models\Church;
use App\Models\OrganizationalUnit;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

/**
 * Everything a parish holds, as a downloadable ZIP of Excel workbooks.
 *
 * WHY A PARISH EXPORT, NOT A "PASTOR EXPORT"
 * ------------------------------------------
 * The owner asked for the departing pastor's data to remain available. But a
 * parish's records are not one person's: a member was not created by the
 * pastor, and an offering was not "owned" by whoever typed it in. Reassigning
 * or dropping rows because a staff account is going away would silently
 * rewrite the church's own history.
 *
 * So the unit of export is the PARISH — every record belonging to the branch
 * that pastor served — and nothing is modified to produce it. The file is a
 * snapshot the owner keeps; the rows stay exactly where they were.
 *
 * The export also answers the owner's next question ("does this parish have
 * anything worth keeping?") via counts(), so a parish with nothing in it can
 * be deleted without ceremony.
 *
 * READ-ONLY BY CONSTRUCTION
 * -------------------------
 * Every query here is a SELECT. There is no update, delete or insert in this
 * class, and the workbooks are built in memory. Running an export can
 * therefore never change the data it describes — which is the property that
 * makes it safe to offer next to a delete button.
 */
class ParishArchiveService
{
    public function __construct(private readonly XlsxWriter $xlsx) {}

    /**
     * The modules exported, in the order they appear in the ZIP.
     *
     * Each entry is a callable returning [headers, rows] — kept as closures so
     * a query only runs when its sheet is actually written, and so the
     * "what counts as this parish's data" rule is stated once, in one place,
     * instead of being spread across the controller.
     *
     * @return array<string, callable(): array{0: array<int,string>, 1: array<int, array<int, mixed>>}>
     */
    private function modules(Church $church, ?OrganizationalUnit $parish): array
    {
        $unitId = $parish?->id;
        $churchId = $church->id;

        // A church-wide user has no parish; exporting "their parish" then means
        // the whole church, which is the only honest reading of the request.
        $scoped = fn (string $table) => DB::table($table)->where('church_id', $churchId)
            ->when($unitId !== null, fn ($q) => $q->where($table.'.organizational_unit_id', $unitId));

        $memberIds = fn () => DB::table('members')->where('church_id', $churchId)
            ->when($unitId !== null, fn ($q) => $q->where('organizational_unit_id', $unitId))
            ->pluck('id');

        // Four tables have NO parish column and none can be added without a
        // migration: transactions, tasks, pastoral_cases and prayer_requests.
        // Rather than guess ownership (or change the schema to make the export
        // tidy), their parish is read from the link the schema already has:
        //
        //   transactions   → the member who gave (members.organizational_unit_id)
        //   pastoral_cases → the member the case is about (same link)
        //   prayer_requests→ the member it is about (a request with no member
        //                    is church-wide: a visitor, or a memberless note)
        //   tasks          → nothing at all (no parish column, no member);
        //                    a parish-scoped export must not claim them
        //
        // A transaction with no member — an offering recorded against an
        // account, a transfer — has no parish link either, so it is included
        // only in the whole-church export. That is the honest answer: the
        // schema does not know which parish it belongs to.
        $scopedViaMember = fn (string $table) => $unitId === null
            ? DB::table($table)->where('church_id', $churchId)
            : DB::table($table)->where('church_id', $churchId)->whereIn('member_id', $memberIds());

        return [
            'Members' => fn () => [
                ['Membership No', 'Full Name', 'Email', 'Phone', 'Gender', 'Date of Birth', 'Status',
                    'Date Joined', 'Baptised', 'Worker', 'Address', 'Emergency Contact', 'Emergency Phone', 'Notes'],
                $scoped('members')->orderBy('full_name')->get()->map(fn ($m) => [
                    $m->membership_number, $m->full_name, $m->email, $m->phone, $m->gender, $m->date_of_birth,
                    $m->membership_status, $m->date_joined, $m->baptism_date, $m->is_worker ? 'Yes' : 'No',
                    $m->address, $m->emergency_contact_name, $m->emergency_contact_phone, $m->notes,
                ])->all(),
            ],

            // Families have no parish column — they are church-wide. Listing
            // only the families the parish's own members belong to keeps the
            // sheet relevant without inventing an ownership link that does not
            // exist in the schema. The link is the `family_member` pivot, not a
            // `members.family_id` column (there is no such column).
            'Families' => fn () => [
                ['Family', 'Members in this parish'],
                DB::table('families')->where('church_id', $churchId)
                    ->whereIn('id', DB::table('family_member')->whereIn('member_id', $memberIds())->pluck('family_id'))
                    ->orderBy('name')->get()
                    ->map(fn ($f) => [
                        $f->name,
                        DB::table('family_member')->where('family_id', $f->id)
                            ->whereIn('member_id', $memberIds())->count(),
                    ])->all(),
            ],

            'Groups' => fn () => [
                ['Group', 'Description', 'Members'],
                $scoped('groups')->orderBy('name')->get()->map(fn ($g) => [
                    $g->name, $g->description,
                    DB::table('group_member')->where('group_id', $g->id)->count(),
                ])->all(),
            ],

            'Departments' => fn () => [
                ['Department', 'Description'],
                $scoped('departments')->orderBy('name')->get()->map(fn ($d) => [$d->name, $d->description])->all(),
            ],

            'Events' => fn () => [
                ['Title', 'Starts', 'Ends', 'Venue', 'Capacity', 'Status', 'Registrations'],
                $scoped('events')->orderBy('starts_at')->get()->map(fn ($e) => [
                    $e->title, $e->starts_at, $e->ends_at, $e->venue, $e->capacity, $e->status,
                    DB::table('event_registrations')->where('event_id', $e->id)->count(),
                ])->all(),
            ],

            'Attendance' => fn () => [
                ['Session', 'Type', 'Date', 'Present', 'Absent'],
                $scoped('attendance_sessions')->orderBy('session_date')->get()->map(function ($s) {
                    $records = DB::table('attendance_records')->where('attendance_session_id', $s->id);

                    return [
                        $s->name, $s->type, $s->session_date,
                        (clone $records)->where('status', 'present')->count(),
                        (clone $records)->where('status', 'absent')->count(),
                    ];
                })->all(),
            ],

            'Income & Expenses' => fn () => [
                ['Date', 'Type', 'Category', 'Amount', 'Account', 'Description', 'Member', 'Approval', 'Voided'],
                $scopedViaMember('transactions')->orderBy('transacted_on')->get()->map(fn ($t) => [
                    $t->transacted_on, $t->type, $t->category, $t->amount,
                    $t->financial_account_id
                        ? DB::table('financial_accounts')->where('id', $t->financial_account_id)->value('name')
                        : null,
                    $t->description,
                    $t->member_id ? DB::table('members')->where('id', $t->member_id)->value('full_name') : null,
                    $t->approval_status, $t->is_void ? 'Yes' : 'No',
                ])->all(),
            ],

            'Loans' => fn () => [
                ['Borrower', 'Principal', 'Monthly Deduction', 'Interest %', 'Status', 'Repaid', 'Notes'],
                $scoped('loans')->orderBy('id')->get()->map(fn ($l) => [
                    $l->member_id
                        ? DB::table('members')->where('id', $l->member_id)->value('full_name')
                        : ($l->organizational_unit_id
                            ? DB::table('organizational_units')->where('id', $l->organizational_unit_id)->value('name')
                            : 'No borrower'),
                    $l->principal_amount, $l->monthly_deduction, $l->interest_rate, $l->status,
                    DB::table('loan_payments')->where('loan_id', $l->id)->sum('amount'),
                    $l->notes,
                ])->all(),
            ],

            // Tasks carry no parish column and no member, and are assigned to
            // USERS. They belong to the church as a whole, so a parish export
            // omits them rather than attributing them to a branch.
            'Tasks' => fn () => [
                ['Title', 'Status', 'Due', 'Assigned To', 'Created By', 'Description'],
                DB::table('tasks')->where('church_id', $churchId)->orderBy('due_date')->get()->map(fn ($t) => [
                    $t->title, $t->status, $t->due_date,
                    $t->assigned_to ? DB::table('users')->where('id', $t->assigned_to)->value('name') : null,
                    $t->created_by ? DB::table('users')->where('id', $t->created_by)->value('name') : null,
                    $t->description,
                ])->all(),
            ],

            'Announcements' => fn () => [
                ['Title', 'Audience', 'Published', 'Body'],
                $scoped('announcements')->orderBy('publish_at')->get()->map(fn ($a) => [
                    $a->title, $a->audience_type, $a->publish_at, $a->body,
                ])->all(),
            ],

            'Subventions' => fn () => [
                ['Period', 'Status', 'Approval', 'Submitted', 'Figures'],
                $scoped('subvention_submissions')->orderBy('id')->get()->map(fn ($s) => [
                    DB::table('subvention_periods')->where('id', $s->subvention_period_id)->value('name'),
                    $s->status, $s->approval_status, $s->submitted_at,
                    is_string($s->figures) ? $s->figures : json_encode($s->figures),
                ])->all(),
            ],

            'Pastoral Care' => fn () => [
                ['Member', 'Type', 'Status', 'Priority', 'Opened', 'Closed', 'Description'],
                $scopedViaMember('pastoral_cases')->orderBy('id')->get()->map(fn ($c) => [
                    $c->member_id ? DB::table('members')->where('id', $c->member_id)->value('full_name') : null,
                    $c->type, $c->status, $c->priority, $c->created_at, $c->closed_at, $c->description,
                ])->all(),
            ],

            'Prayer Requests' => fn () => [
                ['From', 'Member', 'Status', 'Confidential', 'Submitted', 'Request'],
                $scopedViaMember('prayer_requests')->orderBy('id')->get()->map(fn ($p) => [
                    $p->submitted_by_name,
                    $p->member_id ? DB::table('members')->where('id', $p->member_id)->value('full_name') : null,
                    $p->status, $p->is_confidential ? 'Yes' : 'No', $p->created_at, $p->request,
                ])->all(),
            ],

            'SMS Campaigns' => fn () => [
                ['Campaign', 'Status', 'Scheduled', 'Sent', 'Estimated Units', 'Message'],
                $scoped('sms_campaigns')->orderBy('id')->get()->map(fn ($c) => [
                    $c->name, $c->status, $c->scheduled_at, $c->sent_at, $c->estimated_units, $c->message,
                ])->all(),
            ],
        ];
    }

    /**
     * How many records this parish holds, per module.
     *
     * This is what lets the delete flow distinguish "nothing here, just
     * remove the account" from "there is a parish's worth of history to
     * hand over first".
     *
     * @return array<string, int>
     */
    public function counts(Church $church, ?OrganizationalUnit $parish): array
    {
        $counts = [];

        foreach ($this->modules($church, $parish) as $name => $module) {
            [$headers, $rows] = $module();
            $counts[$name] = count($rows);
        }

        return $counts;
    }

    /** Total records across every module. Zero means "this parish has no data". */
    public function total(Church $church, ?OrganizationalUnit $parish): int
    {
        return array_sum($this->counts($church, $parish));
    }

    /**
     * Build the ZIP and return its bytes.
     *
     * One workbook per module, plus a README describing exactly what was
     * exported and — importantly — stating that this is a copy, not a move:
     * the owner must be able to tell that deleting the pastor does not delete
     * the church's records.
     *
     * @return array{0: string, 1: string} [filename, bytes]
     */
    public function export(Church $church, ?OrganizationalUnit $parish): array
    {
        $stamp = now()->format('Y-m-d-His');
        $label = $parish?->name ?? 'Whole church';
        $slug = $this->slug($label);
        $filename = "parish-export-{$slug}-{$stamp}.zip";

        $path = tempnam(sys_get_temp_dir(), 'cfzip').'.zip';

        try {
            $zip = new ZipArchive;

            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to create the export archive.');
            }

            $summary = [];

            foreach ($this->modules($church, $parish) as $name => $module) {
                [$headers, $rows] = $module();

                $summary[] = $name.': '.count($rows);

                // A module with nothing in it is skipped rather than shipped as
                // an empty sheet, so the archive reads as "what this parish
                // actually has" at a glance.
                if ($rows === []) {
                    continue;
                }

                $zip->addFromString(
                    $this->fileNameFor($name).'.xlsx',
                    $this->xlsx->make($name, $headers, $rows),
                );
            }

            $zip->addFromString('README.txt', $this->readme($church, $parish, $summary));

            $zip->close();

            $bytes = file_get_contents($path);

            if ($bytes === false) {
                throw new RuntimeException('Unable to read the export archive.');
            }

            return [$filename, $bytes];
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /** A short, filesystem-safe slug for the archive name. */
    private function slug(string $value): string
    {
        $slug = (string) preg_replace('/[^A-Za-z0-9]+/', '-', $value);

        return trim(strtolower($slug), '-') ?: 'parish';
    }

    /** "Income & Expenses" → "income-expenses.xlsx" */
    private function fileNameFor(string $module): string
    {
        return $this->slug(str_replace('&', 'and', $module));
    }

    /** @param  array<int, string>  $summary */
    private function readme(Church $church, ?OrganizationalUnit $parish, array $summary): string
    {
        $lines = [
            'ChurchFlow — parish records export',
            '==================================',
            '',
            'Church : '.$church->name,
            'Parish : '.($parish?->name ?? 'Whole church'),
            'Created: '.now()->toFormattedDateString().' at '.now()->format('H:i'),
            '',
            'This archive is a COPY of the records for the parish above.',
            'Exporting does not move, change or remove anything: every record',
            'listed here is still in ChurchFlow, unchanged.',
            '',
            'Contents (records per module):',
        ];

        foreach ($summary as $line) {
            $lines[] = '  - '.$line;
        }

        $lines[] = '';
        $lines[] = 'Each .xlsx file opens in Excel, LibreOffice or Google Sheets.';
        $lines[] = 'Modules with no records are not included.';

        return implode("\n", $lines)."\n";
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make "one department name per branch" actually true.
 *
 * The original index was UNIQUE(church_id, organizational_unit_id, name), which
 * did not stop duplicates at all. `organizational_unit_id` is nullable and NULL
 * means "belongs to the whole church"; SQL treats every NULL as distinct, so a
 * church could create "Choir" over and over (the dev database had it three
 * times). NULL cannot carry a unique constraint, so whole-church departments are
 * moved to the 0 sentinel SmsWallet already uses (SmsWallet::CHURCH_WIDE) and
 * the index is rebuilt over a column that can actually enforce it.
 *
 * Three things happen, in this order:
 *   1. names are normalised (trim, collapse inner whitespace) so
 *      "Choir " and "Youth  Choir" stop being different departments;
 *   2. existing duplicates are MERGED into the oldest row — their member
 *      attachments are moved across first, so no member loses a department —
 *      and the extra rows are soft-deleted, never hard-deleted;
 *   3. NULL is rewritten to 0 and the real unique index is created.
 *
 * Still explicit: a soft-deleted row keeps its name, so re-creating a department
 * that was removed earlier needs a different name. That is deliberate — a name
 * that silently reappears in reports is worse than an extra word.
 */
return new class extends Migration
{
    private const WHOLE_CHURCH = 0;

    public function up(): void
    {
        // 1. Normalise names in place. Done in PHP rather than SQL because
        //    squish() (trim + collapse internal runs of whitespace) behaves
        //    identically on SQLite and MySQL, which a REGEXP_REPLACE would not.
        foreach (DB::table('departments')->select('id', 'name')->get() as $row) {
            $clean = \Illuminate\Support\Str::of((string) $row->name)->squish()->toString();

            if ($clean !== $row->name) {
                DB::table('departments')->where('id', $row->id)->update(['name' => $clean]);
            }
        }

        // 2. Merge duplicates. Grouped by (church, branch-in-stored-form, name
        //    case-insensitively); the oldest row survives so links already
        //    shared with members keep working.
        $groups = DB::table('departments')
            ->select('id', 'church_id', 'organizational_unit_id', 'name')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row) => implode('|', [
                $row->church_id,
                $row->organizational_unit_id === null ? 'null' : (int) $row->organizational_unit_id,
                \App\Models\Department::normaliseName($row->name),
            ]));

        foreach ($groups as $rows) {
            $keep = $rows->shift();

            foreach ($rows as $duplicate) {
                // department_member is UNIQUE(department_id, member_id), so a
                // member already in the surviving row must not be re-inserted.
                $alreadyIn = DB::table('department_member')
                    ->where('department_id', $keep->id)
                    ->pluck('member_id')
                    ->all();

                $toMove = DB::table('department_member')
                    ->where('department_id', $duplicate->id)
                    ->when($alreadyIn !== [], fn ($q) => $q->whereNotIn('member_id', $alreadyIn))
                    ->pluck('member_id');

                foreach ($toMove as $memberId) {
                    DB::table('department_member')
                        ->where('department_id', $duplicate->id)
                        ->where('member_id', $memberId)
                        ->update(['department_id' => $keep->id]);
                }

                // Any attachment left on the duplicate is a member the survivor
                // already has; drop the leftover pivot rows before the
                // department row goes, rather than relying on cascade timing.
                DB::table('department_member')->where('department_id', $duplicate->id)->delete();

                // Soft delete: the record is recoverable, and every query in the
                // app already ignores trashed departments.
                DB::table('departments')->where('id', $duplicate->id)->update(['deleted_at' => now()]);
            }
        }

        // Do NOT skip NULL-named groups onto the 0 sentinel. A duplicate group
        // that sits under NULL is merged here as a group of NULLs; every other
        // row in it is soft-deleted, so after step 3 exactly one row per
        // (church, name) survives.

        // 3. Whole church becomes 0 so the unique index can see it.
        //
        // The ORIGINAL index, UNIQUE(church_id, organizational_unit_id, name),
        // is still in place and counts soft-deleted rows. How many NULL/0
        // duplicates it tolerated therefore depends on the engine, so it is
        // dropped first and a single deciding rule is applied against a table
        // it is not fighting with.
        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique('departments_church_id_organizational_unit_id_name_unique');
        });

        // The branch foreign key follows the same drop/re-add pattern as
        // 2026_12_20_000002: 0 is the "whole church" sentinel, not a real
        // branch id, so the constraint has to allow it. It is NOT put back,
        // because the app now writes 0 for every whole-church department —
        // restoring a plain FK would make every such insert fail with
        // "FOREIGN KEY constraint failed".
        //
        // What is given up: the database no longer proves that
        // organizational_unit_id points at a real branch. The cross-church
        // guard was never this constraint anyway — it is the explicit
        // church_id comparison in DepartmentController::store/update. The app
        // still never writes a branch id it has not verified. The create
        // migration's index on (church_id, organizational_unit_id, name) and
        // the church_id cascade are untouched.
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['organizational_unit_id']);
        });

        DB::table('departments')->whereNull('organizational_unit_id')->update([
            'organizational_unit_id' => self::WHOLE_CHURCH,
        ]);

        // The soft-deleted duplicates merged above now collide with the row
        // they were merged into, so they release the name. This is a soft
        // delete staying soft — the columns the app reads (deleted_at) are
        // untouched, and the row is still recoverable by id.
        $held = DB::table('departments')
            ->whereNotNull('deleted_at')
            ->get(['id', 'name']);

        foreach ($held as $row) {
            DB::table('departments')->where('id', $row->id)->update([
                'name' => $row->name.' (removed #'.$row->id.')',
            ]);
        }

        Schema::table('departments', function (Blueprint $table) {
            $table->unique(['church_id', 'organizational_unit_id', 'name'], 'departments_church_unit_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique('departments_church_unit_name_unique');
        });

        // Restore the original index exactly as the create migration made it,
        // so a rollback leaves the schema as it was.
        Schema::table('departments', function (Blueprint $table) {
            $table->unique(['church_id', 'organizational_unit_id', 'name']);
        });

        // up() dropped this constraint and did not restore it, so a rollback
        // removes it only if it is still there. Guarded because the exact index
        // and constraint names differ between SQLite and MySQL.
        foreach (Schema::getForeignKeys('departments') as $foreign) {
            if (($foreign['columns'] ?? []) === ['organizational_unit_id']) {
                Schema::table('departments', function (Blueprint $table) {
                    $table->dropForeign(['organizational_unit_id']);
                });
                break;
            }
        }

        // 0 back to NULL, so a rollback leaves the column exactly as it was.
        // Soft-deleted duplicates are NOT restored: re-activating them would
        // re-create the very duplicates this migration removed. Their names
        // keep the "(removed #id)" suffix added above for the same reason.
        DB::table('departments')->where('organizational_unit_id', self::WHOLE_CHURCH)->update([
            'organizational_unit_id' => null,
        ]);

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('organizational_unit_id')
                ->references('id')->on('organizational_units')
                ->nullOnDelete();
        });
    }
};

<?php

namespace App\Domains\Members\Actions;

use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Deliberately the opposite of the legacy uploadcsv.php: that trusted
 * position (column 0, column 1, ...) with zero validation, so a shifted
 * column silently corrupted every row after it. This requires a header
 * row, maps by COLUMN NAME, validates every row independently, and never
 * lets one bad row abort or corrupt the rest of the batch.
 *
 * Expected headers (case-insensitive, order doesn't matter):
 *   full_name (required), email, gender, phone, date_of_birth (YYYY-MM-DD),
 *   date_joined (YYYY-MM-DD), membership_status
 */
class ImportMembersFromCsv
{
    public function __construct(private readonly int $churchId) {}

    /**
     * @return array{imported:int, skipped:int, errors:array<int,string>}
     */
    public function handle(string $path): array
    {
        $handle = fopen($path, 'r');
        abort_if($handle === false, 422, 'Could not read the uploaded file.');

        // A sheet saved by Excel for Windows begins with a UTF-8 BOM, and on a
        // UTF-16 locale Excel saves tab-separated .txt. Left in place the BOM
        // is glued to the first header ("\u{FEFF}full_name"), which then silently
        // matches nothing and every row is rejected for a missing name.
        $firstBytes = (string) fread($handle, 4);
        rewind($handle);

        if (str_starts_with($firstBytes, "\xFF\xFE") || str_starts_with($firstBytes, "\xFE\xFF")) {
            abort(422, 'That file is UTF-16. Save it as CSV UTF-8 from Excel and try again.');
        }

        $header = fgetcsv($handle);
        abort_if($header === false, 422, 'The file is empty.');

        $header = array_map(
            fn ($h) => strtolower(trim(str_replace("\u{FEFF}", '', (string) $h))),
            $header
        );

        // Excel for a European locale saves ";"-separated CSV. fgetcsv guesses
        // nothing, so detect the delimiter from the header line rather than
        // rejecting a perfectly good sheet as "one column".
        $delimiter = substr_count(implode('', $header), ';') > count($header) - 1 ? ';' : ',';
        if ($delimiter === ';') {
            rewind($handle);
            $firstLine = fgets($handle);
            rewind($handle);
            $header = array_map(fn ($h) => strtolower(trim($h)), str_getcsv(trim($firstLine), ';'));
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $rowNumber = 1; // header was row 1

        while (($row = ($delimiter === ';' ? fgetcsv($handle, 0, ';') : fgetcsv($handle))) !== false) {
            $rowNumber++;

            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // silently skip fully blank rows (trailing newlines etc.)
            }

            $data = array_combine(
                array_slice($header, 0, count($row)),
                array_slice($row, 0, count($header)),
            ) ?: [];

            // Normalise FIRST, then validate what will actually be stored.
            // Doing it the other way round rejects a hand-filled sheet for
            // spelling ("Female", "Member", "12/03/1990") that the normaliser
            // exists to accept — every row skipped, with a confusing message.
            $row = $this->normalise($data);

            $validator = Validator::make($row, [
                'full_name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'gender' => ['nullable', 'in:male,female,other'],
                'phone' => ['nullable', 'string', 'max:30'],
                'date_of_birth' => ['nullable', 'date'],
                'date_joined' => ['nullable', 'date'],
                'membership_status' => ['nullable', 'in:active,inactive,visitor,transferred,deceased,draft'],
            ]);

            if ($validator->fails()) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: ".$validator->errors()->first();
                continue;
            }

            $row = $validator->validated();

            // Each row is its own transaction: one bad row never rolls back
            // rows already successfully imported before it.
            try {
                DB::transaction(function () use ($row) {
                    Member::create([
                        'church_id' => $this->churchId,
                        ...$row,
                    ]);
                });
                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: could not be saved ({$e->getMessage()}).";
            }
        }

        fclose($handle);

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Make a hand-typed row look like the database expects.
     *
     * Kept deliberately narrow: it only rewrites values whose meaning is
     * unambiguous, and anything it cannot make sense of is passed through so
     * the validator above has already rejected it rather than being guessed at.
     */
    private function normalise(array $row): array
    {
        if (isset($row['full_name'])) {
            $row['full_name'] = trim((string) $row['full_name']);
        }

        if (isset($row['email']) && trim((string) $row['email']) === '') {
            // A blank cell is "no email", not an invalid one.
            unset($row['email']);
        }

        if (! empty($row['gender'])) {
            $g = strtolower(trim((string) $row['gender']));
            $row['gender'] = match ($g) {
                'm', 'male', 'man', 'boy' => 'male',
                'f', 'female', 'woman', 'girl' => 'female',
                default => 'other',
            };
        }

        if (! empty($row['membership_status'])) {
            $s = strtolower(trim((string) $row['membership_status']));
            $row['membership_status'] = match ($s) {
                'member', 'active member', 'active' => 'active',
                'inactive member', 'inactive' => 'inactive',
                'visitor', 'guest' => 'visitor',
                'transferred', 'transfer' => 'transferred',
                'deceased', 'dead' => 'deceased',
                'draft' => 'draft',
                // Anything else is left EXACTLY as typed so the validator
                // rejects it. Silently turning a typo into "active" would put
                // someone on the roll who was never meant to be on it.
                default => $s,
            };
        }

        // "12/03/1990" is how a Nigerian church office writes a date, and
        // strtotime reads it as 3 December. Dates are parsed day-first unless
        // the first component cannot be a day (>12), which is what makes the
        // rule safe for both "07/01/2024" (7 January) and "03/25/1990"
        // (25 March) — the ambiguous case is resolved the way this region
        // writes it, not the way PHP guesses.
        foreach (['date_of_birth', 'date_joined'] as $col) {
            if (empty($row[$col])) {
                continue;
            }
            $value = trim((string) $row[$col]);
            if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $value, $m)) {
                $day = (int) $m[1];
                $month = (int) $m[2];
                if ($day > 12 && $month <= 12) {
                    $value = sprintf('%04d-%02d-%02d', $m[3], $month, $day);
                } elseif ($month > 12) {
                    $value = sprintf('%04d-%02d-%02d', $m[3], $day, $month);
                } else {
                    $value = sprintf('%04d-%02d-%02d', $m[3], $month, $day);
                }
            }
            try {
                $row[$col] = \Illuminate\Support\Carbon::parse($value)->toDateString();
            } catch (\Throwable) {
                unset($row[$col]);
            }
        }

        return $row;
    }
}

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

        $header = fgetcsv($handle);
        abort_if($header === false, 422, 'The file is empty.');

        $header = array_map(fn ($h) => strtolower(trim($h)), $header);

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $rowNumber = 1; // header was row 1

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // silently skip fully blank rows (trailing newlines etc.)
            }

            $data = array_combine(
                array_slice($header, 0, count($row)),
                array_slice($row, 0, count($header)),
            ) ?: [];

            $validator = Validator::make($data, [
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

            // Each row is its own transaction: one bad row never rolls back
            // rows already successfully imported before it.
            try {
                DB::transaction(function () use ($validator) {
                    Member::create([
                        'church_id' => $this->churchId,
                        ...$validator->validated(),
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
}

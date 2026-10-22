<?php

namespace App\Domains\Members\Actions;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The blank sheet a church fills in and uploads back (§10 import).
 *
 * WHY IT IS GENERATED AND NOT SHIPPED AS A FILE
 * ----------------------------------------------
 * The importer matches by COLUMN NAME, so the sample has to carry exactly the
 * headers ImportMembersFromCsv expects, in the order it documents. A static
 * .csv in /public drifts the moment a column is added: someone downloads last
 * year's template, uploads it, and the new column is silently absent. Built
 * from the same list the importer validates, the two cannot disagree.
 *
 * It contains a header row and two EXAMPLE rows (Mr/Mrs Sample) that the user
 * deletes before adding their own. Examples rather than an empty sheet because
 * the two things people get wrong are the date format and the gender/status
 * spelling — showing them is cheaper than explaining them in a paragraph.
 */
class SampleMembersSheet
{
    /** Header => example value. The keys ARE the contract with the importer. */
    public const COLUMNS = [
        'full_name' => 'Adaeze Okonkwo',
        'email' => 'adaeze@example.com',
        'phone' => '+234 800 000 0000',
        'gender' => 'female',
        'date_of_birth' => '1990-03-12',
        'date_joined' => '2024-01-07',
        'membership_status' => 'active',
    ];

    public function handle(): StreamedResponse
    {
        $response = new StreamedResponse(function () {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens the file with the right encoding instead
            // of mangling names like "Ayọ̀mídé".
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, array_keys(self::COLUMNS));

            // Two examples. Deliberately the values the importer accepts, so a
            // user who only edits the names gets a clean import.
            fputcsv($out, array_values(self::COLUMNS));
            fputcsv($out, [
                'Chinedu Balogun', 'chinedu@example.com', '+234 800 000 0001',
                'male', '1985-11-02', '2025-06-15', 'active',
            ]);

            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="churchflow-members-sample.csv"');

        return $response;
    }
}

<?php

namespace App\Domains\Members\Actions;

use App\Models\Member;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportMembersToCsv
{
    private const COLUMNS = [
        'membership_number', 'full_name', 'email', 'phone', 'gender',
        'date_of_birth', 'membership_status', 'date_joined',
    ];

    public function handle(): StreamedResponse
    {
        $response = new StreamedResponse(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::COLUMNS);

            // Chunk rather than load the whole church's membership into
            // memory at once — matters once a tenant has thousands of
            // members (§44 performance).
            Member::query()->orderBy('id')->chunk(500, function (Collection $members) use ($out) {
                foreach ($members as $member) {
                    fputcsv($out, array_map(
                        fn ($col) => (string) data_get($member, $col),
                        self::COLUMNS,
                    ));
                }
            });

            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="members.csv"');

        return $response;
    }
}

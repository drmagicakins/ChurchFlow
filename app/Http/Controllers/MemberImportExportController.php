<?php

namespace App\Http\Controllers;

use App\Domains\Members\Actions\ExportMembersToCsv;
use App\Domains\Members\Actions\ImportMembersFromCsv;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberImportExportController extends Controller
{
    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', Member::class);

        return (new ExportMembersToCsv())->handle();
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorize('create', Member::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $result = (new ImportMembersFromCsv($request->user()->church_id))
            ->handle($request->file('file')->getRealPath());

        return back()->with([
            'status' => "Imported {$result['imported']} member(s), skipped {$result['skipped']}.",
            'import_errors' => array_slice($result['errors'], 0, 20), // don't flood the session
        ]);
    }
}

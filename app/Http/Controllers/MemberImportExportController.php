<?php

namespace App\Http\Controllers;

use App\Domains\Members\Actions\ExportMembersToCsv;
use App\Domains\Members\Actions\ImportMembersFromCsv;
use App\Domains\Members\Actions\SampleMembersSheet;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
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

        // The upload has to come back as a FIELD error, not an abort: the form
        // lives in a collapsible panel, and a 422/500 page replaced the whole
        // member list with an error the user could not act on. A validation
        // failure redirects back with `members` framed on the panel instead.
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ], [
            'file.mimes' => 'Upload a .csv file (Excel: Save as → CSV UTF-8).',
            'file.max' => 'That file is larger than 10 MB. Split it into smaller batches.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $result = (new ImportMembersFromCsv($request->user()->church_id))
            ->handle($request->file('file')->getRealPath());

        return back()->with([
            'status' => "Imported {$result['imported']} member(s), skipped {$result['skipped']}.",
            'import_errors' => array_slice($result['errors'], 0, 20), // don't flood the session
        ]);
    }

    /** The blank sheet to fill in and upload back — see SampleMembersSheet. */
    public function sample(): StreamedResponse
    {
        $this->authorize('create', Member::class);

        return (new SampleMembersSheet())->handle();
    }
}

<?php

namespace App\Http\Controllers;

use App\Domains\Files\Services\FileUploadService;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MemberPhotoController extends Controller
{
    public function __construct(private readonly FileUploadService $uploads) {}

    public function store(Request $request, Member $member): RedirectResponse
    {
        $this->authorize('update', $member);

        $request->validate(['photo' => ['required', 'file', 'image', 'max:5120']]);

        $uploaded = $this->uploads->storeImage($request->file('photo'), $member->church, $request->user(), $member);

        $member->update(['photo_upload_id' => $uploaded->id]);

        return back()->with('status', 'Photo updated.');
    }
}

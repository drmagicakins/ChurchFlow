<?php

namespace App\Http\Controllers;

use App\Domains\Communication\Jobs\SendAnnouncementEmailsJob;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Announcement::class);

        $announcements = Announcement::query()
            ->published()
            ->latest('publish_at')
            ->paginate(20);

        return view('announcements.index', compact('announcements'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Announcement::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'audience_type' => ['required', 'in:church,branch,department,group'],
            'organizational_unit_id' => ['required_if:audience_type,branch', 'nullable', 'exists:organizational_units,id'],
            'department_id' => ['required_if:audience_type,department', 'nullable', 'exists:departments,id'],
            'group_id' => ['required_if:audience_type,group', 'nullable', 'exists:groups,id'],
            'publish_at' => ['nullable', 'date'],
        ]);

        $data['created_by'] = $request->user()->id;
        $announcement = Announcement::create($data);

        SendAnnouncementEmailsJob::dispatch($announcement->id);

        return redirect()->route('announcements.show', $announcement);
    }

    public function show(Announcement $announcement): View
    {
        $this->authorize('view', $announcement);

        return view('announcements.show', compact('announcement'));
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $this->authorize('delete', $announcement);
        $announcement->delete();

        return redirect()->route('announcements.index');
    }
}

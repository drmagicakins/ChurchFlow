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

        // §19 targeting: the compose form needs the actual audiences to offer.
        // These are tenant-scoped by the global scope, so this is never another
        // church's branch/department/group. Loaded eagerly here rather than
        // queried inside the Blade template so the form's dependencies are
        // explicit and the page fails loudly here, not in a view.
        // A whole-church department carries no parish, so the tenant scope alone
        // would hide it from a parish pastor — visibleTo() keeps it offerable.
        // OrganizationalUnit has no parish column, so TenantScope does not
        // narrow branches — a parish pastor must be offered only their own.
        $branches = \App\Models\OrganizationalUnit::query()
            ->when($request->user()->isParishScoped(), fn ($q) => $q->whereKey($request->user()->organizational_unit_id))
            ->orderBy('name')
            ->get();
        $departments = \App\Models\Department::query()->visibleTo($request->user())->orderBy('name')->get();
        $groups = \App\Models\Group::query()->orderBy('name')->get();

        return view('announcements.index', compact('announcements', 'branches', 'departments', 'groups'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Announcement::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            // 'church_wide' — not 'church'. The announcements form offers
            // church_wide and Announcement::isVisibleToMember() reads
            // church_wide, so validating the older 'church' spelling rejected
            // the value the UI actually posts: every church-wide announcement
            // failed validation with a 422 on a field the user had just chosen
            // from a dropdown.
            'audience_type' => ['required', 'in:church_wide,branch,department,group'],
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

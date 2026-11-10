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

        // visibleTo() keeps church-wide announcements (branch NULL) in a parish
        // pastor's list — TenantScope alone hides them, because this table has
        // an organizational_unit_id column. See Announcement::scopeVisibleTo().
        $announcements = Announcement::query()
            ->visibleTo($request->user())
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
        $groups = \App\Models\Group::query()->visibleTo($request->user())->orderBy('name')->get();

        // The audiences this user may actually post to. A parish pastor may
        // reach their own branch, a department and a group; only the church
        // owner may address the entire church or another branch. The form is
        // built from this list so it cannot offer a choice the controller will
        // refuse — and the controller still refuses it if one is posted anyway.
        //
        // 'Entire church' is therefore absent for a parish pastor. They still
        // SEE church-wide announcements in the list below; this only governs
        // what they may broadcast.
        $audiences = $request->user()->isParishScoped() ? [] : ['church_wide' => 'Entire church'];

        if ($branches->isNotEmpty()) {
            $audiences['branch'] = 'A specific branch';
        }

        if ($departments->isNotEmpty()) {
            $audiences['department'] = 'A department';
        }

        if ($groups->isNotEmpty()) {
            $audiences['group'] = 'A group';
        }

        return view('announcements.index', compact('announcements', 'branches', 'departments', 'groups', 'audiences'));
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

        $user = $request->user();

        // §19 audience rules. A parish pastor may reach their own branch, a
        // department and a group — but NOT the whole church, and not another
        // branch. Only the church owner (super admin) may address the entire
        // church or any branch. Enforced here, not only by hiding options in
        // the form: a hidden <option> is presentation, and only the server
        // check survives a crafted POST.
        //
        // Note this is about POSTING, not about reading. A parish pastor still
        // RECEIVES church-wide announcements in their list — that is
        // scopeVisibleTo()'s job, and it is deliberately unaffected by this
        // rule. Refusing to let someone broadcast to the whole church is not
        // the same as hiding the church's own notices from them.
        if ($data['audience_type'] === 'church_wide' && $user->isParishScoped()) {
            return back()
                ->withInput()
                ->withErrors(['audience_type' => 'Only the church owner can post to the entire church.']);
        }

        if ($data['audience_type'] === 'branch') {
            $target = (int) ($data['organizational_unit_id'] ?? 0);

            if ($user->isParishScoped() && $target !== (int) $user->organizational_unit_id) {
                return back()
                    ->withInput()
                    ->withErrors(['organizational_unit_id' => 'You can only post to your own branch.']);
            }
        }

        // A department or group must belong to this church. The `exists` rule
        // above only proves the id is real somewhere; without this, a crafted
        // POST could target another tenant's department by id.
        if ($data['audience_type'] === 'department' && ! empty($data['department_id'])) {
            $owns = \App\Models\Department::query()->whereKey($data['department_id'])->exists();
            if (! $owns) {
                return back()->withInput()->withErrors(['department_id' => 'That department was not found.']);
            }
        }

        if ($data['audience_type'] === 'group' && ! empty($data['group_id'])) {
            $owns = \App\Models\Group::query()->whereKey($data['group_id'])->exists();
            if (! $owns) {
                return back()->withInput()->withErrors(['group_id' => 'That group was not found.']);
            }
        }

        // Only the field that matches the chosen audience is stored. Without
        // this, a stale value left in a hidden select (the form keeps every
        // field in the DOM) would be written alongside the real audience and
        // the row would claim two targets at once.
        $data['organizational_unit_id'] = $data['audience_type'] === 'branch' ? ($data['organizational_unit_id'] ?? null) : null;
        $data['department_id'] = $data['audience_type'] === 'department' ? ($data['department_id'] ?? null) : null;
        $data['group_id'] = $data['audience_type'] === 'group' ? ($data['group_id'] ?? null) : null;

        $data['created_by'] = $user->id;
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

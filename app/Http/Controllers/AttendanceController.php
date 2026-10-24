<?php

namespace App\Http\Controllers;

use App\Domains\Attendance\Services\AttendanceAnalyticsService;
use App\Models\AttendanceSession;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', AttendanceSession::class);
        $sessions = AttendanceSession::query()->with(['organizationalUnit', 'department', 'group', 'event'])->orderByDesc('session_date')->paginate(25);

        // The create panel is "well populated": §19/§12 lets a session be tied to
        // a branch, event, department or group, and the controller validates
        // exactly those ids. Offering them here (rather than a bare name+date
        // form) is what makes the type dropdown meaningful.
        return view('attendance.index', [
            'sessions' => $sessions,
            'branches' => \App\Models\OrganizationalUnit::query()
                ->when(request()->user()->isParishScoped(), fn ($q) => $q->whereKey(request()->user()->organizational_unit_id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'events' => \App\Models\Event::query()->orderByDesc('starts_at')->limit(50)->get(['id', 'title', 'starts_at']),
            'departments' => \App\Models\Department::query()->visibleTo(request()->user())->orderBy('name')->get(['id', 'name']),
            'groups' => \App\Models\Group::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', AttendanceSession::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:service,event,department,group'],
            'session_date' => ['required', 'date'],
            'organizational_unit_id' => ['nullable', 'exists:organizational_units,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'group_id' => ['nullable', 'exists:groups,id'],
        ]);

        $session = AttendanceSession::create($data);

        return redirect()->route('attendance.show', $session);
    }

    public function show(AttendanceSession $session, AttendanceAnalyticsService $analytics): View
    {
        $this->authorize('view', $session);

        $session->load(['organizationalUnit', 'event', 'department', 'group']);

        // The roster is every member who could attend this session. For a
        // session tied to a department/group/event the roster is that subset;
        // otherwise it is the whole church. Built as a query so the screen
        // never loads a church's full membership into memory to filter it.
        $members = $this->rosterQuery($session)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'email', 'membership_status']);

        // Existing marks, keyed by member id, so the form renders the current
        // status (and 'unmarked' for someone not yet recorded).
        $existing = $session->records()->pluck('status', 'member_id');

        return view('attendance.show', [
            'session' => $session,
            'members' => $members,
            'existing' => $existing,
            'presentCount' => $session->presentCount(),
            'canTake' => auth()->user()->hasPermission('attendance.manage'),
        ]);
    }

    /**
     * The base member set this session is taken against.
     *
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Member>
     */
    private function rosterQuery(AttendanceSession $session)
    {
        $query = Member::query();

        if ($session->organizational_unit_id) {
            $query->where('organizational_unit_id', $session->organizational_unit_id);
        }

        if ($session->department_id) {
            $query->whereHas('departments', fn ($q) => $q->where('departments.id', $session->department_id));
        }

        if ($session->group_id) {
            $query->whereHas('groups', fn ($q) => $q->where('groups.id', $session->group_id));
        }

        if ($session->event_id) {
            $query->whereHas('eventRegistrations', fn ($q) => $q
                ->where('event_id', $session->event_id)
                ->where('status', 'registered'));
        }

        return $query->where('membership_status', '!=', 'inactive');
    }

    /**
     * Records statuses for a batch of members in one go — this is the
     * "take attendance" screen's submit action, not one request per member.
     */
    public function recordBulk(Request $request, AttendanceSession $session): RedirectResponse
    {
        $this->authorize('update', $session);

        $data = $request->validate([
            'records' => ['required', 'array'],
            'records.*.member_id' => ['required', 'integer'],
            'records.*.status' => ['required', 'in:present,absent,excused,late'],
        ]);

        foreach ($data['records'] as $row) {
            $member = Member::find($row['member_id']);
            abort_unless($member && $member->church_id === $session->church_id, 403);

            $session->records()->updateOrCreate(
                ['member_id' => $member->id],
                ['status' => $row['status'], 'recorded_by' => $request->user()->id],
            );
        }

        return back()->with('status', 'Attendance recorded.');
    }
}

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
        $sessions = AttendanceSession::query()->orderByDesc('session_date')->paginate(25);

        return view('attendance.index', compact('sessions'));
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

        return view('attendance.show', [
            'session' => $session,
            'presentCount' => $session->presentCount(),
        ]);
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

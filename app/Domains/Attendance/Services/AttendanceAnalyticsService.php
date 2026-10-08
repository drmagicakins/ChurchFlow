<?php

namespace App\Domains\Attendance\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Member;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * §12: weekly/monthly attendance, per-member history, per-branch/department
 * breakdowns. Kept as plain aggregate queries here rather than a
 * materialized reporting table — revisit if/when a church's session+record
 * volume makes these queries slow enough to need pre-aggregation (see
 * §44 performance).
 */
class AttendanceAnalyticsService
{
    /** Present/late count per session within a date range, in order. */
    public function trend(Carbon $from, Carbon $to, ?int $organizationalUnitId = null): Collection
    {
        return AttendanceSession::query()
            ->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
            ->when($organizationalUnitId, fn ($q) => $q->where('organizational_unit_id', $organizationalUnitId))
            ->orderBy('session_date')
            ->get()
            ->map(fn (AttendanceSession $session) => [
                'session_date' => $session->session_date->toDateString(),
                'name' => $session->name,
                'present' => $session->presentCount(),
                'total_recorded' => $session->records()->count(),
            ]);
    }

    /** A single member's attendance history, most recent first. */
    public function memberHistory(Member $member, int $limit = 20): Collection
    {
        return AttendanceRecord::query()
            ->where('member_id', $member->id)
            ->with('attendanceSession')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /** A member's attendance rate (present+late / total sessions they were recorded in). */
    public function memberAttendanceRate(Member $member): float
    {
        $records = AttendanceRecord::query()->where('member_id', $member->id)->get();

        if ($records->isEmpty()) {
            return 0.0;
        }

        $present = $records->whereIn('status', ['present', 'late'])->count();

        return round(($present / $records->count()) * 100, 1);
    }
}

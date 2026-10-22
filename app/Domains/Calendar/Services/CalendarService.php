<?php

namespace App\Domains\Calendar\Services;

use App\Models\Event;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * §14: a church calendar is a VIEW over existing data (events, tasks — and
 * later, department activities, birthdays/anniversaries once Members
 * carries those richer fields), not its own table. This keeps a task moved
 * or an event rescheduled from ever getting out of sync with "the
 * calendar" — there's only one source of truth per item type.
 */
class CalendarService
{
    /** @return Collection<int, array{type:string,title:string,date:string,ref:mixed}> */
    public function itemsBetween(Carbon $from, Carbon $to): Collection
    {
        $events = Event::query()
            ->whereBetween('starts_at', [$from, $to])
            ->get()
            ->map(fn (Event $e) => [
                'type' => 'event',
                'title' => $e->title,
                'date' => $e->starts_at->toDateString(),
                'time' => $e->starts_at,
                'ref' => $e,
            ]);

        $tasks = Task::query()
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', ['done', 'cancelled'])
            ->get()
            ->map(fn (Task $t) => [
                'type' => 'task',
                'title' => $t->title,
                'date' => $t->due_date->toDateString(),
                'time' => $t->due_date->copy()->startOfDay(),
                'ref' => $t,
            ]);

        // §12: attendance sessions belong on the calendar too — a service or
        // department meeting is exactly the kind of dated item a church looks
        // for here, and deriving it from the session table (rather than a copy)
        // is the same single-source-of-truth rule as events and tasks.
        $sessions = \App\Models\AttendanceSession::query()
            ->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->map(fn (\App\Models\AttendanceSession $s) => [
                'type' => 'attendance',
                'title' => $s->name,
                'date' => $s->session_date->toDateString(),
                'time' => $s->session_date->copy()->startOfDay(),
                'ref' => $s,
            ]);

        return $events->concat($tasks)->concat($sessions)->sortBy('time')->values();
    }

    /**
     * A calendar month laid out as whole weeks (Sunday first), padded at both
     * ends so every week has exactly seven days. The view renders this grid
     * directly; doing the arithmetic here means the Blade template contains no
     * date maths and the layout is unit-testable on its own.
     *
     * @return array{weeks: array<int, array<int, Carbon>>, label: string, prev: Carbon, next: Carbon}
     */
    public function monthGrid(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth()->startOfWeek(\Illuminate\Support\Carbon::SUNDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(\Illuminate\Support\Carbon::SATURDAY);

        $weeks = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = $cursor->copy();
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return [
            'weeks' => $weeks,
            'label' => $month->copy()->format('F Y'),
            'prev' => $month->copy()->subMonthNoOverflow()->startOfMonth(),
            'next' => $month->copy()->addMonthNoOverflow()->startOfMonth(),
        ];
    }
}

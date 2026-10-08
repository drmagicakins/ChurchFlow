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
                'ref' => $t,
            ]);

        return $events->concat($tasks)->sortBy('date')->values();
    }
}

<x-layout title="Calendar · ChurchFlow">
    @php
        $tz = auth()->user()->church?->timezone ?: config('app.timezone');
        $today = now($tz)->toDateString();
        $typeMeta = [
            'event' => ['label' => 'Event', 'cls' => 'cf-badge--brand'],
            'task' => ['label' => 'Task', 'cls' => ''],
            'attendance' => ['label' => 'Attendance', 'cls' => 'cf-badge--ok'],
        ];
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>Calendar</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Events, open tasks and attendance sessions for {{ auth()->user()->church?->name ?? 'your church' }} — one view over the same records each module owns.
            </p>
        </div>
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
            <a href="{{ route('calendar.index', ['month' => $prevMonth->format('Y-m')]) }}"
               class="cf-btn cf-btn--secondary cf-btn--sm" aria-label="Previous month">← {{ $prevMonth->format('M') }}</a>
            <a href="{{ route('calendar.index') }}" class="cf-btn cf-btn--secondary cf-btn--sm">Today</a>
            <a href="{{ route('calendar.index', ['month' => $nextMonth->format('Y-m')]) }}"
               class="cf-btn cf-btn--secondary cf-btn--sm" aria-label="Next month">{{ $nextMonth->format('M') }} →</a>
            @if (auth()->user()->hasPermission('events.manage'))
                <a href="{{ route('events.index') }}" class="cf-btn cf-btn--primary cf-btn--sm">
                    <x-ui.icon name="calendar" class="h-4 w-4" /> New event
                </a>
            @endif
        </div>
    </div>

    <div class="cf-card cf-card--flush" style="margin-bottom:1.4rem">
        <div class="cf-card__head">
            <h2>{{ $label }}</h2>
            <span class="cf-tiny cf-muted">{{ number_format($items->count()) }} {{ \Illuminate\Support\Str::plural('item', $items->count()) }} this month</span>
        </div>

        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;min-width:56rem">
                <thead>
                    <tr>
                        @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow)
                            <th scope="col" class="cf-tiny cf-muted"
                                style="text-align:left;padding:.5rem .6rem;border-bottom:1px solid var(--cf-line, #e5e7eb);text-transform:uppercase;letter-spacing:.05em">
                                {{ $dow }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($weeks as $week)
                        <tr>
                            @foreach ($week as $day)
                                @php
                                    $inMonth = $day->month === $month->month;
                                    $isToday = $day->toDateString() === $today;
                                    $dayItems = $byDate->get($day->toDateString(), collect());
                                @endphp
                                <td style="vertical-align:top;padding:.4rem .5rem;border-bottom:1px solid var(--cf-line, #eef1f5);border-right:1px solid var(--cf-line, #eef1f5);height:6.5rem;width:14.28%;{{ $isToday ? 'background:var(--cf-brand-tint, #eef2ff);' : '' }}{{ $inMonth ? '' : 'opacity:.45;' }}">
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.25rem">
                                        <span class="cf-small" style="font-weight:{{ $isToday ? '700' : '600' }};{{ $isToday ? 'color:var(--cf-brand, #4f46e5)' : '' }}">
                                            {{ $day->day }}
                                        </span>
                                        @if ($dayItems->isNotEmpty())
                                            <span class="cf-tiny cf-muted">{{ $dayItems->count() }}</span>
                                        @endif
                                    </div>

                                    @foreach ($dayItems->take(3) as $item)
                                        @php
                                            $link = match ($item['type']) {
                                                'event' => route('events.show', $item['ref']),
                                                'attendance' => route('attendance.show', $item['ref']),
                                                default => null,
                                            };
                                        @endphp
                                        @if ($link)
                                            <a href="{{ $link }}" class="cf-tiny"
                                               style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%"
                                               title="{{ $item['title'] }}">{{ $item['title'] }}</a>
                                        @else
                                            <span class="cf-tiny"
                                                  style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%"
                                                  title="{{ $item['title'] }}">{{ $item['title'] }}</span>
                                        @endif
                                    @endforeach

                                    @if ($dayItems->count() > 3)
                                        <span class="cf-tiny cf-muted">+{{ $dayItems->count() - 3 }} more</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="cf-card cf-card--flush">
        <div class="cf-card__head">
            <h2>Everything in {{ $label }}</h2>
        </div>

        @if ($items->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">Nothing scheduled this month</p>
                <p class="cf-small cf-muted">
                    @if (auth()->user()->hasPermission('events.manage'))
                        <a href="{{ route('events.index') }}">Create an event</a> and it will appear here straight away.
                    @else
                        Events and tasks created by your team will appear here.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Item</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            @php $meta = $typeMeta[$item['type']] ?? ['label' => ucfirst($item['type']), 'cls' => '']; @endphp
                            <tr>
                                <td class="cf-mono cf-small" style="white-space:nowrap">
                                    {{ $item['time']->timezone($tz)->format('d M Y') }}
                                    @if ($item['type'] === 'event')
                                        <span class="cf-tiny cf-muted">{{ $item['time']->timezone($tz)->format('g:i A') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($item['type'] === 'event')
                                        <a href="{{ route('events.show', $item['ref']) }}">{{ $item['title'] }}</a>
                                    @elseif ($item['type'] === 'attendance')
                                        <a href="{{ route('attendance.show', $item['ref']) }}">{{ $item['title'] }}</a>
                                    @else
                                        {{ $item['title'] }}
                                    @endif
                                </td>
                                <td><span class="cf-badge {{ $meta['cls'] }}">{{ $meta['label'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layout>

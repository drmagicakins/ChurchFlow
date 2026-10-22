<x-layout :title="$session->name . ' · Attendance · ChurchFlow'">
    @php
        $statuses = ['present' => 'Present', 'late' => 'Late', 'excused' => 'Excused', 'absent' => 'Absent'];
        $marked = $existing->count();
        $unmarked = $members->count() - $marked;
        $ratePct = $members->count() > 0 ? round(($presentCount / $members->count()) * 100) : 0;
    @endphp

    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin-bottom:.35rem">
                <a href="{{ route('attendance.index') }}">← All sessions</a>
            </p>
            <h1>{{ $session->name }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
                <span class="cf-badge">{{ ucfirst($session->type) }}</span>
                <span>{{ $session->session_date?->format('l, j M Y') }}</span>
                @if ($session->organizationalUnit)<span class="cf-badge">{{ $session->organizationalUnit->name }}</span>@endif
                @if ($session->department)<span class="cf-badge">{{ $session->department->name }}</span>@endif
                @if ($session->group)<span class="cf-badge">{{ $session->group->name }}</span>@endif
                @if ($session->event)<span class="cf-badge cf-badge--brand">{{ $session->event->title }}</span>@endif
            </p>
        </div>
    </div>

    @if (session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    <div class="cf-grid cf-grid--4" style="margin-bottom:1.2rem">
        <div class="cf-card"><div class="cf-stat"><span class="cf-stat__label">Roster</span><span class="cf-stat__value">{{ number_format($members->count()) }}</span></div></div>
        <div class="cf-card"><div class="cf-stat"><span class="cf-stat__label">Present</span><span class="cf-stat__value">{{ number_format($presentCount) }}</span></div></div>
        <div class="cf-card"><div class="cf-stat"><span class="cf-stat__label">Not yet marked</span><span class="cf-stat__value">{{ number_format($unmarked) }}</span></div></div>
        <div class="cf-card"><div class="cf-stat"><span class="cf-stat__label">Turnout</span><span class="cf-stat__value">{{ $ratePct }}%</span></div></div>
    </div>

    <div class="cf-card cf-card--flush">
        <div class="cf-card__head">
            <h2>Roster</h2>
            @if ($canTake)
                <div style="display:flex;gap:.4rem">
                    <button type="button" class="cf-btn cf-btn--secondary cf-btn--sm" data-bulk="present">Mark all present</button>
                    <button type="button" class="cf-btn cf-btn--secondary cf-btn--sm" data-bulk="absent">Mark all absent</button>
                </div>
            @endif
        </div>

        @if ($members->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No one on this roster</p>
                <p class="cf-small cf-muted">Add members (or link this session to a department, group or event) and they will appear here to be marked.</p>
            </div>
        @else
            <form method="POST" action="{{ route('attendance.records', $session) }}">
                @csrf
                <div class="cf-table-wrap">
                    <table class="cf-table">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($members as $member)
                                @php $current = $existing->get($member->id); @endphp
                                <tr>
                                    <td>
                                        <span>{{ $member->full_name }}</span>
                                        @if ($member->email)<br><span class="cf-tiny cf-muted">{{ $member->email }}</span>@endif
                                    </td>
                                    <td>
                                        @if ($canTake)
                                            <div style="display:flex;gap:.35rem;flex-wrap:wrap">
                                                @foreach ($statuses as $value => $label)
                                                    @php $fid = 'status-'.$member->id.'-'.$value; @endphp
                                                    <input type="radio" class="cf-radio" id="{{ $fid }}"
                                                           name="records[{{ $member->id }}][status]" value="{{ $value }}"
                                                           data-member="{{ $member->id }}"
                                                           @checked($current === $value)>
                                                    <label for="{{ $fid }}" class="cf-btn cf-btn--secondary cf-btn--sm"
                                                           style="cursor:pointer">{{ $label }}</label>
                                                @endforeach
                                                <input type="hidden" name="records[{{ $member->id }}][member_id]" value="{{ $member->id }}">
                                            </div>
                                        @else
                                            <span class="cf-badge">{{ $current ? ucfirst($current) : 'Not recorded' }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($canTake)
                    <div style="padding:1rem 1.35rem">
                        <button type="submit" class="cf-btn cf-btn--primary">Save attendance</button>
                        <span class="cf-small cf-muted" style="margin-left:.6rem">Only marked rows are saved; leave anyone you are unsure about blank.</span>
                    </div>
                @endif
            </form>
        @endif
    </div>

    @if ($canTake)
        @push('scripts')
            <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
                (function () {
                    // "Mark all present/absent" sets every radio in one click, which is
                    // the normal case for a service (most people are present) — typing
                    // 200 individual marks is exactly the friction this screen exists to remove.
                    document.querySelectorAll('[data-bulk]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var target = btn.getAttribute('data-bulk');
                            document.querySelectorAll('input[type="radio"][data-member]').forEach(function (radio) {
                                if (radio.value === target) { radio.checked = true; }
                            });
                        });
                    });
                })();
            </script>
        @endpush
    @endif
</x-layout>

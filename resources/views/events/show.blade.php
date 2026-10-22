<x-layout :title="$event->title . ' · Events · ChurchFlow'">
    @php
        $tz = auth()->user()->church?->timezone ?: config('app.timezone');
        $registered = $registrations->where('status', 'registered');
        $waitlisted = $registrations->where('status', 'waitlisted');
        $confirmed = $event->confirmedCount();
        $seatsLeft = $event->capacity === null ? null : max($event->capacity - $confirmed, 0);
        $tone = match ($event->status) {
            'cancelled' => 'cf-badge--danger',
            'completed' => 'cf-badge--ok',
            default => 'cf-badge--brand',
        };
    @endphp

    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin-bottom:.35rem">
                <a href="{{ route('events.index') }}">← All events</a>
            </p>
            <h1>{{ $event->title }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                <span class="cf-badge {{ $tone }}">{{ ucfirst($event->status ?? 'scheduled') }}</span>
                @if ($event->organizationalUnit)
                    <span class="cf-badge">{{ $event->organizationalUnit->name }}</span>
                @endif
            </p>
        </div>
        @if ($canManage)
            <form method="POST" action="{{ route('events.destroy', $event) }}"
                onsubmit="return confirm('Cancel this event? It will no longer appear on the calendar.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Cancel event</button>
            </form>
        @endif
    </div>

    <div class="cf-grid cf-grid--2">
        <div class="cf-card">
            <div class="cf-card__head"><h2>Details</h2></div>
            <div class="cf-card__body">
                <dl style="display:grid;grid-template-columns:auto 1fr;gap:.5rem 1rem;margin:0">
                    <dt class="cf-label" style="margin:0">Starts</dt>
                    <dd class="cf-small" style="margin:0">{{ $event->starts_at?->timezone($tz)->format('l, j M Y · g:i A') }}</dd>

                    <dt class="cf-label" style="margin:0">Ends</dt>
                    <dd class="cf-small" style="margin:0">{{ $event->ends_at ? $event->ends_at->timezone($tz)->format('l, j M Y · g:i A') : '—' }}</dd>

                    <dt class="cf-label" style="margin:0">Venue</dt>
                    <dd class="cf-small" style="margin:0">{{ $event->venue ?: '—' }}</dd>

                    <dt class="cf-label" style="margin:0">Organiser</dt>
                    <dd class="cf-small" style="margin:0">{{ $event->organizer?->name ?? '—' }}</dd>

                    <dt class="cf-label" style="margin:0">Capacity</dt>
                    <dd class="cf-small" style="margin:0">{{ $event->capacity ? number_format($event->capacity) : 'Unlimited' }}</dd>
                </dl>

                @if ($event->description)
                    <div style="margin-top:1rem">
                        <p class="cf-label">Description</p>
                        <p class="cf-small" style="white-space:pre-line">{{ $event->description }}</p>
                    </div>
                @endif

                @if (! empty($event->speakers))
                    <div style="margin-top:1rem">
                        <p class="cf-label">Speakers</p>
                        <p class="cf-small">{{ implode(', ', $event->speakers) }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="cf-card">
            <div class="cf-card__head"><h2>Attendance</h2></div>
            <div class="cf-card__body">
                <div class="cf-grid cf-grid--2" style="gap:.75rem">
                    <div class="cf-stat">
                        <span class="cf-stat__label">Registered</span>
                        <span class="cf-stat__value">{{ number_format($confirmed) }}</span>
                    </div>
                    <div class="cf-stat">
                        <span class="cf-stat__label">Seats left</span>
                        <span class="cf-stat__value">{{ $seatsLeft === null ? '∞' : number_format($seatsLeft) }}</span>
                    </div>
                </div>

                @if ($waitlisted->isNotEmpty())
                    <p class="cf-small cf-muted" style="margin-top:.75rem">
                        {{ number_format($waitlisted->count()) }} on the waitlist — they move up automatically when a seat frees.
                    </p>
                @endif

                @if ($canManage && $availableMembers->isNotEmpty())
                    <x-form.card title="Register a member" description="Capacity is enforced: once the event is full, further registrations are waitlisted rather than rejected."
                        :action="route('events.register', $event)" submit="Register" submit-icon="check" layout="inline">
                        <x-form.select name="member_id" label="Member" required placeholder="Choose a member..."
                            :options="$availableMembers->map(fn ($m) => ['value' => $m->id, 'label' => $m->full_name . ($m->email ? ' — ' . $m->email : '')])->all()" />
                    </x-form.card>
                @endif
            </div>
        </div>
    </div>

    <div class="cf-card cf-card--flush" style="margin-top:1.4rem">
        <div class="cf-card__head">
            <h2>Registered attendees</h2>
            <span class="cf-tiny cf-muted">{{ number_format($registered->count()) }} {{ \Illuminate\Support\Str::plural('person', $registered->count()) }}</span>
        </div>

        @if ($registered->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No one registered yet</p>
                <p class="cf-small cf-muted">
                    @if ($canManage)
                        Use the form above to register the first attendee.
                    @else
                        Registrations will appear here as members sign up.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Email</th>
                            <th>Registered</th>
                            @if ($canManage)<th style="text-align:right">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($registered as $registration)
                            <tr>
                                <td>{{ $registration->member?->full_name ?? 'Removed member' }}</td>
                                <td class="cf-small cf-muted">{{ $registration->member?->email ?: '—' }}</td>
                                <td class="cf-small cf-muted" style="white-space:nowrap">
                                    {{ $registration->registered_at?->diffForHumans() }}
                                </td>
                                @if ($canManage)
                                    <td style="text-align:right">
                                        <form method="POST" action="{{ route('events.registrations.cancel', $event) }}">
                                            @csrf
                                            <input type="hidden" name="member_id" value="{{ $registration->member_id }}">
                                            <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Remove</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layout>

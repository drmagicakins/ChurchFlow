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

        // §13/§16: the public form's list. Visitors are counted separately from
        // the member roster on purpose — an organiser needs to know which of the
        // people coming are guests they have never met.
        $visitors = $visitors ?? collect();
        $activeVisitors = $visitors->where('status', '!=', 'cancelled');
        $visitorsWithEmail = $activeVisitors->filter(fn ($v) => filled($v->email))->count();
        $visitorsWithPhone = $activeVisitors->filter(fn ($v) => filled($v->phone))->count();
        $canSms = auth()->user()->hasPermission('sms.manage');
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
            <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                <form method="POST" action="{{ route('events.public-form', $event) }}">
                    @csrf
                    <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">
                        <x-ui.icon name="megaphone" class="h-4 w-4" />
                        {{ $event->public_slug ? 'Public form link' : 'Open public registration' }}
                    </button>
                </form>
                <form method="POST" action="{{ route('events.destroy', $event) }}"
                    onsubmit="return confirm('Cancel this event? It will no longer appear on the calendar.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Cancel event</button>
                </form>
            </div>
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

    {{-- §13/§16: the people who filled in the PUBLIC form. They are not church
         members, so they are their own card — but they are on this page because
         the organiser asked one question ("who is coming?") and deserves one
         answer. --}}
    <div class="cf-card cf-card--flush" style="margin-top:1.4rem" id="visitors">
        <div class="cf-card__head" style="display:flex;gap:.75rem;align-items:center;justify-content:space-between;flex-wrap:wrap">
            <div>
                <h2 style="margin:0">Public registrations</h2>
                <span class="cf-tiny cf-muted">
                    {{ number_format($activeVisitors->count()) }} {{ \Illuminate\Support\Str::plural('visitor', $activeVisitors->count()) }}
                    · {{ $visitorsWithEmail }} with email · {{ $visitorsWithPhone }} with phone

                    @if ($event->public_slug)
                        <span class="cf-badge {{ $event->registration_open ? 'cf-badge--ok' : '' }}" style="margin-left:.4rem">
                            {{ $event->registration_open ? 'Form open' : 'Form closed' }}
                        </span>
                    @endif
                </span>
            </div>

            @if ($canManage && $event->public_slug)
                <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                    <input type="text" class="cf-input cf-mono" style="max-width:22rem;font-size:.8rem"
                        value="{{ route('events.public.register', $event->public_slug) }}" readonly
                        aria-label="Public registration link" onclick="this.select()">
                    <a href="{{ route('events.public.register', $event->public_slug) }}" target="_blank"
                        rel="noopener" class="cf-btn cf-btn--secondary cf-btn--sm">Open form</a>
                </div>
            @endif
        </div>

        @if ($canManage && $activeVisitors->isNotEmpty())
            <div class="cf-card__body" style="border-bottom:1px solid var(--line, #e5e7eb)">
                <div class="cf-grid cf-grid--2">
                    <div>
                        <h3 class="cf-label" style="margin:0 0 .3rem">Email reminder</h3>
                        <p class="cf-tiny cf-muted" style="margin:0 0 .5rem">
                            Sends “{{ $event->title }}” details to all {{ $visitorsWithEmail }} visitor(s) with an email.
                        </p>
                        <form method="POST" action="{{ route('events.remind.email', $event) }}">
                            @csrf
                            <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm"
                                @disabled($visitorsWithEmail === 0)>
                                <x-ui.icon name="doc" class="h-4 w-4" /> Send email reminder
                            </button>
                        </form>
                    </div>

                    <div>
                        <h3 class="cf-label" style="margin:0 0 .3rem">Bulk SMS reminder</h3>
                        @if (! $canSms)
                            <p class="cf-tiny cf-muted" style="margin:0">
                                Sending SMS needs the <strong>SMS</strong> permission. Ask your church owner to grant it.
                            </p>
                        @elseif ($visitorsWithPhone === 0)
                            <p class="cf-tiny cf-muted" style="margin:0">
                                Nobody on this list has a phone number yet.
                            </p>
                        @else
                            <form method="POST" action="{{ route('events.remind.sms', $event) }}" class="cf-form">
                                @csrf
                                <div class="cf-field" style="margin:0 0 .5rem">
                                    <label class="cf-label" for="event-sms-message">Message</label>
                                    <textarea name="message" id="event-sms-message" class="cf-textarea" rows="3"
                                        maxlength="600" required
                                        placeholder="Reminder: {{ $event->title }} starts {{ $event->starts_at?->format('D j M, g:i A') }} at {{ $event->venue ?: 'the church' }}.">{{ old('message', 'Reminder: ' . $event->title . ' starts ' . $event->starts_at?->format('D j M, g:i A') . ($event->venue ? ' at ' . $event->venue : '') . '.') }}</textarea>
                                    @error('message')
                                        <p class="cf-error" role="alert">{{ $message }}</p>
                                    @enderror
                                </div>
                                <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">
                                    <x-ui.icon name="chat" class="h-4 w-4" /> Send SMS to {{ $visitorsWithPhone }}
                                </button>
                                <p class="cf-tiny cf-muted" style="margin:.4rem 0 0">
                                    Units are reserved from this church's SMS wallet as the messages send.
                                </p>
                            </form>
                        @endif
                    </div>
                </div>

                @error('reminder')
                    <p class="cf-error" role="alert" style="margin-top:.6rem">{{ $message }}</p>
                @enderror
            </div>
        @endif

        @if ($visitors->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No public registrations yet</p>
                <p class="cf-small cf-muted">
                    @if ($canManage)
                        Share the public form link so visitors can register without an account.
                    @else
                        Visitors who sign up will appear here.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Visitor</th>
                            <th>Contact</th>
                            <th>Organisation</th>
                            <th style="text-align:right">Party</th>
                            <th>Registered</th>
                            <th>Reminded</th>
                            @if ($canManage)<th style="text-align:right">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($visitors as $visitor)
                            <tr>
                                <td>
                                    {{ $visitor->name }}
                                    @if ($visitor->status === 'waitlisted')
                                        <span class="cf-badge cf-badge--warn" style="margin-left:.35rem">Waitlist</span>
                                    @elseif ($visitor->status === 'cancelled')
                                        <span class="cf-badge cf-badge--danger" style="margin-left:.35rem">Cancelled</span>
                                    @endif
                                    @if ($visitor->notes)
                                        <div class="cf-tiny cf-muted">{{ \Illuminate\Support\Str::limit($visitor->notes, 70) }}</div>
                                    @endif
                                </td>
                                <td class="cf-small cf-muted">
                                    {{ $visitor->email ?: '—' }}<br>
                                    <span class="cf-tiny">{{ $visitor->phone ?: '' }}</span>
                                </td>
                                <td class="cf-small cf-muted">{{ $visitor->organization ?: '—' }}</td>
                                <td class="cf-mono" style="text-align:right">{{ $visitor->headcount() }}</td>
                                <td class="cf-small cf-muted" style="white-space:nowrap">
                                    {{ $visitor->registered_at?->format('d M Y, H:i') }}
                                </td>
                                <td class="cf-tiny cf-muted" style="white-space:nowrap">
                                    @if ($visitor->email_reminded_at)
                                        Email {{ $visitor->email_reminded_at->diffForHumans() }}
                                    @endif
                                    @if ($visitor->sms_reminded_at)
                                        <br>SMS {{ $visitor->sms_reminded_at->diffForHumans() }}
                                    @endif
                                    @if (! $visitor->email_reminded_at && ! $visitor->sms_reminded_at)
                                        —
                                    @endif
                                </td>
                                @if ($canManage)
                                    <td style="text-align:right">
                                        @if ($visitor->status !== 'cancelled')
                                            <form method="POST"
                                                action="{{ route('events.visitors.cancel', [$event, $visitor]) }}"
                                                data-confirm="Remove {{ $visitor->name }} from this event's list? Their registration record is kept."
                                                data-confirm-title="Remove visitor?" data-confirm-ok="Remove">
                                                @csrf
                                                <button type="submit"
                                                    class="cf-btn cf-btn--secondary cf-btn--sm">Remove</button>
                                            </form>
                                        @endif
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

{{--
 | A pastoral case.
 |
 | This page was a bare stub (a heading, "Status:", and an unstyled list of
 | timestamps) even though the controller already provided everything a case
 | needs: append notes and close it. Both actions existed as routes and were
 | unreachable from any screen.
 |
 | The design follows the rest of the app — cf-page-head, cf-card, cf-badge,
 | cf-form components — so a case reads like the member page it belongs with.
 |
 | Two deliberate choices:
 |  1. Notes are append-only and shown NEWEST FIRST, with an explicit line
 |     saying they cannot be edited. That is the module's promise (§21); the
 |     page must not look like a comment box that invites rewriting history.
 |  2. Nothing about the case is printed unless the policy allowed it here —
 |     visibility is enforced by the controller, and the page just renders what
 |     it was given.
--}}
<x-layout :title="($case->member?->full_name ?? 'Pastoral case') . ' · Pastoral care · ChurchFlow'">
    @php
        $canUpdate = auth()->user()->can('update', $case);

        $statusTone = match ($case->status) {
            'closed' => 'cf-badge--ok',
            'in_progress' => 'cf-badge--brand',
            default => 'cf-badge--warn',
        };

        $typeLabel = match ($case->type) {
            'counseling' => 'Counseling',
            'welfare' => 'Welfare / benevolence',
            'hospital_visit' => 'Hospital visit',
            'new_member_followup' => 'New-member follow-up',
            'other' => 'Other',
            default => str_replace('_', ' ', ucfirst($case->type ?? 'case')),
        };

        $priorityTone = match ($case->priority) {
            'high' => 'cf-badge--danger',
            'low' => '',
            default => 'cf-badge--brand',
        };

        $member = $case->member;
        $initials = $member
            ? \Illuminate\Support\Str::of($member->full_name)
                ->explode(' ')
                ->filter()
                ->map(fn($w) => mb_substr($w, 0, 1))
                ->take(2)
                ->implode('')
            : '?';

        $notes = $case->notes->sortByDesc('id')->values();
        $upcoming = $case->appointments
            ->filter(fn($a) => $a->scheduled_at && $a->scheduled_at->isFuture() && $a->status === 'scheduled')
            ->sortBy('scheduled_at');

        $isClosed = $case->status === 'closed';
    @endphp

    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin:0">
                <a href="{{ route('pastoral.cases.index') }}">← All pastoral cases</a>
            </p>
            <h1 style="margin:.35rem 0 0">
                {{ $member?->full_name ?? 'Pastoral case' }}
            </h1>
            <p class="cf-small cf-muted"
                style="display:flex;gap:.45rem;flex-wrap:wrap;align-items:center;margin-top:.45rem">
                <span class="cf-badge cf-badge--brand">{{ $typeLabel }}</span>
                <span
                    class="cf-badge {{ $statusTone }}">{{ str_replace('_', ' ', ucfirst($case->status ?? 'open')) }}</span>
                @if ($case->priority && $case->priority !== 'normal')
                    <span class="cf-badge {{ $priorityTone }}">{{ ucfirst($case->priority) }} priority</span>
                @endif
                @if ($member?->membership_number)
                    <span class="cf-mono cf-tiny">{{ $member->membership_number }}</span>
                @endif
            </p>
        </div>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
            @if ($member && auth()->user()->hasPermission('members.view'))
                <a href="{{ route('members.show', $member) }}" class="cf-btn cf-btn--secondary cf-btn--sm">
                    <x-ui.icon name="users" class="h-4 w-4" /> Member record
                </a>
            @endif
            @if ($canUpdate && !$isClosed)
                <button type="button" class="cf-btn cf-btn--secondary cf-btn--sm" data-toggle="case-close"
                    aria-expanded="false" aria-controls="case-close-panel">
                    <x-ui.icon name="check" class="h-4 w-4" /> Close case
                </button>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">{{ session('status') }}</p>
            </div>
        </div>
    @endif

    @if ($isClosed)
        <div class="cf-alert cf-alert--ok" role="status" style="margin-bottom:1.2rem">
            <span class="cf-alert__icon"><x-ui.icon name="lock" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">This case is closed</p>
                <p class="cf-small" style="margin:.25rem 0 0">
                    Closed {{ $case->closed_at?->diffForHumans() ?? 'previously' }}. Its history stays
                    readable; a new concern should be opened as a new case.
                </p>
            </div>
        </div>
    @endif

    @if ($canUpdate && !$isClosed)
        {{-- Closing asks for an optional final note, so the reason is recorded
             in the same append-only log as everything else. --}}
        <div id="case-close-panel" data-panel="case-close" @if (!$errors->any()) hidden @endif
            style="margin-bottom:1.2rem">
            <x-form.card title="Close this case"
                description="Closing records the outcome and stops further notes. What was already written stays exactly as it is."
                :action="route('pastoral.cases.close', ['case' => $case])" method="POST" submit="Close case" submit-icon="check">
                <x-form.textarea name="closing_note" label="Closing note (optional)" rows="3" wide
                    placeholder="How was it resolved?" hint="Added as the final entry in the case history." />
            </x-form.card>
        </div>
    @endif

    <div class="cf-grid cf-grid--2">
        {{-- The case itself --}}
        <div class="cf-card">
            <div class="cf-card__head">
                <h2>Case details</h2>
            </div>
            <div class="cf-card__body">
                <dl style="display:grid;grid-template-columns:auto 1fr;gap:.45rem 1rem;font-size:.9rem;margin:0">
                    <dt class="cf-muted">Member</dt>
                    <dd style="margin:0">
                        @if ($member)
                            @if (auth()->user()->hasPermission('members.view'))
                                <a href="{{ route('members.show', $member) }}">{{ $member->full_name }}</a>
                            @else
                                {{ $member->full_name }}
                            @endif
                        @else
                            —
                        @endif
                    </dd>

                    <dt class="cf-muted">Type</dt>
                    <dd style="margin:0">{{ $typeLabel }}</dd>

                    <dt class="cf-muted">Status</dt>
                    <dd style="margin:0">{{ str_replace('_', ' ', ucfirst($case->status ?? 'open')) }}</dd>

                    <dt class="cf-muted">Assigned to</dt>
                    <dd style="margin:0">{{ $case->assignee?->name ?? '—' }}</dd>

                    <dt class="cf-muted">Opened</dt>
                    <dd style="margin:0">
                        {{ $case->created_at?->toFormattedDateString() ?? '—' }}
                        @if ($case->openedBy?->name)
                            <span class="cf-tiny cf-muted">by {{ $case->openedBy->name }}</span>
                        @endif
                    </dd>

                    @if ($case->closed_at)
                        <dt class="cf-muted">Closed</dt>
                        <dd style="margin:0">{{ $case->closed_at->toFormattedDateString() }}</dd>
                    @endif

                    <dt class="cf-muted">Priority</dt>
                    <dd style="margin:0">{{ ucfirst($case->priority ?? 'normal') }}</dd>
                </dl>

                @if (filled($case->description))
                    <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--line, #e5e7eb)">
                        <p class="cf-tiny cf-muted" style="margin:0 0 .25rem">Summary</p>
                        <p style="margin:0;white-space:pre-line">{{ $case->description }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Who this record is belong to, in the same shape as the member page --}}
        <div style="display:grid;gap:1.2rem">
            <div class="cf-card">
                <div class="cf-card__head">
                    <h2>Care record</h2>
                </div>
                <div class="cf-card__body" style="display:flex;gap:.9rem;align-items:flex-start">
                    <span class="cfd-user__avatar" style="width:52px;height:52px;font-size:1.1rem;flex-shrink:0"
                        aria-hidden="true">{{ $initials }}</span>
                    <div class="cf-small" style="display:grid;gap:.3rem">
                        <p style="margin:0;font-weight:600">{{ $member?->full_name ?? 'Member removed' }}</p>
                        @if ($member?->phone)
                            <p style="margin:0" class="cf-muted">{{ $member->phone }}</p>
                        @endif
                        <p style="margin:0" class="cf-muted">
                            {{ $notes->count() }} {{ \Illuminate\Support\Str::plural('note', $notes->count()) }} ·
                            {{ $case->appointments->count() }}
                            {{ \Illuminate\Support\Str::plural('appointment', $case->appointments->count()) }}
                        </p>
                    </div>
                </div>
                <div class="cf-card__body" style="border-top:1px solid var(--line, #e5e7eb)">
                    <p class="cf-tiny cf-muted" style="margin:0;display:flex;gap:.4rem;align-items:flex-start">
                        <x-ui.icon name="shield" class="h-4 w-4" />
                        <span>Visible only to the assigned pastor and holders of pastoral care access.
                            Never shown to general administrators.</span>
                    </p>
                </div>
            </div>

            <div class="cf-card">
                <div class="cf-card__head">
                    <h2>Appointments</h2>
                    <a href="{{ route('pastoral.appointments.index') }}">All appointments</a>
                </div>
                <div class="cf-card__body">
                    @if ($case->appointments->isEmpty())
                        <p class="cf-small cf-muted" style="margin:0">No appointment has been booked for this case.
                        </p>
                    @else
                        <ul style="margin:0;padding:0;list-style:none;display:grid;gap:.6rem">
                            @foreach ($case->appointments->sortBy('scheduled_at') as $appointment)
                                <li style="display:flex;gap:.6rem;align-items:flex-start">
                                    <span class="cfd-ico cfd-ico--sm cfd-ico--purple">
                                        <x-ui.icon name="calendar" class="h-4 w-4" />
                                    </span>
                                    <div>
                                        <p style="margin:0" class="cf-small">{{ $appointment->title }}</p>
                                        <p style="margin:0" class="cf-tiny cf-muted">
                                            {{ $appointment->scheduled_at?->toDayDateTimeString() ?? 'No date' }}
                                            @if ($appointment->location)
                                                · {{ $appointment->location }}
                                            @endif
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- The append-only history, newest first --}}
    <div class="cf-card" style="margin-top:1.2rem">
        <div class="cf-card__head">
            <h2>Case history</h2>
            <span class="cf-tiny cf-muted">{{ $notes->count() }}
                {{ \Illuminate\Support\Str::plural('entry', $notes->count()) }} · append-only</span>
        </div>

        <div class="cf-card__body">
            @if ($notes->isEmpty())
                <p class="cf-small cf-muted" style="margin:0">No entries yet.</p>
            @else
                <ol style="margin:0;padding:0;list-style:none;display:grid;gap:1rem">
                    @foreach ($notes as $note)
                        <li style="padding-left:1rem;border-left:2px solid var(--line, #e5e7eb)">
                            <p class="cf-tiny cf-muted" style="margin:0 0 .2rem">
                                {{ $note->author?->name ?? 'System' }} ·
                                {{ $note->created_at?->toDayDateTimeString() ?? 'undated' }}
                                <span class="cf-muted">({{ $note->created_at?->diffForHumans() }})</span>
                            </p>
                            <p style="margin:0;white-space:pre-line">{{ $note->note }}</p>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>

        @if ($canUpdate && !$isClosed)
            <div class="cf-card__body" style="border-top:1px solid var(--line, #e5e7eb)">
                <x-form.card title="Add an entry"
                    description="A correction is a new entry, never an edit to what is already here." :action="route('pastoral.cases.notes', ['case' => $case])"
                    method="POST" submit="Add entry" submit-icon="chat">
                    <x-form.textarea name="note" label="Note" rows="3" wide required
                        placeholder="What happened, and what is the next step?" />
                </x-form.card>
            </div>
        @elseif ($isClosed)
            <div class="cf-card__body" style="border-top:1px solid var(--line, #e5e7eb)">
                <p class="cf-small cf-muted" style="margin:0">
                    This case is closed, so no further entries can be added to it.
                </p>
            </div>
        @endif
    </div>

    @if ($canUpdate && !$isClosed)
        @push('scripts')
            <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
                (function() {
                    // Opens the close-case panel. Pushed unconditionally and
                    // guarded here at runtime, which is the pattern the rest of
                    // the app uses; a user without permission has neither the
                    // button nor the panel, so this is a no-op for them.
                    var btn = document.querySelector('[data-toggle="case-close"]');
                    var panel = document.querySelector('[data-panel="case-close"]');
                    if (!btn || !panel) return;

                    btn.addEventListener('click', function() {
                        var isOpen = !panel.hasAttribute('hidden');
                        if (isOpen) {
                            panel.setAttribute('hidden', '');
                        } else {
                            panel.removeAttribute('hidden');
                            panel.scrollIntoView({
                                behavior: 'smooth',
                                block: 'start'
                            });
                        }
                        btn.setAttribute('aria-expanded', String(!isOpen));
                    });

                    // A failed close must not leave its message hidden.
                    if (panel.querySelector('[aria-invalid="true"], .cf-error')) {
                        panel.removeAttribute('hidden');
                        btn.setAttribute('aria-expanded', 'true');
                    }
                })();
            </script>
        @endpush
    @endif
</x-layout>

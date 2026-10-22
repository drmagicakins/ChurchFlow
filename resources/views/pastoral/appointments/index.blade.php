<x-layout title="Appointments · Pastoral care · ChurchFlow">
    @php
        $canManage = auth()->user()->hasPermission('pastoral.manage');
        $members = \App\Models\Member::query()->orderBy('full_name')->limit(500)->get();
        $cases = \App\Models\PastoralCase::query()->orderByDesc('id')->limit(200)->get();
        $pastors = \App\Models\User::query()->where('church_id', auth()->user()->church_id)->orderBy('name')->get();
        $statuses = [
            'scheduled' => 'Scheduled',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no_show' => 'No show',
        ];
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>Appointments</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Pastoral meetings. An appointment tied to a case inherits that case's visibility; a plain meeting is
                visible to whoever can see appointments.
            </p>
        </div>
        @if($canManage)
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="appt-create" aria-expanded="false">
                <x-ui.icon name="calendar" class="h-4 w-4" /> Schedule appointment
            </button>
        @endif
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    @if($errors->any())
        <div class="cf-alert cf-alert--error" role="alert">
            <span class="cf-alert__icon"><x-ui.icon name="alert" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">There is a problem with your submission.</p>
                <ul class="cf-alert__list">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    @if($canManage)
        <div data-panel="appt-create" hidden style="margin-bottom:1.2rem">
            <x-form.card
                title="Schedule an appointment"
                description="Link a member and, optionally, a pastoral case so the meeting carries the right visibility."
                :action="route('pastoral.appointments.store')"
                submit="Schedule"
                submit-icon="calendar"
                layout="inline"
            >
                <x-form.input name="title" label="What is this about" required placeholder="e.g. Marriage counselling session" />

                <x-form.select name="pastor_id" label="With pastor" required placeholder="Choose a pastor"
                    :options="$pastors->map(fn ($p) => ['value' => $p->id, 'label' => $p->name])->all()" />

                <x-form.select name="member_id" label="Member (optional)" placeholder="Not linked to a member"
                    :options="$members->map(fn ($m) => ['value' => $m->id, 'label' => $m->full_name . ' (' . $m->membership_number . ')'])->all()" />

                @if($cases->isNotEmpty())
                    <x-form.select name="pastoral_case_id" label="Pastoral case (optional)" placeholder="Not tied to a case"
                        :options="$cases->map(fn ($c) => ['value' => $c->id, 'label' => '#'.$c->id.' · '.ucfirst(str_replace('_', ' ', (string) $c->type))])->all()" />
                @endif

                <x-form.input name="scheduled_at" label="When" type="datetime-local" required :value="now()->addDay()->format('Y-m-d\\TH:i')" />
                <x-form.input name="duration_minutes" label="Duration (minutes)" type="number" min="5" :value="'30'" />
                <x-form.input name="location" label="Location" placeholder="e.g. Pastor's office" />
            </x-form.card>
        </div>
    @endif

    <div class="cf-card cf-card--flush">
        @if($appointments->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">Nothing scheduled</p>
                <p class="cf-small cf-muted">
                    @if($canManage)
                        Use <strong>Schedule appointment</strong> above to book a meeting.
                    @else
                        Appointments booked with you will appear here.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr><th>Appointment</th><th>When</th><th>With</th><th>Status</th><th style="text-align:right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach($appointments as $appointment)
                            <tr>
                                <td>
                                    <strong>{{ $appointment->title }}</strong>
                                    @if($appointment->location)
                                        <div class="cf-tiny cf-muted">{{ $appointment->location }}</div>
                                    @endif
                                </td>
                                <td class="cf-small" style="white-space:nowrap">
                                    {{ $appointment->scheduled_at?->format('d M Y, H:i') ?? '—' }}
                                    @if($appointment->duration_minutes)
                                        <div class="cf-tiny cf-muted">{{ $appointment->duration_minutes }} min</div>
                                    @endif
                                </td>
                                <td class="cf-small cf-muted">
                                    {{ $appointment->pastor?->name ?? '—' }}
                                    @if($appointment->member)
                                        <div class="cf-tiny cf-muted">with {{ $appointment->member->full_name }}</div>
                                    @endif
                                </td>
                                <td>
                                    @php $tone = match ($appointment->status) {
                                        'completed' => 'cf-badge--ok',
                                        'cancelled', 'no_show' => 'cf-badge--danger',
                                        default => 'cf-badge--brand',
                                    }; @endphp
                                    <span class="cf-badge {{ $tone }}">{{ str_replace('_', ' ', ucfirst($appointment->status)) }}</span>
                                </td>
                                <td style="text-align:right;white-space:nowrap">
                                    @if(auth()->user()->can('update', $appointment))
                                        <form method="POST" action="{{ route('pastoral.appointments.update', $appointment) }}" style="display:inline-flex;gap:.35rem;align-items:center">
                                            @csrf
                                            @method('PUT')
                                            <select name="status" class="cf-select" style="padding:.3rem;font-size:.82rem">
                                                @foreach($statuses as $value => $label)
                                                    <option value="{{ $value }}" @selected($appointment->status === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Save</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($appointments->hasPages())
        <div style="padding:1rem 1.35rem">{{ $appointments->links() }}</div>
    @endif

    @push('scripts')
        <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
            (function () {
                var btn = document.querySelector('[data-toggle="appt-create"]');
                var panel = document.querySelector('[data-panel="appt-create"]');
                if (!btn || !panel) return;
                btn.addEventListener('click', function () {
                    var isOpen = !panel.hasAttribute('hidden');
                    if (isOpen) { panel.setAttribute('hidden', ''); }
                    else { panel.removeAttribute('hidden'); panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
                    btn.setAttribute('aria-expanded', String(!isOpen));
                });
                if (panel.querySelector('[aria-invalid="true"]')) { panel.removeAttribute('hidden'); }
            })();
        </script>
    @endpush
</x-layout>

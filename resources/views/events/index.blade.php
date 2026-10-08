<x-layout title="Events · ChurchFlow">
    @php
        $canManage = auth()->user()->hasPermission('events.manage');
        $branches = \App\Models\OrganizationalUnit::query()->orderBy('name')->get();
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>Events</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                {{ number_format($events->total()) }} {{ \Illuminate\Support\Str::plural('event', $events->total()) }} ·
                registrations are checked against capacity, so the last slot can only go to one person.
            </p>
        </div>
        @if($canManage)
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="event-create" aria-expanded="false">
                <x-ui.icon name="calendar" class="h-4 w-4" /> Create Event
            </button>
        @endif
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    @if($canManage)
        <div data-panel="event-create" hidden style="margin-bottom:1.2rem">
            <x-form.card
                title="Create an event"
                description="Leave capacity empty for an event with no limit — registration then never waitlists anyone."
                :action="route('events.store')"
                submit="Create event"
                submit-icon="calendar"
                layout="inline"
            >
                <x-form.input name="title" label="Event title" required placeholder="e.g. Sunday Celebration Service" />
                <x-form.input name="venue" label="Venue" placeholder="e.g. Main Auditorium" />

                <x-form.input name="starts_at" label="Starts at" type="datetime-local" required />
                <x-form.input name="ends_at" label="Ends at" type="datetime-local" hint="Must be after the start time." />

                <x-form.input name="capacity" label="Capacity" type="number" min="1"
                    hint="Maximum attendees. Leave empty for unlimited." />

                @if($branches->isNotEmpty())
                    <x-form.select name="organizational_unit_id" label="Branch" placeholder="Whole church"
                        :options="$branches->map(fn ($b) => ['value' => $b->id, 'label' => $b->name])->all()" />
                @endif

                <x-form.textarea name="description" label="Description" rows="3" wide />
            </x-form.card>
        </div>
    @endif

    <div class="cf-card cf-card--flush">
        @if($events->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No events yet</p>
                <p class="cf-small cf-muted">
                    @if($canManage)
                        Create your first event above — it will appear on the calendar and the dashboard automatically.
                    @else
                        Events created by your team will appear here.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>When</th>
                            <th>Venue</th>
                            <th style="text-align:right">Capacity</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($events as $event)
                            <tr>
                                <td><a href="{{ route('events.show', $event) }}">{{ $event->title }}</a></td>
                                <td class="cf-small" style="white-space:nowrap">
                                    {{ $event->starts_at?->format('d M Y, H:i') }}
                                </td>
                                <td class="cf-small cf-muted">{{ $event->venue ?: '—' }}</td>
                                <td class="cf-mono" style="text-align:right">
                                    {{ $event->capacity ? number_format($event->capacity) : 'Unlimited' }}
                                </td>
                                <td>
                                    @php $tone = match ($event->status) {
                                        'cancelled' => 'cf-badge--danger',
                                        'completed' => 'cf-badge--ok',
                                        default => 'cf-badge--brand',
                                    }; @endphp
                                    <span class="cf-badge {{ $tone }}">{{ ucfirst($event->status ?? 'scheduled') }}</span>
                                </td>
                                <td style="text-align:right;white-space:nowrap">
                                    <a href="{{ route('events.show', $event) }}" class="cf-btn cf-btn--secondary cf-btn--sm">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding:1rem 1.35rem">{{ $events->links() }}</div>
        @endif
    </div>

    @push('scripts')
        <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
            (function () {
                var btn = document.querySelector('[data-toggle="event-create"]');
                var panel = document.querySelector('[data-panel="event-create"]');
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

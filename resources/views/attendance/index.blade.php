<x-layout title="Attendance · ChurchFlow">
    @php $canTake = auth()->user()->hasPermission('attendance.manage'); @endphp

    <div class="cf-page-head">
        <div>
            <h1>Attendance</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                {{ number_format($sessions->total()) }}
                {{ \Illuminate\Support\Str::plural('session', $sessions->total()) }} ·
                attendance is recorded for a whole roster at once, not one member at a time.
            </p>
        </div>
        @if ($canTake)
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="session-create"
                aria-expanded="false">
                <x-ui.icon name="check" class="h-4 w-4" /> Start a session
            </button>
        @endif
    </div>

    @if (session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">{{ session('status') }}</p>
            </div>
        </div>
    @endif

    @if ($canTake)
        <div data-panel="session-create" hidden style="margin-bottom:1.2rem">
            <x-form.card title="Start an attendance session"
                description="A session is one occasion you are taking attendance for. Once created you can mark the whole roster in a single submit."
                :action="route('attendance.store')" submit="Start session" layout="inline">
                <x-form.input name="name" label="Session name" required placeholder="e.g. Sunday First Service" />

                <x-form.select name="type" label="Session type" :value="'service'" :options="[
                    'service' => 'Service',
                    'event' => 'Event',
                    'department' => 'Department meeting',
                    'group' => 'Group meeting',
                ]" />

                <x-form.input name="session_date" label="Date" type="date" :value="now()->toDateString()" required />

                <x-form.textarea name="notes" label="Notes" rows="2" wide />
            </x-form.card>
        </div>
    @endif

    <div class="cf-card cf-card--flush">
        @if ($sessions->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No attendance sessions yet</p>
                <p class="cf-small cf-muted">
                    @if ($canTake)
                        Start a session above, then mark the roster in one go.
                    @else
                        Sessions recorded by your team will appear here.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Session</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sessions as $session)
                            <tr>
                                <td><a href="{{ route('attendance.show', $session) }}">{{ $session->name }}</a></td>
                                <td><span class="cf-badge">{{ ucfirst($session->type) }}</span></td>
                                <td class="cf-small cf-muted" style="white-space:nowrap">
                                    {{ $session->session_date?->format('d M Y') }}
                                </td>
                                <td style="text-align:right">
                                    <a href="{{ route('attendance.show', $session) }}"
                                        class="cf-btn cf-btn--secondary cf-btn--sm">Take attendance</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding:1rem 1.35rem">{{ $sessions->links() }}</div>
        @endif
    </div>

    @push('scripts')
        <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
            (function() {
                var btn = document.querySelector('[data-toggle="session-create"]');
                var panel = document.querySelector('[data-panel="session-create"]');
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
                if (panel.querySelector('[aria-invalid="true"]')) {
                    panel.removeAttribute('hidden');
                }
            })();
        </script>
    @endpush
</x-layout>

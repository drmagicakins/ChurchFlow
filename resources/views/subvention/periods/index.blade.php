<x-layout title="Subvention Periods · ChurchFlow">
    @php
        $canManage = auth()->user()->hasPermission('subvention.manage');
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>Subvention periods</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                A period is the window a subvention is filed for — usually a month or a quarter. Each branch may
                file at most one submission per period.
            </p>
        </div>
        @if($canManage)
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="period-create" aria-expanded="false">
                <x-ui.icon name="calendar" class="h-4 w-4" /> New period
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
        <div data-panel="period-create" hidden style="margin-bottom:1.2rem">
            <x-form.card title="Create a period"
                description="Give it a name you'll recognise on a report (e.g. “Q3 2026”), then set the window it covers."
                :action="route('subvention.periods.store')" submit="Create period" submit-icon="calendar" layout="inline">
                <x-form.input name="name" label="Name" required placeholder="e.g. Q3 2026" />
                <x-form.input name="period_start" label="Start date" type="date" required />
                <x-form.input name="period_end" label="End date" type="date" required />
            </x-form.card>
        </div>
    @endif

    <div class="cf-card cf-card--flush">
        @if($periods->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No periods yet</p>
                <p class="cf-small cf-muted">
                    @if($canManage)
                        Create one above before recording a submission.
                    @else
                        A period will appear here once someone with subvention access creates one.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Window</th>
                            <th>Status</th>
                            <th style="text-align:right">Submissions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($periods as $period)
                            <tr>
                                <td><strong>{{ $period->name }}</strong></td>
                                <td class="cf-small cf-muted">
                                    {{ $period->period_start?->toFormattedDateString() ?? '—' }}
                                    – {{ $period->period_end?->toFormattedDateString() ?? '—' }}
                                </td>
                                <td>
                                    @php $tone = match ($period->status) { 'open' => 'cf-badge--ok', 'closed' => '', default => 'cf-badge--brand' }; @endphp
                                    <span class="cf-badge {{ $tone }}">{{ ucfirst($period->status ?? 'open') }}</span>
                                </td>
                                <td class="cf-mono" style="text-align:right">{{ $period->submissions_count ?? $period->submissions()->count() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($canManage)
        @push('scripts')
            <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
                (function () {
                    var btn = document.querySelector('[data-toggle="period-create"]');
                    var panel = document.querySelector('[data-panel="period-create"]');
                    if (!btn || !panel) return;
                    btn.addEventListener('click', function () {
                        var open = !panel.hasAttribute('hidden');
                        if (open) { panel.setAttribute('hidden', ''); }
                        else { panel.removeAttribute('hidden'); panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
                        btn.setAttribute('aria-expanded', String(!open));
                    });
                    if (panel.querySelector('[aria-invalid="true"]')) { panel.removeAttribute('hidden'); }
                })();
            </script>
        @endpush
    @endif
</x-layout>

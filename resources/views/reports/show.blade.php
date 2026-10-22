<x-layout :title="$report['title'] . ' · Reports · ChurchFlow'">
    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin-bottom:.35rem">
                <a href="{{ route('reports.index') }}">← All reports</a>
            </p>
            <h1>{{ $report['title'] }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">{{ $report['description'] }}</p>
        </div>
        @if ($enabled)
            <a href="{{ route('reports.export', ['report' => $report['key'], 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'branch' => $branchId]) }}"
                class="cf-btn cf-btn--secondary cf-btn--sm">Export CSV</a>
        @endif
    </div>

    @unless ($enabled)
        <div class="cf-alert cf-alert--warn" role="status">
            <div class="cf-alert__body">
                <p class="cf-alert__title">This report is not included in your current plan.</p>
                <p class="cf-small cf-muted" style="margin:.25rem 0 .6rem">
                    @if ($requiredPlan)
                        Available on the <strong>{{ $requiredPlan }}</strong> plan and above.
                    @endif
                    The figures below are a live preview; exporting and printing unlock on upgrade.
                </p>
                @if (Route::has('billing.plans'))
                    <a href="{{ route('billing.plans') }}" class="cf-btn cf-btn--primary cf-btn--sm">See plans</a>
                @endif
            </div>
        </div>
    @endunless

    {{-- Filters: the range and (where meaningful) a branch. A plain GET form,
         so a filtered report is a real, shareable, bookmarkable URL — not a
         client-side view state that disappears on refresh. --}}
    <form method="GET" action="{{ route('reports.show', $report['key']) }}" class="cf-card"
        style="margin-bottom:1.2rem">
        <div class="cf-card__body" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
            <div class="cf-field">
                <label class="cf-label" for="filter-from">From</label>
                <input type="date" class="cf-input" id="filter-from" name="from"
                    value="{{ $from->toDateString() }}">
            </div>
            <div class="cf-field">
                <label class="cf-label" for="filter-to">To</label>
                <input type="date" class="cf-input" id="filter-to" name="to" value="{{ $to->toDateString() }}">
            </div>
            @if ($report['key'] !== 'branch_performance' && $branches->isNotEmpty())
                <div class="cf-field">
                    <label class="cf-label" for="filter-branch">Branch</label>
                    <select class="cf-select" id="filter-branch" name="branch">
                        <option value="">All branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button type="submit" class="cf-btn cf-btn--primary cf-btn--sm">Apply</button>
        </div>
    </form>

    @if (!empty($payload['summary']))
        <div class="cfd-kpis" style="margin-bottom:1.2rem">
            @foreach ($payload['summary'] as $figure)
                <x-dashboard.kpi :label="$figure['label']" :value="$figure['value']" icon="chart-up" tone="blue" />
            @endforeach
        </div>
    @endif

    @if (!empty($payload['chart']) && count($payload['chart']['labels'] ?? []) > 0)
        <section class="cfd-panel" style="margin-bottom:1.2rem">
            <div class="cfd-panel__head">
                <h2><span class="cfd-ico cfd-ico--sm cfd-ico--blue"><x-ui.icon name="chart-up" class="h-4 w-4" /></span>
                    Trend</h2>
                <span class="cf-tiny cf-muted">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</span>
            </div>
            <div class="cfd-chartbox"><canvas id="cf-report-chart" role="img"
                    aria-label="Trend for {{ $report['title'] }}"></canvas></div>
        </section>
    @endif

    <div class="cf-card cf-card--flush">
        <div class="cf-card__head">
            <h2>Detail</h2>
            <span class="cf-tiny cf-muted">{{ number_format(count($payload['rows'])) }}
                {{ \Illuminate\Support\Str::plural('row', count($payload['rows'])) }}</span>
        </div>

        @if (empty($payload['rows']))
            <div class="cf-empty">
                <p class="cf-empty__title">No data for this period</p>
                <p class="cf-small cf-muted">Adjust the
                    dates{{ $branches->isNotEmpty() && $report['key'] !== 'branch_performance' ? ' or branch' : '' }}
                    above and apply.</p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            @foreach ($payload['columns'] as $column)
                                <th @if ($loop->index > 0 && !in_array($column, ['Category', 'Status', 'Type', 'Branch', 'Session'])) style="text-align:right" @endif>
                                    {{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payload['rows'] as $row)
                            <tr>
                                @foreach ($payload['columns'] as $column)
                                    <td
                                        @if ($loop->index > 0 && !in_array($column, ['Category', 'Status', 'Type', 'Branch', 'Session'])) class="cf-mono" style="text-align:right;white-space:nowrap" @endif>
                                        {{ $row[$column] ?? '—' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if (!empty($payload['chart']) && count($payload['chart']['labels'] ?? []) > 0)
        @push('scripts')
            <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
                (function() {
                    const data = @json($payload['chart']);
                    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    const fmt = (v) => new Intl.NumberFormat(undefined, {
                        notation: 'compact'
                    }).format(v);
                    const palette = ['#1677FF', '#18B981', '#F59E0B', '#EF4444'];
                    new Chart(document.getElementById('cf-report-chart'), {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: data.series.map((s, i) => ({
                                label: s.label,
                                data: s.data,
                                borderColor: palette[i % palette.length],
                                backgroundColor: palette[i % palette.length] + '22',
                                tension: .3,
                                fill: true,
                                pointRadius: 3,
                            })),
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: reduce ? false : undefined,
                            plugins: {
                                legend: {
                                    position: 'top',
                                    align: 'end',
                                    labels: {
                                        usePointStyle: true,
                                        boxWidth: 8
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: fmt
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    }
                                }
                            },
                        },
                    });
                })();
            </script>
        @endpush
    @endif
</x-layout>

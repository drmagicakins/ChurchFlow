@php
    // Church-local greeting and date, per the church's configured timezone.
    $tz = $church?->timezone ?: config('app.timezone');
    $now = now($tz);
    $greeting = $now->hour < 12 ? 'Good morning' : ($now->hour < 17 ? 'Good afternoon' : 'Good evening');
    $currency = $church?->currency ?: 'NGN';
    $symbol = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€'][$currency] ?? $currency.' ';
    $money = fn ($v) => $symbol.number_format((float) $v);
    $seriesHasData = $financialSeries && (array_sum($financialSeries['income']) + array_sum($financialSeries['expenses'])) > 0;
@endphp

<x-layout title="Dashboard · ChurchFlow">
    <div class="cf-page-head">
        <div>
            <h1>{{ $greeting }}, {{ auth()->user()->name }} 👋</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">Here's what's happening with {{ $church?->name ?? 'your church' }} today.</p>
        </div>
        <p class="cf-small cf-muted">{{ $now->format('l, M j, Y') }}</p>
    </div>

    @if ($memberStats || $financeStats)
        <div class="cfd-kpis" style="margin-bottom:1.1rem">
            @if ($memberStats)
                <x-dashboard.kpi label="Total Members" :value="number_format($memberStats['total'])" icon="users" tone="blue" :pct="$memberStats['totalTrendPct']" />
                <x-dashboard.kpi label="Active Members" :value="number_format($memberStats['active'])" icon="family" tone="green" :pct="$memberStats['activeTrendPct']" />
            @endif
            @if ($financeStats)
                <x-dashboard.kpi label="Income" :value="$money($financeStats['income'])" icon="wallet" tone="green" :pct="$financeStats['incomeTrendPct']" />
                <x-dashboard.kpi label="Expenses" :value="$money($financeStats['expenses'])" icon="naira" tone="orange" :pct="$financeStats['expensesTrendPct']" :invert="true" />
            @endif
        </div>
    @endif

    <div class="cfd-layout">
        <div class="cfd-stack">
            @if ($financialSeries)
                <section class="cfd-panel">
                    <div class="cfd-panel__head">
                        <h2><span class="cfd-ico cfd-ico--sm cfd-ico--blue"><x-ui.icon name="chart-up" class="h-4 w-4" /></span> Financial Overview</h2>
                        <span class="cf-tiny cf-muted">Last 6 months</span>
                    </div>
                    @if ($seriesHasData)
                        <div class="cfd-chartbox"><canvas id="cfd-finance-chart" role="img" aria-label="Monthly income and expenses for the last six months"></canvas></div>
                    @else
                        <p class="cfd-empty">No financial transactions found. @if (auth()->user()->hasPermission('finance.record'))<a href="{{ route('finance.accounts.index') }}">Record a transaction</a>@endif</p>
                    @endif
                </section>
            @endif

            @if ($activitySummary || $recentActivities !== null)
                <div class="cfd-two">
                    @if ($activitySummary)
                        <section class="cfd-panel">
                            <div class="cfd-panel__head"><h2>Activity Summary</h2><span class="cf-tiny cf-muted">This month</span></div>
                            <div class="cfd-metrics" style="grid-template-columns:repeat(2,minmax(0,1fr))">
                                <div class="cfd-metric"><span>New Members</span><strong>{{ number_format($activitySummary['newMembers']) }}</strong></div>
                                <div class="cfd-metric"><span>Payments Received</span><strong>{{ $money($activitySummary['paymentsReceived']) }}</strong></div>
                                <div class="cfd-metric"><span>Events Held</span><strong>{{ number_format($activitySummary['eventsHeld']) }}</strong></div>
                                <div class="cfd-metric"><span>Announcements</span><strong>{{ number_format($activitySummary['messagesSent']) }}</strong></div>
                            </div>
                        </section>
                    @endif

                    @if ($recentActivities !== null)
                        <section class="cfd-panel">
                            <div class="cfd-panel__head"><h2>Recent Activities</h2></div>
                            @forelse ($recentActivities as $a)
                                <div class="cfd-row">
                                    <span class="cfd-ico cfd-ico--sm cfd-ico--{{ $a['tone'] }}"><x-ui.icon :name="$a['icon']" class="h-4 w-4" /></span>
                                    <div><p class="cfd-row__title">{{ $a['label'] }}</p><p class="cfd-row__sub">{{ $a['when']->diffForHumans() }}</p></div>
                                </div>
                            @empty
                                <p class="cfd-empty">No activity yet. Actions across the app will appear here.</p>
                            @endforelse
                        </section>
                    @endif
                </div>
            @endif

            @if ($departmentsOverview !== null)
                <section class="cfd-panel">
                    <div class="cfd-panel__head"><h2>Departments &amp; Branches</h2><a href="{{ route('departments.index') }}">View All</a></div>
                    @if ($departmentsOverview->isEmpty())
                        <p class="cfd-empty">No departments yet. <a href="{{ route('departments.index') }}">Add your first department</a></p>
                    @else
                        <div class="cfd-depts">
                            @foreach ($departmentsOverview as $d)
                                <div class="cfd-dept">
                                    <span class="cfd-ico cfd-ico--{{ $d['tone'] }}"><x-ui.icon :name="$d['icon']" /></span>
                                    <div><p class="cfd-row__title">{{ $d['name'] }}</p><p class="cfd-row__sub">{{ $d['subtitle'] }}</p></div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif
        </div>

        <aside class="cfd-stack">
            @if ($quickActions->isNotEmpty())
                <section class="cfd-panel">
                    <div class="cfd-panel__head"><h2>Quick Actions</h2></div>
                    <div class="cfd-actions">
                        @foreach ($quickActions as $qa)
                            <a href="{{ route($qa['route']) }}" class="cfd-action">
                                <span class="cfd-ico cfd-ico--{{ $qa['tone'] }}"><x-ui.icon :name="$qa['icon']" /></span>{{ $qa['label'] }}
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="cfd-panel">
                <div class="cfd-panel__head"><h2>Upcoming Events</h2><a href="{{ route('events.index') }}">View All</a></div>
                @forelse ($upcomingEvents->take(3) as $event)
                    <a href="{{ route('events.show', $event) }}" class="cfd-row">
                        <span class="cfd-ico cfd-ico--sm cfd-ico--purple"><x-ui.icon name="calendar" class="h-4 w-4" /></span>
                        <div><p class="cfd-row__title">{{ $event->title }}</p><p class="cfd-row__sub">{{ $event->starts_at?->timezone($tz)->format('M j, Y • g:i A') }}</p></div>
                    </a>
                @empty
                    <p class="cfd-empty">No upcoming events. <a href="{{ route('events.index') }}">Create an event</a></p>
                @endforelse
            </section>

            <section class="cfd-panel">
                <div class="cfd-panel__head"><h2>Today's Schedule</h2><a href="{{ route('calendar.index') }}">View Calendar</a></div>
                @if ($todaysSchedule->isEmpty())
                    <p class="cfd-empty">Nothing scheduled for today.</p>
                @else
                    <div class="cfd-timeline">
                        @foreach ($todaysSchedule as $event)
                            <div class="cfd-timeline__item">
                                <span class="cfd-timeline__time">{{ $event->starts_at->timezone($tz)->format('h:i A') }}</span>
                                <div><p class="cfd-row__title">{{ $event->title }}</p>@if ($event->venue)<p class="cfd-row__sub">{{ $event->venue }}</p>@endif</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            @if (Route::has('support'))
                <div class="cfd-promo">
                    <h3>Building a stronger church community</h3>
                    <p>Questions about getting the most from ChurchFlow? Our team is here to help.</p>
                    <a href="{{ route('support') }}" class="cf-btn cf-btn--primary cf-btn--sm">Get Support →</a>
                </div>
            @endif
        </aside>
    </div>

    <h2 class="cf-small cf-muted" style="margin:1.6rem 0 .8rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em">Operations</h2>

    {{-- Every panel is individually guarded: a null here means "this user lacks
         the permission", so the tile is hidden rather than shown as zero. --}}
    @if ($openTasks !== null || $pendingApprovals !== null || $activeLoans !== null)
        <div class="cf-grid cf-grid--4" style="margin-bottom:1.4rem">
            @if ($openTasks !== null)
                <div class="cf-card">
                    <div class="cf-stat">
                        <span class="cf-stat__label">Open tasks</span>
                        <span class="cf-stat__value">{{ number_format($openTasks) }}</span>
                        <span class="cf-stat__hint">
                            <a href="{{ route('tasks.index') }}">View tasks</a>
                        </span>
                    </div>
                </div>
            @endif

            @if ($pendingApprovals !== null)
                <div class="cf-card">
                    <div class="cf-stat">
                        <span class="cf-stat__label">Awaiting approval</span>
                        <span class="cf-stat__value">{{ number_format($pendingApprovals) }}</span>
                        <span class="cf-stat__hint">
                            <a href="{{ route('approvals.index') }}">Review queue</a>
                        </span>
                    </div>
                </div>
            @endif

            @if ($activeLoans !== null)
                <div class="cf-card">
                    <div class="cf-stat">
                        <span class="cf-stat__label">Active loans</span>
                        <span class="cf-stat__value">{{ number_format($activeLoans) }}</span>
                        <span class="cf-stat__hint">
                            <a href="{{ route('finance.loans.index') }}">Manage loans</a>
                        </span>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="cf-grid cf-grid--2">

        {{-- Next 30 days: events and open tasks merged by the same CalendarService
             the /calendar page uses, so the two can never disagree. --}}
        <div class="cf-card cf-card--flush">
            <div class="cf-card__head">
                <h2>Next 30 days</h2>
                <a href="{{ route('calendar.index') }}" class="cf-small">Calendar</a>
            </div>

            @if ($upcoming->isEmpty())
                <div class="cf-empty">
                    <p class="cf-empty__title">Nothing scheduled</p>
                    <p class="cf-small cf-muted">Events and open tasks will appear here.</p>
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
                            @foreach ($upcoming as $item)
                                <tr>
                                    <td class="cf-mono cf-small" style="white-space:nowrap">
                                        {{ \Illuminate\Support\Carbon::parse($item['date'])->format('d M') }}
                                    </td>
                                    <td>{{ $item['title'] }}</td>
                                    <td>
                                        <span
                                            class="cf-badge {{ $item['type'] === 'event' ? 'cf-badge--brand' : '' }}">
                                            {{ ucfirst($item['type']) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Announcements. --}}
        <div class="cf-card cf-card--flush">
            <div class="cf-card__head">
                <h2>Latest announcements</h2>
                <a href="{{ route('announcements.index') }}" class="cf-small">All</a>
            </div>

            @if ($recentAnnouncements->isEmpty())
                <div class="cf-empty">
                    <p class="cf-empty__title">No announcements yet</p>
                    <p class="cf-small cf-muted">Post one to reach your congregation.</p>
                </div>
            @else
                <div class="cf-card__body" style="display:grid;gap:1rem">
                    @foreach ($recentAnnouncements as $announcement)
                        <div>
                            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                                <strong class="cf-small">{{ $announcement->title }}</strong>
                                <span class="cf-badge">{{ $announcement->audience_type }}</span>
                            </div>
                            <p class="cf-tiny cf-muted" style="margin-top:.25rem">
                                {{ \Illuminate\Support\Str::limit($announcement->body ?? '', 110) }}
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Account balances: only for a user who can see finance at all. --}}
        @if ($accounts !== null)
            <div class="cf-card cf-card--flush">
                <div class="cf-card__head">
                    <h2>Account balances</h2>
                    <a href="{{ route('finance.accounts.index') }}" class="cf-small">All accounts</a>
                </div>

                @if ($accounts->isEmpty())
                    <div class="cf-empty">
                        <p class="cf-empty__title">No accounts yet</p>
                        <p class="cf-small cf-muted">Create an account to start recording income and expenses.</p>
                    </div>
                @else
                    <div class="cf-table-wrap">
                        <table class="cf-table">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th>Type</th>
                                    <th style="text-align:right">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($accounts as $account)
                                    <tr>
                                        <td>
                                            <a
                                                href="{{ route('finance.accounts.show', $account) }}">{{ $account->name }}</a>
                                        </td>
                                        <td><span class="cf-badge">{{ ucfirst($account->type) }}</span></td>
                                        <td class="cf-mono" style="text-align:right;white-space:nowrap">
                                            {{ number_format((float) $account->balance(), 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        {{-- Recent subventions. --}}
        @if ($subventions !== null)
            <div class="cf-card cf-card--flush">
                <div class="cf-card__head">
                    <h2>Recent subventions</h2>
                    <a href="{{ route('subvention.submissions.index') }}" class="cf-small">All</a>
                </div>

                @if ($subventions->isEmpty())
                    <div class="cf-empty">
                        <p class="cf-empty__title">No submissions yet</p>
                        <p class="cf-small cf-muted">Configure a rule set and period to begin.</p>
                    </div>
                @else
                    <div class="cf-table-wrap">
                        <table class="cf-table">
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th>Status</th>
                                    <th style="text-align:right">Remittance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($subventions as $submission)
                                    <tr>
                                        <td>
                                            <a href="{{ route('subvention.submissions.show', $submission) }}">
                                                {{ $submission->period?->name ?? '#' . $submission->id }}
                                            </a>
                                        </td>
                                        <td>
                                            @php
                                                $tone = match ($submission->status) {
                                                    'approved' => 'cf-badge--ok',
                                                    'rejected' => 'cf-badge--danger',
                                                    'returned' => 'cf-badge--warn',
                                                    'submitted', 'under_review' => 'cf-badge--brand',
                                                    default => '',
                                                };
                                            @endphp
                                            <span class="cf-badge {{ $tone }}">
                                                {{ str_replace('_', ' ', ucfirst($submission->status)) }}
                                            </span>
                                        </td>
                                        <td class="cf-mono" style="text-align:right;white-space:nowrap">
                                            {{ number_format((float) ($submission->latestCalculation?->remittance_amount ?? 0), 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @if ($seriesHasData)
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
            <script>
                (function () {
                    const data = @json($financialSeries);
                    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    const fmt = (v) => new Intl.NumberFormat(undefined, { notation: 'compact' }).format(v);
                    new Chart(document.getElementById('cfd-finance-chart'), {
                        type: 'bar',
                        data: { labels: data.labels, datasets: [
                            { label: 'Income', data: data.income, backgroundColor: '#18B981', borderRadius: 4 },
                            { label: 'Expenses', data: data.expenses, backgroundColor: '#1677FF', borderRadius: 4 },
                        ]},
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            animation: reduce ? false : undefined,
                            plugins: { legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8 } } },
                            scales: { y: { beginAtZero: true, ticks: { callback: fmt } }, x: { grid: { display: false } } },
                        },
                    });
                })();
            </script>
        @endpush
    @endif
</x-layout>

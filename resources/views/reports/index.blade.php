<x-layout title="Reports · ChurchFlow">
    <div class="cf-page-head">
        <div>
            <h1>Reports</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Understand what is happening across your church — attendance, growth, giving and branch performance,
                over any period you choose.
            </p>
        </div>
    </div>

    @unless ($enabled)
        {{-- §36: advanced_reports is a paid feature (Growth and up). Rather than
             hiding the whole page, the catalogue is shown with a clear lock, so
             the church can see what an upgrade buys — a hidden feature sells
             nothing, and a bare 403 tells the user nothing they can act on. --}}
        <div class="cf-alert cf-alert--warn" role="status">
            <div class="cf-alert__body">
                <p class="cf-alert__title">Advanced reports are not included in your current plan.</p>
                <p class="cf-small cf-muted" style="margin:.25rem 0 .6rem">
                    @if ($requiredPlan)
                        Available on the <strong>{{ $requiredPlan }}</strong> plan and above.
                    @endif
                    Upgrade to run any report below and export it to CSV.
                </p>
                @if (Route::has('billing.plans'))
                    <a href="{{ route('billing.plans') }}" class="cf-btn cf-btn--primary cf-btn--sm">See plans</a>
                @endif
            </div>
        </div>
    @endunless

    @if ($reports->isEmpty())
        <div class="cf-card">
            <div class="cf-empty">
                <p class="cf-empty__title">No reports available for your role</p>
                <p class="cf-small cf-muted">Reports appear here when your role has permission to see the underlying
                    data.</p>
            </div>
        </div>
    @else
        <div class="cf-grid cf-grid--2">
            @foreach ($reports as $report)
                <div class="cf-card">
                    <div class="cf-card__body" style="display:flex;gap:1rem;align-items:flex-start">
                        <span class="cfd-ico cfd-ico--blue"><x-ui.icon :name="$report['icon']" /></span>
                        <div style="flex:1">
                            <h2 class="cf-small" style="font-weight:700;margin:0 0 .3rem">
                                {{ $report['title'] }}
                                @unless ($enabled)
                                    <span class="cf-badge cf-badge--warn" style="margin-left:.4rem">Upgrade</span>
                                @endunless
                            </h2>
                            <p class="cf-small cf-muted" style="margin:0 0 .7rem">{{ $report['description'] }}</p>
                            <a href="{{ route('reports.show', $report['key']) }}"
                                class="cf-btn cf-btn--secondary cf-btn--sm">
                                Open report
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layout>

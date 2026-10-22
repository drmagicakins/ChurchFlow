<x-layout :title="$budget->name . ' · Budget · ChurchFlow'">
    @php
        $canManage = auth()->user()->hasPermission('budgets.manage');
        $symbol = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€'][$church?->currency ?? 'NGN'] ?? '';
        $money = fn ($v) => $symbol . number_format((float) $v, 2);

        $plannedTotal = $variance->sum(fn ($row) => (float) $row['planned']);
        $actualTotal = $variance->sum(fn ($row) => (float) $row['actual']);
        $varianceTotal = $plannedTotal - $actualTotal;
        $usedPct = $plannedTotal > 0 ? round(($actualTotal / $plannedTotal) * 100) : 0;
    @endphp

    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin:0"><a href="{{ route('finance.budgets.index') }}">← All budgets</a></p>
            <h1 style="margin:.35rem 0 0">{{ $budget->name }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.35rem">
                {{ $budget->period_start?->format('d M Y') }} – {{ $budget->period_end?->format('d M Y') }}
                · <span class="cf-badge">{{ ucfirst($budget->status) }}</span>
                · {{ $budget->financialAccount?->name ?? 'All accounts' }}
            </p>
        </div>
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

    <div class="cf-card" style="margin-bottom:1.2rem">
        <div class="cf-card__body" style="display:flex;gap:2rem;flex-wrap:wrap">
            <div class="cf-stat">
                <span class="cf-stat__label">Planned</span>
                <span class="cf-stat__value">{{ $money($plannedTotal) }}</span>
                <span class="cf-stat__hint">across {{ $variance->count() }} {{ \Illuminate\Support\Str::plural('category', $variance->count()) }}</span>
            </div>
            <div class="cf-stat">
                <span class="cf-stat__label">Actual spend</span>
                <span class="cf-stat__value">{{ $money($actualTotal) }}</span>
                <span class="cf-stat__hint">{{ $usedPct }}% of the plan</span>
            </div>
            <div class="cf-stat">
                <span class="cf-stat__label">{{ $varianceTotal >= 0 ? 'Remaining' : 'Over budget' }}</span>
                <span class="cf-stat__value" style="{{ $varianceTotal < 0 ? 'color:var(--danger,#b91c1c)' : '' }}">
                    {{ $money(abs($varianceTotal)) }}
                </span>
                <span class="cf-stat__hint">planned minus actual</span>
            </div>
        </div>
    </div>

    <div class="cf-card cf-card--flush">
        <div class="cf-card__head">
            <h2>Category variance</h2>
            <span class="cf-tiny cf-muted">Planned vs approved expenses of the same category</span>
        </div>

        @if($variance->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No planned lines on this budget</p>
                <p class="cf-small cf-muted">Add a category below to start tracking it.</p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th style="text-align:right">Planned</th>
                            <th style="text-align:right">Actual</th>
                            <th style="text-align:right">Variance</th>
                            <th>Progress</th>
                            @if($canManage)<th style="text-align:right">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($variance as $row)
                            @php
                                $planned = (float) $row['planned'];
                                $actual = (float) $row['actual'];
                                $variance = (float) $row['variance'];
                                $pct = $planned > 0 ? min(round(($actual / $planned) * 100), 999) : 0;
                                $over = $variance < 0;
                            @endphp
                            <tr>
                                <td>{{ str_replace('_', ' ', ucfirst($row['category'])) }}</td>
                                <td class="cf-mono" style="text-align:right;white-space:nowrap">{{ $money($planned) }}</td>
                                <td class="cf-mono" style="text-align:right;white-space:nowrap">{{ $money($actual) }}</td>
                                <td class="cf-mono" style="text-align:right;white-space:nowrap;{{ $over ? 'color:var(--danger,#b91c1c)' : 'color:var(--ok-ink,#047857)' }}">
                                    {{ $over ? '−' : '' }}{{ $money(abs($variance)) }}
                                </td>
                                <td style="min-width:9rem">
                                    <div style="height:.5rem;border-radius:999px;background:var(--surface-sunken,#f1f5f9);overflow:hidden">
                                        <div style="height:100%;width:{{ min($pct, 100) }}%;background:{{ $over ? 'var(--danger,#b91c1c)' : 'var(--cf-brand,#1677ff)' }}"></div>
                                    </div>
                                    <span class="cf-tiny cf-muted">{{ $pct }}%</span>
                                </td>
                                @if($canManage)
                                    <td style="text-align:right">
                                        @php $budgetItem = $budget->items->firstWhere('category', $row['category']); @endphp
                                        @if($budgetItem)
                                            <form method="POST" action="{{ route('finance.budgets.items.destroy', [$budget, $budgetItem]) }}" style="display:inline"
                                                data-confirm="Remove the {{ str_replace('_', ' ', $row['category']) }} line from this budget?"
                                                data-confirm-title="Remove budget line?" data-confirm-ok="Remove">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Remove</button>
                                            </form>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="font-weight:700">
                            <th style="text-align:left">Total</th>
                            <td class="cf-mono" style="text-align:right;white-space:nowrap">{{ $money($plannedTotal) }}</td>
                            <td class="cf-mono" style="text-align:right;white-space:nowrap">{{ $money($actualTotal) }}</td>
                            <td class="cf-mono" style="text-align:right;white-space:nowrap;{{ $varianceTotal < 0 ? 'color:var(--danger,#b91c1c)' : 'color:var(--ok-ink,#047857)' }}">
                                {{ $varianceTotal < 0 ? '−' : '' }}{{ $money(abs($varianceTotal)) }}
                            </td>
                            <td></td>
                            @if($canManage)<td></td>@endif
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>

    @if($canManage)
        <div style="margin-top:1.2rem">
            <x-form.card title="Add a planned line"
                description="Add a spending category to this budget. Its actual is matched against approved expenses with the same category name in the period."
                :action="route('finance.budgets.items.store', $budget)" submit="Add line" submit-icon="pie" layout="inline">
                <x-form.input name="category" label="Category" required placeholder="e.g. Utilities" />
                <x-form.input name="planned_amount" :label="'Planned amount ('.$symbol.')'" type="number" step="0.01" min="0.01" required placeholder="0.00" />
            </x-form.card>
        </div>
    @endif
</x-layout>

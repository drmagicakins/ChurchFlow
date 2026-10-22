<x-layout :title="$loan->borrowerLabel() . ' · Loan · ChurchFlow'">
    @php
        $canManage = auth()->user()->hasPermission('loans.manage');
        $symbol = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€'][$church?->currency ?? 'NGN'] ?? '';
        $money = fn ($v) => $symbol . number_format((float) $v, 2);
        $totalPaid = $loan->payments->sum(fn ($p) => (float) $p->amount);
        $principal = (float) $loan->principal_amount;
        $paidPct = $principal > 0 ? min(round(($totalPaid / $principal) * 100), 100) : 0;
    @endphp

    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin:0"><a href="{{ route('finance.loans.index') }}">← All loans</a></p>
            <h1 style="margin:.35rem 0 0">{{ $loan->borrowerLabel() }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.35rem">
                <span class="cf-badge {{ $loan->status === 'closed' ? 'cf-badge--ok' : ($loan->status === 'defaulted' ? 'cf-badge--danger' : 'cf-badge--brand') }}">{{ ucfirst($loan->status) }}</span>
                · {{ $loan->member_id ? 'Member loan' : 'Branch loan' }}
                · interest {{ rtrim(rtrim((string) $loan->interest_rate, '0'), '.') ?: '0' }}%
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
                <span class="cf-stat__label">Principal</span>
                <span class="cf-stat__value">{{ $money($principal) }}</span>
            </div>
            <div class="cf-stat">
                <span class="cf-stat__label">Total repaid</span>
                <span class="cf-stat__value">{{ $money($totalPaid) }}</span>
                <span class="cf-stat__hint">{{ $monthsPaid }} {{ \Illuminate\Support\Str::plural('month', $monthsPaid) }} of deductions</span>
            </div>
            <div class="cf-stat">
                <span class="cf-stat__label">Outstanding</span>
                <span class="cf-stat__value">{{ $money($outstanding) }}</span>
                <span class="cf-stat__hint">principal less repayments</span>
            </div>
        </div>
        <div class="cf-card__body" style="border-top:1px solid var(--line,#e5e7eb)">
            <div style="height:.6rem;border-radius:999px;background:var(--surface-sunken,#f1f5f9);overflow:hidden">
                <div style="height:100%;width:{{ $paidPct }}%;background:var(--cf-brand,#1677ff)"></div>
            </div>
            <p class="cf-tiny cf-muted" style="margin:.4rem 0 0">{{ $paidPct }}% repaid</p>
        </div>
    </div>

    @if($loan->notes)
        <div class="cf-card" style="margin-bottom:1.2rem">
            <div class="cf-card__body">
                <p class="cf-tiny cf-muted" style="margin:0 0 .2rem">Notes</p>
                <p class="cf-small" style="margin:0">{{ $loan->notes }}</p>
            </div>
        </div>
    @endif

    <div class="cf-grid cf-grid--2">
        {{-- Payment history --}}
        <div class="cf-card cf-card--flush">
            <div class="cf-card__head">
                <h2>Repayment history</h2>
                <span class="cf-tiny cf-muted">{{ $loan->payments->count() }} {{ \Illuminate\Support\Str::plural('payment', $loan->payments->count()) }}</span>
            </div>

            @if($loan->payments->isEmpty())
                <div class="cf-empty">
                    <p class="cf-empty__title">No repayments recorded yet</p>
                    <p class="cf-small cf-muted">Use the form beside this to log the first deduction.</p>
                </div>
            @else
                <div class="cf-table-wrap">
                    <table class="cf-table">
                        <thead>
                            <tr><th>Date paid</th><th style="text-align:right">Amount</th></tr>
                        </thead>
                        <tbody>
                            @foreach($loan->payments->sortByDesc('paid_on') as $payment)
                                <tr>
                                    <td>{{ $payment->paid_on?->format('d M Y') ?? '—' }}</td>
                                    <td class="cf-mono" style="text-align:right;white-space:nowrap">{{ $money($payment->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Record a payment --}}
        @if($canManage && $loan->status === 'active')
            <x-form.card title="Record a repayment"
                description="Payments are appended to an immutable ledger — the outstanding balance recalculates from them. A loan closes automatically once it is fully repaid."
                :action="route('finance.loans.payments', $loan)" submit="Record payment" submit-icon="naira">
                <x-form.input name="amount" :label="'Amount ('.$symbol.')'" type="number" step="0.01" min="0.01" required
                    :value="(string) min((float) $loan->monthly_deduction, (float) $outstanding)" />
                <x-form.input name="paid_on" label="Date paid" type="date" :value="now()->toDateString()" required />
            </x-form.card>
        @elseif($canManage)
            <div class="cf-card">
                <div class="cf-card__body">
                    <p class="cf-small cf-muted" style="margin:0">
                        This loan is {{ $loan->status }} — no further repayments can be recorded against it.
                    </p>
                </div>
            </div>
        @endif
    </div>
</x-layout>

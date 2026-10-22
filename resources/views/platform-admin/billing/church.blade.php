<x-layout :title="$church->name.' · Billing · Platform Admin · ChurchFlow'">
    @php
        $symbol = '₦';
        $money = fn ($v) => $symbol.number_format((float) $v, 2);
        $statusTone = match ($church->status) {
            'active' => 'cf-badge--ok',
            'trial', 'trialing' => 'cf-badge--brand',
            'expired', 'cancelled', 'suspended' => 'cf-badge--danger',
            default => 'cf-badge--warn',
        };
    @endphp

    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin:0"><a href="{{ route('platform-admin.billing.index') }}">← All churches</a></p>
            <h1 style="margin:.35rem 0 0">{{ $church->name }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.35rem">
                <span class="cf-badge {{ $statusTone }}">{{ str_replace('_', ' ', ucfirst($church->status)) }}</span>
                @if($church->slug) · <span class="cf-mono">{{ $church->slug }}</span> @endif
                · joined {{ $church->created_at?->toFormattedDateString() ?? '—' }}
            </p>
        </div>
        <a href="{{ route('platform-admin.dashboard') }}" class="cf-btn cf-btn--secondary cf-btn--sm">Overview</a>
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    <div class="cf-grid cf-grid--2">
        <div class="cf-card">
            <div class="cf-card__head"><h2>Subscription</h2></div>
            <div class="cf-card__body">
                @if($subscription)
                    <div class="cf-stat" style="margin-bottom:1rem">
                        <span class="cf-stat__label">Plan</span>
                        <span class="cf-stat__value" style="font-size:1.4rem">{{ $subscription->plan?->name ?? '—' }}</span>
                        <span class="cf-stat__hint">
                            {{ str_replace('_', ' ', ucfirst($subscription->status)) }}
                            · billed {{ $subscription->billing_interval }}
                        </span>
                    </div>

                    <dl style="display:grid;grid-template-columns:auto 1fr;gap:.45rem 1rem;font-size:.9rem;margin:0">
                        <dt class="cf-muted">Current period ends</dt>
                        <dd style="margin:0">{{ $subscription->current_period_end?->toFormattedDateString() ?? '—' }}</dd>

                        @if($subscription->trial_ends_at)
                            <dt class="cf-muted">Trial ends</dt>
                            <dd style="margin:0">{{ $subscription->trial_ends_at?->toFormattedDateString() }}</dd>
                        @endif

                        @if($subscription->cancel_requested_at)
                            <dt class="cf-muted">Cancellation</dt>
                            <dd style="margin:0">Requested {{ $subscription->cancel_requested_at?->diffForHumans() }}</dd>
                        @endif
                    </dl>
                @else
                    <p class="cf-small cf-muted" style="margin:0">This church has no subscription record.</p>
                @endif
            </div>

            @if($subscription)
                <div class="cf-card__body" style="border-top:1px solid var(--line, #e5e7eb);display:flex;gap:.5rem;flex-wrap:wrap">
                    @if(! in_array($subscription->status, ['suspended', 'cancelled'], true))
                        <form method="POST" action="{{ route('platform-admin.billing.suspend', $church) }}"
                            onsubmit="return confirm('Suspend {{ $church->name }}? Their users will lose access until reactivated.')">
                            @csrf
                            <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Suspend church</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('platform-admin.billing.reactivate', $church) }}">
                            @csrf
                            <button type="submit" class="cf-btn cf-btn--primary cf-btn--sm">Reactivate church</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        <div class="cf-card cf-card--flush">
            <div class="cf-card__head">
                <h2>Invoices</h2>
                <span class="cf-tiny cf-muted">{{ $invoices->total() }} total</span>
            </div>

            @if($invoices->isEmpty())
                <div class="cf-empty">
                    <p class="cf-empty__title">No invoices</p>
                    <p class="cf-small cf-muted">Subscription and SMS-credit invoices will appear here.</p>
                </div>
            @else
                <div class="cf-table-wrap">
                    <table class="cf-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th style="text-align:right">Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoices as $invoice)
                                <tr>
                                    <td class="cf-small" style="white-space:nowrap">{{ $invoice->created_at?->format('d M Y') ?? '—' }}</td>
                                    <td><span class="cf-badge">{{ str_replace('_', ' ', $invoice->type) }}</span></td>
                                    <td class="cf-mono" style="text-align:right;white-space:nowrap">
                                        {{ $invoice->currency }} {{ number_format((float) $invoice->total(), 2) }}
                                    </td>
                                    <td>
                                        <span class="cf-badge {{ $invoice->status === 'paid' ? 'cf-badge--ok' : ($invoice->status === 'failed' ? 'cf-badge--danger' : 'cf-badge--warn') }}">
                                            {{ ucfirst($invoice->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($invoices->hasPages())
                    <div style="padding:1rem 1.2rem">{{ $invoices->links() }}</div>
                @endif
            @endif
        </div>
    </div>
</x-layout>

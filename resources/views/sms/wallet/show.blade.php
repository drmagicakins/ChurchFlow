<x-layout title="SMS wallet · ChurchFlow">
    @php
        $symbol = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€'][$church?->currency ?? 'NGN'] ?? '';
        $canManage = auth()->user()->hasPermission('sms.manage');
        $unitPrice = $pricePerUnit !== null ? (float) $pricePerUnit : null;
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>SMS wallet</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Every balance change is a ledger row — a purchase, a campaign reservation, or a refund. Nothing is
                ever adjusted silently.
            </p>
        </div>
        <a href="{{ route('sms.campaigns.index') }}" class="cf-btn cf-btn--secondary cf-btn--sm">← SMS campaigns</a>
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
        <div class="cf-card__body" style="display:flex;gap:2rem;flex-wrap:wrap;align-items:center">
            <div class="cf-stat">
                <span class="cf-stat__label">Current balance</span>
                <span class="cf-stat__value">{{ number_format($wallet->balance_units) }}</span>
                <span class="cf-stat__hint">SMS units available to send</span>
            </div>
            @if($unitPrice !== null)
                <div class="cf-stat">
                    <span class="cf-stat__label">Price per unit</span>
                    <span class="cf-stat__value">{{ $symbol }}{{ number_format($unitPrice, 2) }}</span>
                    <span class="cf-stat__hint">set by the platform</span>
                </div>
            @endif
        </div>
    </div>

    <div class="cf-grid cf-grid--2">
        {{-- Buy credits --}}
        <div class="cf-card">
            <div class="cf-card__head">
                <h2>Top up SMS credits</h2>
                <span class="cf-tiny cf-muted">Paid securely through the payment gateway</span>
            </div>
            <div class="cf-card__body">
                @if(! $canPurchase)
                    <p class="cf-small cf-muted" style="margin:0">
                        Buying SMS credits needs the <strong>billing</strong> permission. Ask your church owner to top
                        up, or to grant you access.
                    </p>
                @elseif($unitPrice === null)
                    <p class="cf-small cf-muted" style="margin:0">
                        SMS pricing has not been configured by the platform administrator yet. Please try again later.
                    </p>
                @else
                    <p class="cf-small cf-muted" style="margin:0 0 .8rem">
                        Choose a package. You are taken to the payment page to pay, and the credits land in this wallet
                        as soon as the payment is confirmed.
                    </p>

                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:.6rem">
                        @foreach($packages as $units)
                            <form method="POST" action="{{ route('sms.wallet.purchase') }}">
                                @csrf
                                <input type="hidden" name="package" value="{{ $units }}">
                                <button type="submit" class="cf-btn cf-btn--secondary cf-btn--block" style="width:100%;flex-direction:column;gap:.15rem;padding:.7rem">
                                    <strong>{{ number_format($units) }} credits</strong>
                                    <span class="cf-tiny cf-muted">{{ $symbol }}{{ number_format($units * $unitPrice, 2) }}</span>
                                </button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>

            @if($canManage)
                <div class="cf-card__body" style="border-top:1px solid var(--line,#e5e7eb)">
                    <p class="cf-tiny cf-muted" style="margin:0 0 .5rem">
                        <strong>Administrative top-up.</strong> For correcting a support issue — this adds credits without
                        a payment and is recorded as an adjustment.
                    </p>
                    <form method="POST" action="{{ route('sms.wallet.credit') }}" class="cf-form" style="display:flex;gap:.5rem;align-items:flex-end">
                        @csrf
                        <div class="cf-field" style="flex:1">
                            <label class="cf-label" for="credit-units">Units</label>
                            <input type="number" name="units" id="credit-units" class="cf-input" min="1" required placeholder="e.g. 500">
                        </div>
                        <div class="cf-field" style="flex:2">
                            <label class="cf-label" for="credit-reason">Reason</label>
                            <input type="text" name="description" id="credit-reason" class="cf-input" maxlength="255" placeholder="e.g. Goodwill credit">
                        </div>
                        <button type="submit" class="cf-btn cf-btn--secondary">Add</button>
                    </form>
                </div>
            @endif
        </div>

        {{-- Ledger --}}
        <div class="cf-card cf-card--flush">
            <div class="cf-card__head">
                <h2>Wallet ledger</h2>
                <span class="cf-tiny cf-muted">{{ number_format($transactions->total()) }} entries</span>
            </div>

            @if($transactions->isEmpty())
                <div class="cf-empty">
                    <p class="cf-empty__title">No SMS transactions yet</p>
                    <p class="cf-small cf-muted">Buying credits, sending a campaign and refunds all appear here.</p>
                </div>
            @else
                <div class="cf-table-wrap">
                    <table class="cf-table">
                        <thead>
                            <tr><th>Date</th><th>Type</th><th style="text-align:right">Units</th><th style="text-align:right">Balance after</th></tr>
                        </thead>
                        <tbody>
                            @foreach($transactions as $t)
                                <tr>
                                    <td class="cf-small" style="white-space:nowrap">{{ $t->created_at?->format('d M Y, H:i') }}</td>
                                    <td>
                                        @php $tone = match ($t->type) {
                                            'purchase', 'refund', 'adjustment' => 'cf-badge--ok',
                                            'debit' => 'cf-badge--brand',
                                            default => '',
                                        }; @endphp
                                        <span class="cf-badge {{ $tone }}">{{ ucfirst($t->type) }}</span>
                                        @if($t->description)
                                            <div class="cf-tiny cf-muted">{{ \Illuminate\Support\Str::limit($t->description, 50) }}</div>
                                        @endif
                                    </td>
                                    <td class="cf-mono" style="text-align:right;white-space:nowrap;{{ $t->units < 0 ? 'color:var(--danger,#b91c1c)' : 'color:var(--ok-ink,#047857)' }}">
                                        {{ $t->units > 0 ? '+' : '' }}{{ number_format($t->units) }}
                                    </td>
                                    <td class="cf-mono" style="text-align:right;white-space:nowrap">{{ number_format($t->balance_after) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if($transactions->hasPages())
        <div style="padding:1rem 1.35rem">{{ $transactions->links() }}</div>
    @endif
</x-layout>

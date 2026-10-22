<x-layout title="Billing &amp; Revenue · Platform Admin · ChurchFlow">
    @php
        $symbol = '₦';
        $money = fn ($v) => $symbol.number_format((float) $v, 2);
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>Billing &amp; revenue</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Every church on this installation, with subscription and SMS revenue kept separate — a single
                combined figure would hide a collapse in either one.
            </p>
        </div>
        <a href="{{ route('platform-admin.dashboard') }}" class="cf-btn cf-btn--secondary cf-btn--sm">← Overview</a>
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    <div class="cf-grid cf-grid--2" style="margin-bottom:1.4rem">
        <div class="cf-card">
            <div class="cf-card__head"><h2>Revenue by type</h2></div>
            <div class="cf-card__body">
                @forelse($revenueByType as $type => $total)
                    <div class="cf-stat" style="margin-bottom:.7rem">
                        <span class="cf-stat__label">{{ str_replace('_', ' ', ucfirst($type)) }}</span>
                        <span class="cf-stat__value" style="font-size:1.3rem">{{ $money($total) }}</span>
                    </div>
                @empty
                    <p class="cf-small cf-muted" style="margin:0">No paid invoices yet.</p>
                @endforelse
            </div>
        </div>

        <div class="cf-card">
            <div class="cf-card__head"><h2>Subscriptions by status</h2></div>
            <div class="cf-card__body">
                @if($subscriptionsByStatus->isEmpty())
                    <p class="cf-small cf-muted" style="margin:0">No subscriptions yet.</p>
                @else
                    <div style="display:flex;flex-wrap:wrap;gap:.5rem">
                        @foreach($subscriptionsByStatus as $status => $count)
                            @php $tone = match ($status) {
                                'active' => 'cf-badge--ok',
                                'trialing', 'pending' => 'cf-badge--brand',
                                'past_due', 'grace_period' => 'cf-badge--warn',
                                'expired', 'cancelled', 'suspended' => 'cf-badge--danger',
                                default => '',
                            }; @endphp
                            <span class="cf-badge {{ $tone }}" style="padding:.4rem .7rem;font-size:.82rem">
                                {{ str_replace('_', ' ', ucfirst($status)) }} · {{ number_format($count) }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="cf-card cf-card--flush">
        <div class="cf-card__head">
            <h2>Churches</h2>
            <span class="cf-tiny cf-muted">{{ $churches->total() }} total</span>
        </div>

        @if($churches->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No churches yet</p>
                <p class="cf-small cf-muted">A church appears here the moment its first owner completes signup.</p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Church</th>
                            <th>Status</th>
                            <th style="text-align:right">Branches</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($churches as $church)
                            <tr>
                                <td><a href="{{ route('platform-admin.billing.church', $church) }}">{{ $church->name }}</a></td>
                                <td>
                                    @php $tone = match ($church->status) {
                                        'active' => 'cf-badge--ok',
                                        'trial', 'trialing' => 'cf-badge--brand',
                                        'expired', 'cancelled', 'suspended' => 'cf-badge--danger',
                                        default => 'cf-badge--warn',
                                    }; @endphp
                                    <span class="cf-badge {{ $tone }}">{{ str_replace('_', ' ', ucfirst($church->status)) }}</span>
                                </td>
                                <td class="cf-mono" style="text-align:right">{{ $church->organizational_units_count }}</td>
                                <td style="text-align:right;white-space:nowrap">
                                    @if(! in_array($church->status, ['suspended', 'cancelled'], true))
                                        <form method="POST" action="{{ route('platform-admin.billing.suspend', $church) }}" style="display:inline"
                                            onsubmit="return confirm('Suspend {{ $church->name }}?')">
                                            @csrf
                                            <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Suspend</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('platform-admin.billing.reactivate', $church) }}" style="display:inline">
                                            @csrf
                                            <button type="submit" class="cf-btn cf-btn--primary cf-btn--sm">Reactivate</button>
                                        </form>
                                    @endif
                                    <a href="{{ route('platform-admin.billing.church', $church) }}" class="cf-btn cf-btn--secondary cf-btn--sm">Manage</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($churches->hasPages())
                <div style="padding:1rem 1.2rem">{{ $churches->links() }}</div>
            @endif
        @endif
    </div>
</x-layout>

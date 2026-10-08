<x-layout title="Platform Admin · ChurchFlow">
    @php
        $symbol = '₦';
        $money = fn ($v) => $symbol.number_format((float) $v, 2);
        // Conversion = churches paying (active) as a share of those who ever
        // started (active + trialing). Deliberately not "trialing vs expired":
        // expired churches are a churn measure, not a conversion one, and
        // mixing the two produces a number that moves for two different
        // reasons and therefore explains neither.
        $conversion = $paidOrTrialing > 0 ? round($activeCount / $paidOrTrialing * 100) : null;
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>Platform overview</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Every church on this installation. Subscription and SMS revenue are kept separate on
                purpose — a single combined figure would hide a collapse in either one.
            </p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a href="{{ route('platform-admin.billing.index') }}" class="cf-btn cf-btn--primary cf-btn--sm">
                <x-ui.icon name="wallet" class="h-4 w-4" /> Billing &amp; revenue
            </a>
            <a href="{{ route('platform-admin.settings.edit') }}" class="cf-btn cf-btn--secondary cf-btn--sm">Settings</a>
            <a href="{{ route('platform-admin.feature-flags.index') }}" class="cf-btn cf-btn--secondary cf-btn--sm">Feature flags</a>
        </div>
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    <div class="cf-grid cf-grid--4" style="margin-bottom:1.4rem">
        <div class="cf-card">
            <div class="cf-stat">
                <span class="cf-stat__label">Churches</span>
                <span class="cf-stat__value">{{ number_format($churchCount) }}</span>
                <span class="cf-stat__hint">{{ number_format($userCount) }} user accounts</span>
            </div>
        </div>

        <div class="cf-card">
            <div class="cf-stat">
                <span class="cf-stat__label">Paying subscriptions</span>
                <span class="cf-stat__value">{{ number_format($activeCount) }}</span>
                <span class="cf-stat__hint">
                    {{ number_format($trialingCount) }} on trial
                    @if($conversion !== null) · {{ $conversion }}% conversion @endif
                </span>
            </div>
        </div>

        <div class="cf-card">
            <div class="cf-stat">
                <span class="cf-stat__label">Subscription revenue</span>
                <span class="cf-stat__value" style="font-size:1.5rem">{{ $money($subscriptionRevenue) }}</span>
                <span class="cf-stat__hint">Paid invoices, all time</span>
            </div>
        </div>

        <div class="cf-card">
            <div class="cf-stat">
                <span class="cf-stat__label">SMS revenue</span>
                <span class="cf-stat__value" style="font-size:1.5rem">{{ $money($smsRevenue) }}</span>
                <span class="cf-stat__hint">{{ number_format($activePlanCount) }} of {{ number_format($planCount) }} plans active</span>
            </div>
        </div>
    </div>

    <div class="cf-grid cf-grid--2">
        {{-- Recent signups: what an operator checks after a signup wave. --}}
        <div class="cf-card cf-card--flush">
            <div class="cf-card__head">
                <h2>Recently created churches</h2>
                <a href="{{ route('platform-admin.billing.index') }}" class="cf-small">All churches</a>
            </div>

            @if($recentChurches->isEmpty())
                <div class="cf-empty">
                    <p class="cf-empty__title">No churches yet</p>
                    <p class="cf-small cf-muted">A church appears here the moment its first owner completes signup.</p>
                </div>
            @else
                <div class="cf-table-wrap">
                    <table class="cf-table">
                        <thead>
                            <tr><th>Church</th><th>Status</th><th style="text-align:right">Users</th><th style="text-align:right">Created</th></tr>
                        </thead>
                        <tbody>
                            @foreach($recentChurches as $church)
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
                                    <td class="cf-mono" style="text-align:right">{{ $church->users_count }}</td>
                                    <td class="cf-small cf-muted" style="text-align:right;white-space:nowrap">{{ $church->created_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- A support queue, not a report: these are the churches someone
             should actually look at today. --}}
        <div class="cf-card cf-card--flush">
            <div class="cf-card__head">
                <h2>Needs attention</h2>
                <span class="cf-tiny cf-muted">Past due, in grace, expired or suspended</span>
            </div>

            @if($needsAttention->isEmpty())
                <div class="cf-empty">
                    <p class="cf-empty__title">Nothing needs attention</p>
                    <p class="cf-small cf-muted">No church is behind on billing right now.</p>
                </div>
            @else
                <div class="cf-table-wrap">
                    <table class="cf-table">
                        <thead>
                            <tr><th>Church</th><th>Status</th><th style="text-align:right">Period ended</th></tr>
                        </thead>
                        <tbody>
                            @foreach($needsAttention as $subscription)
                                <tr>
                                    <td>
                                        @if($subscription->church)
                                            <a href="{{ route('platform-admin.billing.church', $subscription->church) }}">{{ $subscription->church->name }}</a>
                                        @else
                                            <span class="cf-muted">Deleted church #{{ $subscription->church_id }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php $tone = match ($subscription->status) {
                                            'past_due', 'grace_period' => 'cf-badge--warn',
                                            default => 'cf-badge--danger',
                                        }; @endphp
                                        <span class="cf-badge {{ $tone }}">{{ str_replace('_', ' ', ucfirst($subscription->status)) }}</span>
                                    </td>
                                    <td class="cf-small cf-muted" style="text-align:right;white-space:nowrap">
                                        {{ $subscription->current_period_end?->format('d M Y') ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Status distribution: a compact read of the whole book of business. --}}
    @if($statusBreakdown->isNotEmpty())
        <div class="cf-card" style="margin-top:1.2rem">
            <div class="cf-card__head"><h2>Subscriptions by status</h2></div>
            <div class="cf-card__body">
                <div style="display:flex;flex-wrap:wrap;gap:.6rem">
                    @foreach($statusBreakdown as $status => $count)
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
            </div>
        </div>
    @endif
</x-layout>

<x-layout title="Billing · ChurchFlow">
    @php
        $currencySymbol = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€'][$subscription?->plan?->currency ?? 'NGN'] ?? '';
        $money = fn ($v) => $currencySymbol.number_format((float) $v, 2);
        $isTrial = $subscription?->isTrialing() ?? false;
        $statusTone = match ($subscription?->status) {
            'trialing', 'active' => 'cf-badge--ok',
            'past_due', 'grace_period' => 'cf-badge--warn',
            'expired', 'cancelled', 'suspended' => 'cf-badge--danger',
            default => '',
        };
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>Billing</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Your subscription, invoices and SMS credit — all in one place.
            </p>
        </div>
        @if($subscription)
            <a href="{{ route('billing.plans') }}" class="cf-btn cf-btn--primary cf-btn--sm">
                {{ $isTrial ? 'Choose a plan →' : 'Change plan →' }}
            </a>
        @endif
    </div>

    {{-- The trial banner is the single most important thing on this page for a
         trialing church, so it comes first and states the deadline in days,
         not just a date — "3 days left" is a decision, "12 Oct 2026" is not. --}}
    @if($isTrial)
        <div class="cf-card" style="border-left:3px solid var(--cf-brand, #1677FF);margin-bottom:1.2rem">
            <div class="cf-card__body">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
                    <div>
                        <h2 style="margin:0 0 .3rem">
                            Your free trial — {{ $trialDaysRemaining }} {{ \Illuminate\Support\Str::plural('day', $trialDaysRemaining) }} left
                        </h2>
                        <p class="cf-small cf-muted" style="margin:0">
                            You're trialing <strong>{{ $subscription->plan->name }}</strong> until
                            {{ $subscription->trial_ends_at?->toFormattedDateString() }}.
                            @if($subscription->pendingPlan)
                                <br>You'll move to <strong>{{ $subscription->pendingPlan->name }}</strong> when your subscription begins.
                            @endif
                        </p>
                    </div>
                    <form method="POST" action="{{ route('billing.subscribe') }}">
                        @csrf
                        <button type="submit" class="cf-btn cf-btn--primary">
                            Subscribe now — {{ $money($subscription->pendingPlan?->priceFor($subscription->billing_interval) ?? $subscription->plan->priceFor($subscription->billing_interval)) }}/{{ $subscription->billing_interval === 'yearly' ? 'year' : 'month' }}
                        </button>
                    </form>
                </div>
                <p class="cf-tiny cf-muted" style="margin:.8rem 0 0">
                    Nothing is charged until you subscribe or your trial ends. Subscribe early and your paid period starts today —
                    you don't lose the days you have left.
                </p>
            </div>
        </div>
    @endif

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" style="margin-bottom:1.2rem">{{ session('status') }}</div>
    @endif
    @if($errors->has('billing'))
        <div class="cf-alert cf-alert--error" style="margin-bottom:1.2rem">{{ $errors->first('billing') }}</div>
    @endif

    <div class="cf-grid cf-grid--2">
        <div class="cf-card">
            <div class="cf-card__head">
                <h2>Subscription</h2>
                @if($subscription)
                    <span class="cf-badge {{ $statusTone }}">{{ str_replace('_', ' ', ucfirst($subscription->status)) }}</span>
                @endif
            </div>
            <div class="cf-card__body">
                @if($subscription)
                    <div class="cf-stat" style="margin-bottom:1rem">
                        <span class="cf-stat__label">Current plan</span>
                        <span class="cf-stat__value" style="font-size:1.4rem">{{ $subscription->plan->name }}</span>
                        <span class="cf-stat__hint">
                            {{ $money($subscription->plan->priceFor($subscription->billing_interval)) }}
                            per {{ $subscription->billing_interval === 'yearly' ? 'year' : 'month' }}
                        </span>
                    </div>

                    <dl style="display:grid;grid-template-columns:auto 1fr;gap:.45rem 1rem;font-size:.9rem">
                        <dt class="cf-muted">Billing interval</dt>
                        <dd style="margin:0">{{ ucfirst($subscription->billing_interval) }}</dd>

                        <dt class="cf-muted">{{ $isTrial ? 'Trial ends' : 'Next billing date' }}</dt>
                        <dd style="margin:0">{{ $subscription->current_period_end->toFormattedDateString() }}</dd>

                        @if($subscription->cancel_requested_at)
                            <dt class="cf-muted">Cancellation</dt>
                            <dd style="margin:0">
                                Requested — access continues until {{ $subscription->current_period_end->toFormattedDateString() }}.
                            </dd>
                        @endif
                    </dl>

                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1.1rem">
                        <a href="{{ route('billing.plans') }}" class="cf-btn cf-btn--secondary cf-btn--sm">Change plan</a>

                        {{-- Cancel is only meaningful once there is an actual paid
                             subscription to cancel; a trial simply ends. --}}
                        @if(!$isTrial && !$subscription->cancel_requested_at && in_array($subscription->status, ['active', 'past_due', 'grace_period'], true))
                            <form method="POST" action="{{ route('billing.cancel') }}">
                                @csrf
                                <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Cancel subscription</button>
                            </form>
                        @endif

                        @if(in_array($subscription->status, ['expired', 'cancelled', 'suspended'], true))
                            <form method="POST" action="{{ route('billing.reactivate') }}">
                                @csrf
                                <button type="submit" class="cf-btn cf-btn--primary cf-btn--sm">Reactivate</button>
                            </form>
                        @endif
                    </div>
                @else
                    <x-empty-state
                        title="No subscription yet"
                        message="Pick a plan to start your church on ChurchFlow." />
                    <a href="{{ route('billing.plans') }}" class="cf-btn cf-btn--primary cf-btn--sm">Choose a plan</a>
                @endif
            </div>
        </div>

        <div class="cf-card">
            <div class="cf-card__head">
                <h2>SMS Wallet</h2>
                <a href="{{ route('sms.wallet.show') }}" class="cf-small">Details</a>
            </div>
            <div class="cf-card__body">
                <div class="cf-stat">
                    <span class="cf-stat__label">Credit balance</span>
                    <span class="cf-stat__value">{{ number_format($smsWallet->balance_units) }}</span>
                    <span class="cf-stat__hint">units</span>
                </div>
                <p class="cf-small cf-muted" style="margin-top:.7rem">
                    Email is included with your subscription. Bulk SMS uses these credits — you always see the exact cost before a campaign sends.
                </p>
                <form method="POST" action="{{ route('billing.sms-credits') }}" style="margin-top:1rem">
                    @csrf
                    <label class="cf-small cf-muted" for="package">Buy credits</label>
                    <div style="display:flex;gap:.5rem;margin-top:.3rem">
                        <select name="package" id="package" class="cf-input" style="flex:1">
                            <option value="1000">1,000 units</option>
                            <option value="5000">5,000 units</option>
                            <option value="10000">10,000 units</option>
                            <option value="25000">25,000 units</option>
                        </select>
                        <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Buy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="cf-card cf-card--flush" style="margin-top:1.2rem">
        <div class="cf-card__head">
            <h2>Recent transactions</h2>
            <a href="{{ route('billing.invoices') }}" class="cf-small">All invoices</a>
        </div>

        @if($invoices->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No invoices yet</p>
                <p class="cf-small cf-muted">
                    @if($isTrial)
                        Nothing has been charged — you're still in your free trial.
                    @else
                        Your invoices will appear here once your subscription bills.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Type</th>
                            <th style="text-align:right">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                            <tr>
                                <td class="cf-small" style="white-space:nowrap">{{ $invoice->created_at->format('d M Y') }}</td>
                                <td>{{ $invoice->description }}</td>
                                <td><span class="cf-badge">{{ str_replace('_', ' ', $invoice->type) }}</span></td>
                                <td class="cf-mono" style="text-align:right;white-space:nowrap">
                                    {{ $invoice->currency }} {{ number_format((float) $invoice->amount, 2) }}
                                </td>
                                <td>
                                    <span class="cf-badge {{ $invoice->status === 'paid' ? 'cf-badge--ok' : '' }}">
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layout>

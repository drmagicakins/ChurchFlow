<x-layout title="Change plan · ChurchFlow">
    @php
        $symbol = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€'][$subscription->plan->currency ?? 'NGN'] ?? '';
        $money = fn ($v) => $symbol.number_format((float) $v, 2);
        $isUpgrade = bccomp((string) $newPlan->monthly_price, (string) $subscription->plan->monthly_price, 2) > 0;
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>{{ $isUpgrade ? 'Upgrade' : 'Downgrade' }} to {{ $newPlan->name }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                {{ $subscription->plan->name }} → {{ $newPlan->name }}
                @unless($isTrial)
                    · {{ $proration['days_remaining'] }} of {{ $proration['days_in_period'] }} days left in this period
                @endunless
            </p>
        </div>
        <a href="{{ route('billing.plans') }}" class="cf-btn cf-btn--secondary cf-btn--sm">← Back</a>
    </div>

    @if($isTrial)
        {{-- Nothing has been charged during a trial, so there is genuinely
             nothing to prorate. Showing a £0.00 breakdown here would read as
             "this change is free", which is a different and misleading
             statement — so the page says what actually happens instead. --}}
        <div class="cf-card" style="max-width:52ch">
            <div class="cf-card__body">
                <h2 style="margin:0 0 .5rem">No charge today</h2>
                <p class="cf-small cf-muted" style="margin:0 0 1rem">
                    You're still in your free trial ({{ $trialDaysRemaining }} {{ \Illuminate\Support\Str::plural('day', $trialDaysRemaining) }} left),
                    so nothing is prorated and nothing is charged now.
                    Confirming records <strong>{{ $newPlan->name }}</strong> as the plan you'll continue on
                    from {{ $subscription->trial_ends_at?->toFormattedDateString() }} — your trial itself is untouched.
                </p>
                <p class="cf-small" style="margin:0 0 1rem">
                    From then it will cost <strong>{{ $money($newPlan->priceFor($subscription->billing_interval)) }}
                    per {{ $subscription->billing_interval === 'yearly' ? 'year' : 'month' }}</strong>.
                </p>
                <form method="POST" action="{{ route('billing.change-plan') }}">
                    @csrf
                    <input type="hidden" name="plan_id" value="{{ $newPlan->id }}">
                    <button type="submit" class="cf-btn cf-btn--primary">Confirm — continue on {{ $newPlan->name }}</button>
                </form>
            </div>
        </div>
    @else
        <div class="cf-card" style="max-width:52ch">
            <div class="cf-card__body">
                <dl style="display:grid;grid-template-columns:1fr auto;gap:.55rem 1rem;font-size:.9rem;margin:0 0 1.1rem">
                    <dt class="cf-muted">Credit for unused time on {{ $subscription->plan->name }}</dt>
                    <dd style="margin:0;text-align:right">{{ $money($proration['credit']) }}</dd>

                    <dt class="cf-muted">Charge for remaining time on {{ $newPlan->name }}</dt>
                    <dd style="margin:0;text-align:right">{{ $money($proration['charge']) }}</dd>
                </dl>

                <p style="border-top:1px solid #e5e7eb;padding-top:.9rem;margin:0 0 1rem;display:flex;justify-content:space-between;gap:1rem">
                    <strong>Net {{ bccomp($proration['net'], '0', 2) >= 0 ? 'charge' : 'credit' }}</strong>
                    <strong>{{ $money(abs((float) $proration['net'])) }}</strong>
                </p>

                <p class="cf-tiny cf-muted" style="margin:0 0 1rem">
                    @if(bccomp($proration['net'], '0', 2) >= 0)
                        This is recorded against your account for the rest of the current billing period.
                    @else
                        A credit is recorded on your account — it is applied as a line item against future billing, not refunded immediately.
                    @endif
                </p>

                <form method="POST" action="{{ route('billing.change-plan') }}">
                    @csrf
                    <input type="hidden" name="plan_id" value="{{ $newPlan->id }}">
                    <button type="submit" class="cf-btn cf-btn--primary">Confirm plan change</button>
                </form>
            </div>
        </div>
    @endif
</x-layout>

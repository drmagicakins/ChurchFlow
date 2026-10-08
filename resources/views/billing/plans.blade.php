<x-layout title="Plans · ChurchFlow">
    @php
        $symbol =
            ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€'][$subscription?->plan?->currency ?? 'NGN'] ?? '';
        // Phase 12: formatted through Plan itself, so this page and the
        // landing/pricing pages can never render the same price differently.
        $money = fn($planOrAmount) => is_object($planOrAmount)
            ? $planOrAmount->displayPrice('monthly')
            : $symbol . number_format((float) $planOrAmount, 2);
        $currentMonthly =
            $subscription?->plan?->monthly_price !== null ? (float) $subscription->plan->monthly_price : null;
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>{{ $isTrial ? 'Choose your plan' : 'Change your plan' }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                @if ($isTrial)
                    You're on a free trial of {{ $subscription->plan->name }} —
                    {{ $trialDaysRemaining }} {{ \Illuminate\Support\Str::plural('day', $trialDaysRemaining) }} left.
                    Pick the plan you want to continue on.
                @else
                    Move up or down at any time. Upgrades are prorated for the days left in this period;
                    a downgrade records a credit you keep against future billing.
                @endif
            </p>
        </div>
        <a href="{{ route('billing.show') }}" class="cf-btn cf-btn--secondary cf-btn--sm">← Back to billing</a>
    </div>

    {{-- During a trial, choosing a plan does not change the plan you're
         trialing on — it records what you'll move to when you start paying.
         Saying that up front is the difference between "I picked Growth" and
         "why am I still being told I'm on Starter?" --}}
    @if ($isTrial)
        <div class="cf-alert"
            style="background:rgba(22,119,255,.08);border-color:rgba(22,119,255,.25);margin-bottom:1.2rem">
            <div>
                <strong>Your trial continues on {{ $subscription->plan->name }} until
                    {{ $subscription->trial_ends_at?->toFormattedDateString() }}.</strong>
                <p class="cf-small" style="margin:.35rem 0 0">
                    Choosing a different plan records it as your plan from the day your subscription starts —
                    your trial itself is unchanged.
                    @if ($pendingPlanId)
                        You're currently set to move to
                        <strong>{{ $plans->firstWhere('id', $pendingPlanId)?->name }}</strong>.
                    @endif
                </p>
            </div>
        </div>
    @endif

    <div class="cf-grid cf-grid--3">
        @foreach ($plans as $plan)
            @php
                $isCurrent = (int) $plan->id === (int) $currentPlanId;
                $isPending = (int) $plan->id === (int) $pendingPlanId;

                // Only comparable when both prices exist. A plan with no
                // monthly price is the bespoke/enterprise tier, which isn't
// an upgrade or a downgrade — it's a conversation.
                $planMonthly = $plan->monthly_price !== null ? (float) $plan->monthly_price : null;
                $direction =
                    $planMonthly !== null && $currentMonthly !== null && !$isCurrent
                        ? ($planMonthly > $currentMonthly
                            ? 'upgrade'
                            : 'downgrade')
                        : null;
            @endphp

            <div class="cf-card" style="{{ $isCurrent ? 'border:2px solid var(--cf-brand, #1677FF)' : '' }}">
                <div class="cf-card__body">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem">
                        <h3 style="margin:0">{{ $plan->name }}</h3>
                        @if ($isCurrent)
                            <span class="cf-badge cf-badge--brand">Current</span>
                        @elseif($isPending)
                            <span class="cf-badge cf-badge--ok">Selected</span>
                        @endif
                    </div>

                    <p class="cf-stat__value" style="font-size:1.6rem;margin:.7rem 0 .2rem">
                        {{ $plan->displayPrice('monthly') }}<span class="cf-small cf-muted">/month</span>
                    </p>
                    @if ($plan->yearly_price !== null)
                        <p class="cf-tiny cf-muted" style="margin:0">
                            or {{ $plan->displayPrice('yearly') }}/year — two months free
                        </p>
                    @endif

                    <ul class="cf-small" style="list-style:none;padding:0;margin:1rem 0;display:grid;gap:.4rem">
                        <li>{{ $plan->max_members ? number_format($plan->max_members) . ' members' : 'Unlimited members' }}
                        </li>
                        <li>{{ $plan->max_branches ? $plan->max_branches . ' ' . ($plan->max_branches > 1 ? 'branches' : 'branch') : 'Unlimited branches' }}
                        </li>
                        <li>{{ $plan->max_admins ? $plan->max_admins . ' admin logins' : 'Unlimited admin logins' }}</li>
                        @if ($plan->hasFeature('advanced_reports'))
                            <li>Advanced reports</li>
                        @endif
                        @if ($plan->hasFeature('api_access'))
                            <li>API access</li>
                        @endif
                        @if ($plan->hasFeature('custom_domain'))
                            <li>Custom domain</li>
                        @endif
                        <li>Email included</li>
                    </ul>

                    @if ($isCurrent)
                        <button type="button" class="cf-btn cf-btn--secondary cf-btn--sm" disabled
                            style="width:100%;opacity:.6">
                            Your current plan
                        </button>
                    @elseif($isTrial)
                        {{-- A trial records the choice; nothing is charged. --}}
                        <form method="POST" action="{{ route('billing.change-plan') }}">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                            <button type="submit" class="cf-btn cf-btn--primary cf-btn--sm" style="width:100%">
                                Continue on {{ $plan->name }}
                            </button>
                        </form>
                    @else
                        {{-- A paid plan change shows the prorated cost BEFORE committing. --}}
                        <a href="{{ route('billing.change-plan.preview', ['plan_id' => $plan->id]) }}"
                            class="cf-btn {{ $direction === 'upgrade' ? 'cf-btn--primary' : 'cf-btn--secondary' }} cf-btn--sm"
                            style="width:100%;text-align:center">
                            {{ $direction === 'upgrade' ? 'Upgrade' : ($direction === 'downgrade' ? 'Downgrade' : 'Switch') }}
                            to {{ $plan->name }}
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if ($isTrial)
        <div class="cf-card" style="margin-top:1.4rem">
            <div class="cf-card__body">
                <h2 style="margin:0 0 .4rem">Ready to start paying now?</h2>
                <p class="cf-small cf-muted" style="margin:0 0 .9rem">
                    Subscribe today and your paid period starts immediately — you keep whatever trial days you have left
                    as extra,
                    they are not wasted.
                </p>
                <form method="POST" action="{{ route('billing.subscribe') }}">
                    @csrf
                    <button type="submit" class="cf-btn cf-btn--primary cf-btn--sm">
                        Subscribe now
                        @if ($pendingPlanId)
                            on {{ $plans->firstWhere('id', $pendingPlanId)?->name }}
                        @else
                            on {{ $subscription->plan->name }}
                        @endif
                    </button>
                </form>
            </div>
        </div>
    @endif

    <p class="cf-tiny cf-muted" style="margin-top:1.4rem">
        Prices are set by the platform and shown in {{ $subscription?->plan?->currency ?? 'NGN' }}.
        Bulk SMS is billed separately by credits on every plan.
    </p>
</x-layout>

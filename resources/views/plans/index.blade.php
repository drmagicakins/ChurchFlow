<x-marketing-layout :title="'Plans & pricing · '.config('marketing.product.name')">
    @php
        // Phase 12: prices are stated, not implied. This page used to show a
        // bare monthly_price with no symbol and no yearly option, so the
        // figure the visitor saw here was the only number on the site with no
        // currency attached to it.
        $plans = app(\App\Domains\Subscriptions\Services\PlanCatalog::class)->forDisplay();
    @endphp

    <section class="mk-section">
        <div class="mk-shell">
            <div class="mk-head mk-head--center">
                <p class="mk-eyebrow" style="justify-content:center">Pricing</p>
                <h1 class="mk-h2" style="margin-top:.85rem">Choose your plan</h1>
                <p class="mk-lede" style="margin-top:1rem">
                    Every plan starts with a <strong>14-day free trial</strong> — no card required.
                    Email notifications are included with every plan; bulk SMS is available separately
                    through SMS credits.
                </p>
            </div>

            <div class="mk-pricing__grid mk-pricing__grid--page">
                @foreach($plans as $plan)
                    <article class="mk-card mk-card--pad mk-plan {{ $plan['popular'] ? 'mk-plan--featured' : '' }}">
                        @if($plan['popular'])
                            <span class="mk-plan__flag">Most popular</span>
                        @endif

                        <h2 class="mk-plan__name">{{ $plan['name'] }}</h2>
                        <p class="mk-plan__tagline">{{ $plan['tagline'] }}</p>

                        <p class="mk-plan__price">
                            <span class="mk-num">{{ $plan['price'] }}</span>
                            @if($plan['period'])
                                <span class="mk-plan__period">{{ $plan['period'] }}</span>
                            @endif
                        </p>
                        @if($plan['yearly_price'])
                            <p class="mk-plan__annual">or {{ $plan['yearly_price'] }} billed yearly</p>
                        @endif

                        <ul class="mk-checks mk-plan__features">
                            @foreach($plan['features'] as $feature)
                                <li><span>{{ $feature }}</span></li>
                            @endforeach
                        </ul>

                        @auth
                            @if($plan['is_self_serve'])
                                <a href="{{ route('checkout.review', $plan['plan_id']) }}"
                                   class="mk-btn {{ $plan['popular'] ? 'mk-btn--primary' : 'mk-btn--secondary' }} mk-btn--block mk-plan__cta">
                                    Choose {{ $plan['name'] }}
                                </a>
                            @else
                                <a href="{{ route('contact') }}" class="mk-btn mk-btn--secondary mk-btn--block mk-plan__cta">Talk to us</a>
                            @endif
                        @else
                            <a href="{{ route('register') }}"
                               class="mk-btn {{ $plan['popular'] ? 'mk-btn--primary' : 'mk-btn--secondary' }} mk-btn--block mk-plan__cta">
                                Start free trial
                            </a>
                        @endauth
                    </article>
                @endforeach
            </div>
        </div>
    </section>
</x-marketing-layout>

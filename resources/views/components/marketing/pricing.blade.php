{{--
 | Pricing.
 |
 | Three things this section must communicate, per the brief, or the visitor
 | arrives at the payment step misinformed:
 |
 |   1. Email is included in every plan.
 |   2. Bulk SMS is billed separately, pay-as-you-go.
 |   3. Payment is verified BEFORE the church workspace is created.
 |
 | Prices render from config and are null, which prints as "Price on request".
 | That is deliberate: the brief forbids inventing final pricing, and a made-up
 | number here would be read as a real price by whoever wires up billing next.
 | When real prices exist, fill in config('marketing.plans.*.price') and the
 | markup needs no change.
--}}

@php
    $plans = config('marketing.plans');
    $billingNotes = config('marketing.billing_notes');

    // The billing sequence, shown once, as a chain. It is the clearest way to
    // state an order of operations that a marketing page usually blurs.
    $journey = ['Choose plan', 'Pay', 'We verify', 'Church created', 'Dashboard'];
@endphp

<section class="mk-section mk-section--tint" id="pricing">
    <div class="mk-shell">
        <div class="mk-head mk-head--center" data-reveal>
            <p class="mk-eyebrow" style="justify-content: center">Pricing</p>
            <h2 class="mk-h2" style="margin-top: 0.85rem">Plans for Every Church</h2>
            <p class="mk-lede" style="margin-top: 1rem">
                Choose a plan that fits your church's size and needs. Upgrade, downgrade or cancel
                at any time — and your church workspace is created only after payment is verified.
            </p>
        </div>

        <div class="mk-pricing">
            {{-- Left: what is included / how billing works --}}
            <div class="mk-panel-dark mk-pricing__intro" data-reveal>
                <span class="mk-pricing__introicon">
                    <x-ui.icon name="wallet" class="h-5 w-5" />
                </span>
                <h3 class="mk-pricing__introtitle">How ChurchFlow billing works</h3>

                <ul class="mk-checks mk-checks--on-dark mk-pricing__notes">
                    @foreach ($billingNotes as $note)
                        <li><span>{{ $note }}</span></li>
                    @endforeach
                </ul>

                <div class="mk-pricing__journey">
                    <p class="mk-pricing__journeyhead">What happens after you choose a plan</p>
                    <ol class="mk-pricing__chain">
                        @foreach ($journey as $i => $stage)
                            <li>
                                <span class="mk-pricing__stagedot">{{ $i + 1 }}</span>
                                {{ $stage }}
                            </li>
                        @endforeach
                    </ol>
                </div>

                <p class="mk-pricing__fineprint">
                    Prices are shown as "Price on request" until billing is configured. No card
                    details are collected on this page.
                </p>
            </div>

            {{-- Right: the plans --}}
            <div class="mk-pricing__grid">
                @foreach ($plans as $i => $plan)
                    <article
                        class="mk-card mk-card--pad mk-plan {{ $plan['popular'] ? 'mk-plan--featured' : '' }}"
                        data-reveal
                        style="--reveal-delay: {{ $i * 70 }}ms"
                    >
                        @if ($plan['popular'])
                            <span class="mk-plan__flag">Most popular</span>
                        @endif

                        <h3 class="mk-plan__name">{{ $plan['name'] }}</h3>
                        <p class="mk-plan__tagline">{{ $plan['tagline'] }}</p>

                        <p class="mk-plan__price">
                            @if ($plan['price'])
                                <span class="mk-num">{{ $plan['price'] }}</span>
                                @if ($plan['period'])
                                    <span class="mk-plan__period">{{ $plan['period'] }}</span>
                                @endif
                            @else
                                <span class="mk-plan__pricepending">Price on request</span>
                            @endif
                        </p>

                        <ul class="mk-checks mk-plan__features">
                            @foreach ($plan['features'] as $feature)
                                <li><span>{{ $feature }}</span></li>
                            @endforeach
                        </ul>

                        <a
                            href="{{ $plan['key'] === 'enterprise' ? route('contact') : route('get-started', ['plan' => $plan['key']]) }}"
                            class="mk-btn {{ $plan['popular'] ? 'mk-btn--primary' : 'mk-btn--secondary' }} mk-btn--block mk-plan__cta"
                        >
                            {{ $plan['cta'] }}
                        </a>
                    </article>
                @endforeach
            </div>
        </div>
    </div>
</section>

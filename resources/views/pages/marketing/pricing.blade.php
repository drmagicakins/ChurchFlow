{{--
 | /pricing
 |
 | Standalone pricing page: the full comparison table plus the billing rules. The
 | home page carries the short version; this carries the detail a decision maker
 | wants before they commit, including what each plan does NOT include.
 |
 | Prices come from config and are null until billing is configured — see the note
 | in config/marketing.php. Nothing here invents a number.
--}}

@php
    $plans = config('marketing.plans');
    $billingNotes = config('marketing.billing_notes');

    // Feature comparison matrix. Kept next to the page rather than in config
    // because it is presentation of the same facts the plan cards state, and
    // duplicating it into config would create two sources of truth that drift.
    $matrix = [
        [
            'feature' => 'Members, families and groups',
            'starter' => true,
            'growth' => true,
            'denomination' => true,
            'enterprise' => true,
        ],
        [
            'feature' => 'Events and registrations',
            'starter' => true,
            'growth' => true,
            'denomination' => true,
            'enterprise' => true,
        ],
        [
            'feature' => 'Attendance and check-in',
            'starter' => true,
            'growth' => true,
            'denomination' => true,
            'enterprise' => true,
        ],
        [
            'feature' => 'Income and expense records',
            'starter' => true,
            'growth' => true,
            'denomination' => true,
            'enterprise' => true,
        ],
        [
            'feature' => 'Email notifications',
            'starter' => true,
            'growth' => true,
            'denomination' => true,
            'enterprise' => true,
        ],
        [
            'feature' => 'Budgets and loans',
            'starter' => false,
            'growth' => true,
            'denomination' => true,
            'enterprise' => true,
        ],
        [
            'feature' => 'Subvention workflow',
            'starter' => false,
            'growth' => true,
            'denomination' => true,
            'enterprise' => true,
        ],
        [
            'feature' => 'Pastoral care module',
            'starter' => false,
            'growth' => true,
            'denomination' => true,
            'enterprise' => true,
        ],
        [
            'feature' => 'Reports and analytics',
            'starter' => false,
            'growth' => true,
            'denomination' => true,
            'enterprise' => true,
        ],
        [
            'feature' => 'Multiple branches',
            'starter' => false,
            'growth' => false,
            'denomination' => true,
            'enterprise' => true,
        ],
        [
            'feature' => 'Consolidated group reporting',
            'starter' => false,
            'growth' => false,
            'denomination' => true,
            'enterprise' => true,
        ],
        [
            'feature' => 'Roles and permissions',
            'starter' => false,
            'growth' => false,
            'denomination' => true,
            'enterprise' => true,
        ],
        [
            'feature' => 'Custom modules and integrations',
            'starter' => false,
            'growth' => false,
            'denomination' => false,
            'enterprise' => true,
        ],
        [
            'feature' => 'Data migration assistance',
            'starter' => false,
            'growth' => false,
            'denomination' => false,
            'enterprise' => true,
        ],
    ];
@endphp

<x-marketing-layout title="Pricing — ChurchFlow"
    description="ChurchFlow plans for churches of every size. Email notifications included in every plan; bulk SMS billed separately, pay-as-you-go. Your church is created after verified payment.">
    <x-marketing.page-header eyebrow="Pricing" title="Simple plans," accent="no surprises."
        lede="Email is included in every plan. Bulk SMS is billed separately and pay-as-you-go, so a church that never sends bulk SMS never pays for it.">
        <x-slot:actions>
            <a href="#plans" class="mk-btn mk-btn--primary mk-btn--lg">
                Compare plans
                <x-ui.icon name="arrow-right" class="h-4 w-4" />
            </a>
            <a href="{{ route('contact') }}" class="mk-btn mk-btn--secondary mk-btn--lg">Talk to us</a>
        </x-slot:actions>
    </x-marketing.page-header>

    {{-- Plan cards --}}
    <section class="mk-section" id="plans">
        <div class="mk-shell">
            <div class="mk-pricing__grid mk-pricing__grid--page">
                @foreach ($plans as $plan)
                    <article class="mk-card mk-card--pad mk-plan {{ $plan['popular'] ? 'mk-plan--featured' : '' }}"
                        data-reveal style="--reveal-delay: {{ $loop->index * 70 }}ms">
                        @if ($plan['popular'])
                            <span class="mk-plan__flag">Most popular</span>
                        @endif

                        <h2 class="mk-plan__name">{{ $plan['name'] }}</h2>
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

                        <a href="{{ $plan['key'] === 'enterprise' ? route('contact') : route('get-started', ['plan' => $plan['key']]) }}"
                            class="mk-btn {{ $plan['popular'] ? 'mk-btn--primary' : 'mk-btn--secondary' }} mk-btn--block mk-plan__cta">
                            {{ $plan['cta'] }}
                        </a>
                    </article>
                @endforeach
            </div>

            @include('pages.marketing.partials.billing-note')
        </div>
    </section>

    {{-- Comparison matrix --}}
    <section class="mk-section mk-section--tint" id="compare">
        <div class="mk-shell">
            <div class="mk-head" data-reveal>
                <p class="mk-eyebrow">Compare</p>
                <h2 class="mk-h2" style="margin-top: 0.85rem">What is in each plan</h2>
                <p class="mk-lede" style="margin-top: 1rem">
                    Every plan covers the day-to-day running of a church. The higher plans add
                    finance depth, group structure and governance.
                </p>
            </div>

            <div class="mk-matrix__wrap" data-reveal>
                <table class="mk-matrix">
                    <caption class="cf-visually-hidden">
                        ChurchFlow feature availability by plan
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Feature</th>
                            @foreach ($plans as $plan)
                                <th scope="col" class="{{ $plan['popular'] ? 'is-featured' : '' }}">
                                    {{ $plan['name'] }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($matrix as $row)
                            <tr>
                                <th scope="row">{{ $row['feature'] }}</th>
                                @foreach ($plans as $plan)
                                    <td class="{{ $plan['popular'] ? 'is-featured' : '' }}">
                                        @if ($row[$plan['key']])
                                            <span class="mk-matrix__yes">
                                                <x-ui.icon name="check" class="h-3.5 w-3.5" />
                                                <span class="cf-visually-hidden">Included</span>
                                            </span>
                                        @else
                                            <span class="mk-matrix__no" aria-hidden="true">&mdash;</span>
                                            <span class="cf-visually-hidden">Not included</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <x-marketing.faq />

    <x-marketing.final-cta />
</x-marketing-layout>

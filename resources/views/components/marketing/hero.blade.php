{{--
 | Hero.
 |
 | Layout follows the reference: copy on the left, product preview on the right,
 | benefits strip beneath the CTAs. The product preview is real markup rather
 | than an image, so it reflows instead of shrinking, and so the numbers can be
 | swapped for live tenant data later without re-exporting anything.
 |
 | The dashboard mockup is the single most important element on this page: it is
 | the only proof a visitor gets that the product exists. The previous version
 | showed four stat tiles and a bar chart, which reads as "wireframe". This one
 | adds the parts that make it read as a real application — a chrome bar, a
 | sidebar with the actual module set, a greeting, an activity feed and an
 | upcoming-events list — because a SaaS hero that shows a generic admin panel
 | undersells the product it is selling.
 |
 | Accessibility: the mockup is aria-hidden, and the same information is exposed
 | as real text in the feature explorer further down the page. Hiding it here
 | without that counterpart would remove content, not just decoration.
--}}

@php
    $demo = config('marketing.demo');
    $benefits = config('marketing.benefits');

    // Sidebar entries mirror the real application's module set, so the mockup is
    // honest about what the product contains rather than decorative.
    $sidebar = [
        ['icon' => 'pie', 'label' => 'Dashboard', 'active' => true],
        ['icon' => 'users', 'label' => 'Members'],
        ['icon' => 'naira', 'label' => 'Finance'],
        ['icon' => 'receipt', 'label' => 'Subvention'],
        ['icon' => 'calendar', 'label' => 'Events'],
        ['icon' => 'check-in', 'label' => 'Attendance'],
        ['icon' => 'branches', 'label' => 'Departments'],
        ['icon' => 'mail', 'label' => 'Communication'],
        ['icon' => 'heart', 'label' => 'Pastoral Care'],
        ['icon' => 'doc', 'label' => 'Reports'],
    ];
@endphp

<section class="mk-hero">
    {{-- Soft background: two tinted blobs and a faint grid. No imagery, so there
         is nothing to download and nothing to look blurry on a 3x display. --}}
    <div class="mk-hero__bg" aria-hidden="true">
        <span class="mk-hero__blob mk-hero__blob--blue"></span>
        <span class="mk-hero__blob mk-hero__blob--green"></span>
        <span class="mk-hero__grid"></span>
    </div>

    <div class="mk-shell mk-hero__inner">
        {{-- ------------------------------------------------------------ copy --}}
        <div class="mk-hero__copy" data-reveal="scale">
            <span class="mk-hero__badge">
                <span class="mk-hero__badgedot" aria-hidden="true"></span>
                All-in-One Church Management Platform
            </span>

            <h1 class="mk-h1 mk-hero__title">
                Everything your church needs.
                <span class="mk-hero__accent">One intelligent platform.</span>
            </h1>

            <p class="mk-lede mk-hero__lede">
                ChurchFlow helps churches, ministries and denominations manage their people,
                finances, activities, communication and more — all in one place.
            </p>

            <div class="mk-hero__ctas">
                <a href="{{ route('register') }}" class="mk-btn mk-btn--primary mk-btn--lg">
                    Get Started
                    <x-ui.icon name="arrow-right" class="h-4 w-4" />
                </a>
                <a href="{{ route('demo') }}" class="mk-btn mk-btn--secondary mk-btn--lg">
                    <x-ui.icon name="play" class="h-4 w-4" />
                    Watch Demo
                </a>
            </div>

            <p class="mk-hero__micro">
                Paid plans only — your church is created once payment is verified.
                <a href="{{ route('home') }}#how-it-works" class="mk-hero__microlink">See how it works</a>
            </p>

            {{-- Benefit indicators --}}
            <dl class="mk-hero__benefits">
                @foreach ($benefits as $benefit)
                    <div class="mk-hero__benefit">
                        <span class="mk-hero__benefiticon">
                            <x-ui.icon :name="$benefit['icon']" class="h-[1.05rem] w-[1.05rem]" />
                        </span>
                        <div>
                            <dt class="mk-hero__benefittitle">{{ $benefit['title'] }}</dt>
                            <dd class="mk-hero__benefittext">{{ $benefit['text'] }}</dd>
                        </div>
                    </div>
                @endforeach
            </dl>
        </div>

        {{-- ------------------------------------------------- product preview --}}
        <div class="mk-hero__visual" data-reveal="scale" style="--reveal-delay: 120ms">
            <div class="mk-app" aria-hidden="true">
                {{-- Chrome --}}
                <div class="mk-app__bar">
                    <span class="mk-app__dot"></span>
                    <span class="mk-app__dot"></span>
                    <span class="mk-app__dot"></span>
                    <span class="mk-app__url">app.churchflow.com/dashboard</span>
                    <span class="mk-app__live">
                        <span class="mk-app__livedot mk-anim-pulse"></span> Live
                    </span>
                </div>

                <div class="mk-app__body">
                    {{-- Sidebar --}}
                    <div class="mk-app__side">
                        <div class="mk-app__sidelogo">
                            <x-ui.brand-mark :size="18" id="hero" />
                            <span>ChurchFlow</span>
                        </div>
                        @foreach ($sidebar as $item)
                            <span class="mk-app__nav {{ ! empty($item['active']) ? 'mk-app__nav--active' : '' }}">
                                <x-ui.icon :name="$item['icon']" class="h-3.5 w-3.5" />
                                {{ $item['label'] }}
                            </span>
                        @endforeach
                    </div>

                    {{-- Main --}}
                    <div class="mk-app__main">
                        <div class="mk-app__head">
                            <div>
                                <p class="mk-app__greeting">{{ $demo['greeting'] }}</p>
                                <p class="mk-app__sub">{{ $demo['subtitle'] }}</p>
                            </div>
                            <span class="mk-app__avatar">PJ</span>
                        </div>

                        {{-- Stat tiles --}}
                        <div class="mk-app__tiles">
                            @foreach ($demo['stats'] as $stat)
                                <div class="mk-tile mk-tile--{{ $stat['tone'] }}">
                                    <div class="mk-tile__top">
                                        <span class="mk-tile__label">{{ $stat['label'] }}</span>
                                        <x-ui.icon :name="$stat['icon']" class="mk-tile__icon" />
                                    </div>
                                    <p class="mk-tile__value mk-num">{{ $stat['value'] }}</p>
                                    <p class="mk-tile__delta">{{ $stat['delta'] }}</p>
                                </div>
                            @endforeach
                        </div>

                        {{-- Chart + side list --}}
                        <div class="mk-app__lower">
                            <div class="mk-app__chart">
                                <div class="mk-app__charthead">
                                    <span>{{ $demo['chart']['title'] }}</span>
                                    <span class="mk-app__chartcap">{{ $demo['chart']['caption'] }}</span>
                                </div>
                                <div class="mk-bars">
                                    @foreach ($demo['chart']['bars'] as $i => $bar)
                                        <div class="mk-bars__pair">
                                            <span
                                                class="mk-bars__bar mk-bars__bar--income"
                                                style="height: {{ $bar['income'] }}%; --bar-delay: {{ $i * 90 }}ms"
                                            ></span>
                                            <span
                                                class="mk-bars__bar mk-bars__bar--expense"
                                                style="height: {{ $bar['expense'] }}%; --bar-delay: {{ $i * 90 + 45 }}ms"
                                            ></span>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mk-bars__axis">
                                    @foreach ($demo['chart']['bars'] as $bar)
                                        <span>{{ $bar['month'] }}</span>
                                    @endforeach
                                </div>
                                <div class="mk-app__legend">
                                    <span><i class="mk-app__swatch mk-app__swatch--income"></i> Income</span>
                                    <span><i class="mk-app__swatch mk-app__swatch--expense"></i> Expenses</span>
                                </div>
                            </div>

                            <div class="mk-app__panel">
                                <p class="mk-app__panelhead">{{ $demo['events']['title'] }}</p>
                                @foreach ($demo['events']['items'] as $event)
                                    <div class="mk-app__event">
                                        <span class="mk-app__eventdate mk-num">{{ $event['date'] }}</span>
                                        <div>
                                            <p class="mk-app__eventname">{{ $event['name'] }}</p>
                                            <p class="mk-app__eventmeta">{{ $event['meta'] }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Activity feed --}}
                        <div class="mk-app__activity">
                            <p class="mk-app__panelhead">{{ $demo['activity']['title'] }}</p>
                            @foreach ($demo['activity']['items'] as $item)
                                <div class="mk-app__actrow">
                                    <span class="mk-icon mk-icon--sm mk-tone--{{ $item['tone'] }}">
                                        <x-ui.icon :name="$item['icon']" class="h-3 w-3" />
                                    </span>
                                    <p class="mk-app__acttext">{{ $item['text'] }}</p>
                                    <span class="mk-app__acttime mk-num">{{ $item['time'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Phone preview. Sits beside the dashboard from lg up; below that the
                 dashboard is full width and there is no room for it. --}}
            <div class="mk-phone mk-anim-float" aria-hidden="true">
                <span class="mk-phone__notch"></span>
                <div class="mk-phone__screen">
                    <div class="mk-phone__head">
                        <x-ui.brand-mark :size="13" id="phone" />
                        <span>ChurchFlow</span>
                    </div>
                    <p class="mk-phone__greet">Good morning, Pastor John</p>
                    <div class="mk-phone__tiles">
                        <span class="mk-phone__tile mk-phone__tile--blue">
                            <b class="mk-num">1,284</b><i>Members</i>
                        </span>
                        <span class="mk-phone__tile mk-phone__tile--green">
                            <b class="mk-num">842</b><i>Attended</i>
                        </span>
                    </div>
                    <p class="mk-phone__label">This week</p>
                    <div class="mk-phone__rows">
                        <span class="mk-phone__row"><em>Sunday Service</em><b class="mk-num">842</b></span>
                        <span class="mk-phone__row"><em>Midweek Study</em><b class="mk-num">410</b></span>
                        <span class="mk-phone__row"><em>Youth Service</em><b class="mk-num">286</b></span>
                    </div>
                    <span class="mk-phone__cta">Open dashboard</span>
                </div>
            </div>
        </div>
    </div>
</section>

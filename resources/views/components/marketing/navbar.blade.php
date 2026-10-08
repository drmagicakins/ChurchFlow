{{--
 | Marketing navigation bar.
 |
 | Changes from the ported version, each fixing a concrete defect:
 |
 |  1. The logo was `<img h-9 w-auto>` on a full-lockup PNG with a black
 |     background and a baked-in tagline. At that size the wordmark rendered at
 |     roughly 4px, and the black plate showed as a dark rectangle on the white
 |     bar. Replaced with <x-ui.brand-logo>, which draws the mark as SVG and sets
 |     the wordmark as live text.
 |  2. The bar had no height contract, so it grew and shrank with its content.
 |     Fixed at 4.5rem, which matches html's scroll-padding-top, so an in-page
 |     #anchor does not land underneath the sticky bar.
 |  3. The Resources dropdown opened on :mouseenter but its container only closed
 |     on @mouseleave — a keyboard user who tabbed in could open it and never
 |     reach or dismiss it. Now: opens on click/Enter, closes on Escape, closes on
 |     outside click, and the chevron reflects state.
 |  4. Mobile menu links navigated but never closed the drawer, because nothing
 |     reset `mobileOpen` on click.
 |  5. Added the active-page state, so "Pricing" is visibly the current page when
 |     you are on it. Previously every link looked identical everywhere.
 |
 | The "Get Started" CTA points at route('register') for guests. For an
 | authenticated visitor it points at the dashboard instead — a signed-in user
 | sent back to a sign-up form is the single most common nav defect on a SaaS site.
--}}

@php
    $homeUrl = route('home');

    $links = [
        ['label' => 'Features', 'route' => 'features'],
        ['label' => 'Pricing', 'route' => 'pricing'],
        ['label' => 'About', 'route' => 'about'],
        ['label' => 'Demo', 'route' => 'demo'],
    ];

    $resourceLinks = [
        ['label' => 'Blog', 'route' => 'resources.blog', 'desc' => 'Product news and church administration ideas'],
        ['label' => 'Help Center', 'route' => 'resources.help-center', 'desc' => 'Answers to common questions'],
        ['label' => 'Guides', 'route' => 'resources.guides', 'desc' => 'Step-by-step setup walkthroughs'],
    ];

    // Highlight the current section. routeIs() handles the exact route name, so
    // each link maps to one section and nothing lights up by accident.
    $isCurrent = fn (string $name) => request()->routeIs($name);
@endphp

<header
    x-data="{
        mobileOpen: false,
        resourcesOpen: false,
        stuck: false,
        close() { this.mobileOpen = false; this.resourcesOpen = false; }
    }"
    @keydown.escape.window="close()"
    @click.outside="resourcesOpen = false"
    @scroll.window="stuck = window.scrollY > 12"
    class="mk-nav"
    :class="stuck ? 'is-stuck' : ''"
>
    <nav class="mk-shell mk-nav__inner" aria-label="Main">
        <x-ui.brand-logo :href="$homeUrl" :size="34" />

        {{-- Desktop navigation --}}
        <div class="mk-nav__links">
            @foreach ($links as $link)
                <a
                    href="{{ route($link['route']) }}"
                    class="mk-nav__link {{ $isCurrent($link['route']) ? 'is-current' : '' }}"
                    @if ($isCurrent($link['route'])) aria-current="page" @endif
                >
                    {{ $link['label'] }}
                </a>
            @endforeach

            <div class="mk-nav__menu" @mouseleave="resourcesOpen = false">
                <button
                    type="button"
                    class="mk-nav__link mk-nav__link--btn {{ request()->routeIs('resources.*') ? 'is-current' : '' }}"
                    @click="resourcesOpen = !resourcesOpen"
                    @mouseenter="resourcesOpen = true"
                    :aria-expanded="resourcesOpen ? 'true' : 'false'"
                    aria-haspopup="true"
                    aria-controls="resources-menu"
                >
                    Resources
                    <svg
                        class="mk-nav__chev"
                        :class="resourcesOpen ? 'is-open' : ''"
                        viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                    >
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.29l3.71-4.06a.75.75 0 1 1 1.08 1.04l-4.25 4.65a.75.75 0 0 1-1.08 0L5.21 8.27a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div id="resources-menu" x-show="resourcesOpen" x-cloak class="mk-nav__dropdown">
                    @foreach ($resourceLinks as $resource)
                        <a href="{{ route($resource['route']) }}" class="mk-nav__dropitem">
                            <span class="mk-nav__droptitle">{{ $resource['label'] }}</span>
                            <span class="mk-nav__dropdesc">{{ $resource['desc'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Desktop actions --}}
        <div class="mk-nav__actions">
            @auth
                <a href="{{ route('dashboard') }}" class="mk-nav__link">Dashboard</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="mk-btn mk-btn--secondary">Sign out</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="mk-nav__link">Login</a>
                <a href="{{ route('register') }}" class="mk-btn mk-btn--primary">Get Started</a>
            @endauth
        </div>

        {{-- Mobile toggle --}}
        <button
            type="button"
            class="mk-nav__burger"
            @click="mobileOpen = !mobileOpen"
            :aria-expanded="mobileOpen ? 'true' : 'false'"
            aria-controls="mobile-menu"
        >
            <span class="cf-visually-hidden">Toggle navigation menu</span>
            <svg x-show="!mobileOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M4 12h16M4 17h16" />
            </svg>
            <svg x-show="mobileOpen" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </nav>

    {{-- Mobile drawer. @click="close()" on every link is what actually dismisses it. --}}
    <div id="mobile-menu" x-show="mobileOpen" x-cloak class="mk-nav__drawer">
        <div class="mk-shell mk-nav__drawerinner">
            @foreach ($links as $link)
                <a href="{{ route($link['route']) }}" class="mk-nav__drawerlink" @click="close()">
                    {{ $link['label'] }}
                </a>
            @endforeach

            <p class="mk-nav__drawerhead">Resources</p>
            @foreach ($resourceLinks as $resource)
                <a href="{{ route($resource['route']) }}" class="mk-nav__drawerlink mk-nav__drawerlink--sub" @click="close()">
                    {{ $resource['label'] }}
                </a>
            @endforeach

            <div class="mk-nav__draweractions">
                @auth
                    <a href="{{ route('dashboard') }}" class="mk-btn mk-btn--secondary mk-btn--block" @click="close()">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="mk-btn mk-btn--secondary mk-btn--block" @click="close()">Login</a>
                    <a href="{{ route('register') }}" class="mk-btn mk-btn--primary mk-btn--block" @click="close()">Get Started</a>
                @endauth
            </div>
        </div>
    </div>
</header>

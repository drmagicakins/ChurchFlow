{{--
 | The authenticated application shell: sidebar + topbar + content.
 |
 | This file had been reduced to a bare `<html><body>{{ $slot }}` wrapper, while
 | every module view (41 of them) is written against the real shell's class names
 | — cf-shell / cf-sidebar / cf-navlink / cf-topbar / cf-content. The styles for
 | those classes exist in app.css and partials/theme.blade.php, so the markup, not
 | the CSS, was the thing that went missing.
 |
 | A `title` prop is declared so `<x-layout title="...">` (used by the SMS, wallet
 | and notification screens) reads it from the attribute bag. Any title passed
 | from the page would otherwise be silently discarded, because the generated
 | `startComponent($component->resolveView(), $component->data())` for a
 | class-less component hands the component only its ATTRIBUTES — a variable
 | assigned in the calling template is never forwarded into the component view.
--}}
@props(['title' => 'Dashboard · ChurchFlow'])

@php
    $title = $attributes->get('title') ?? $title;
    $user = auth()->user();
    $church = $user?->church;

    // Menu entries are (route name, permission, label, icon). A '__group' key
    // renders a section heading; the permission is checked against the same
    // User::hasPermission() the controllers use, so the sidebar can never offer
    // a link the user would then be 403'd on. A null permission means "everyone".
    $nav = [
        ['__group' => 'Overview'],
        ['dashboard', null, 'Dashboard', 'grid'],
        ['calendar.index', null, 'Calendar', 'calendar'],
        ['reports.index', null, 'Reports', 'chart-up'],

        ['__group' => 'People'],
        ['members.index', 'members.view', 'Members', 'users'],
        ['families.index', 'members.view', 'Families', 'family'],
        ['branches.index', 'members.view', 'Branches', 'branches'],
        ['departments.index', 'members.view', 'Departments', 'building'],
        ['groups.index', 'members.view', 'Groups', 'branches'],

        ['__group' => 'Activities'],
        ['events.index', null, 'Events', 'calendar'],
        ['attendance.index', null, 'Attendance', 'check-in'],
        ['announcements.index', 'announcements.manage', 'Announcements', 'megaphone'],
        ['tasks.index', null, 'Tasks', 'task'],

        ['__group' => 'Finance'],
        ['finance.accounts.index', 'finance.view', 'Accounts', 'wallet'],
        ['finance.bank-accounts.index', 'finance.view', 'Bank accounts', 'wallet'],
        ['finance.budgets.index', 'finance.view', 'Budgets', 'pie'],
        ['finance.loans.index', null, 'Loans', 'naira'],
        ['approvals.index', 'finance.approve', 'Approvals', 'shield'],

        ['__group' => 'Subvention'],
        ['subvention.submissions.index', 'subvention.submit', 'Submissions', 'doc'],
        ['subvention.periods.index', 'subvention.manage', 'Periods', 'calendar'],
        ['subvention.rule-sets.index', 'subvention.manage', 'Rule sets', 'sliders'],

        ['__group' => 'Pastoral'],
        ['pastoral.cases.index', 'pastoral.manage', 'Cases', 'heart'],
        ['pastoral.prayer-requests.index', 'pastoral.manage', 'Prayer requests', 'heart'],
        ['pastoral.appointments.index', 'pastoral.manage', 'Appointments', 'calendar'],

        ['__group' => 'Communication'],
        ['sms.campaigns.index', 'sms.manage', 'SMS campaigns', 'chat'],
        ['sms.wallet.show', 'sms.manage', 'SMS wallet', 'wallet'],
        ['notifications.index', null, 'Notifications', 'bell'],

        ['__group' => 'Account'],
        ['billing.show', 'billing.manage', 'Billing & plan', 'wallet'],
        ['settings.index', 'settings.manage', 'Settings', 'sliders'],
        ['support.index', null, 'Support', 'chat'],
    ];

    // A menu row is kept only when its route actually exists in this build and the
    // signed-in user holds the permission it names. Route::has() means a phase that
    // has not shipped yet simply omits its link instead of throwing.
    $canSee = function (array $item) use ($user): bool {
        [$route, $permission] = [$item[0], $item[1]];

        if ($route !== null && ! Route::has($route)) {
            return false;
        }

        return $permission === null || (bool) $user?->hasPermission($permission);
    };

    $groups = [];
    foreach ($nav as $item) {
        if (isset($item['__group'])) {
            $groups[] = ['label' => $item['__group'], 'items' => []];
            continue;
        }

        if ($groups === [] || ! $canSee($item)) {
            continue;
        }

        $groups[array_key_last($groups)]['items'][] = $item;
    }

    // Drop headings whose every entry was filtered out.
    $groups = array_values(array_filter($groups, fn ($group) => $group['items'] !== []));

    $unread = $user ? \App\Models\AppNotification::query()
        ->where('user_id', $user->id)
        ->whereNull('read_at')
        ->count() : 0;

    // Phase 13: the mobile drawer needs a short, flat list of the same links
    // for quick-jump, and the count of visible items tells the hamburger
    // whether a badge is worth showing.
    $navItemCount = collect($groups)->sum(fn ($g) => count($g['items']));
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    {{-- The brand favicon, shared with the marketing pages. A real PNG link is
         used rather than relying on /favicon.ico, which is an empty file. --}}
    <link rel="icon" type="image/png" href="{{ asset('images/churchflow-favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/churchflow-favicon.png') }}">
    @include('partials.theme')
    @stack('head')
</head>
<body>
    <div class="cf-shell" x-data="cfNav()" @keydown.escape.window="close()">
        {{-- Mobile drawer scrim. Clicking outside the nav closes it, which is
             what everyone tries first. --}}
        <div class="cf-navscrim" x-show="open" x-cloak @click="close()" aria-hidden="true"></div>

        <aside class="cf-sidebar" id="cf-sidebar"
               :class="{ 'is-open': open }"
               role="navigation"
               aria-label="Main">
            <div class="cf-sidebar__top">
                <a href="{{ route('dashboard') }}" class="cf-sidebar__brand">
                    <span class="cf-sidebar__mark" aria-hidden="true">CF</span>
                    <span class="cf-sidebar__wordmark">
                        <span>Church<em>Flow</em></span>
                        <small>The Operating Platform for Modern Churches</small>
                    </span>
                </a>

                {{-- Closes the drawer. Only visible on mobile. Uses a CSS ×
                     rather than an icon component because the icon set has no
                     cross glyph, and inventing one for a single use adds a
                     sprite entry nothing else will ever reference. --}}
                <button type="button" class="cf-sidebar__close" @click="close()" aria-label="Close navigation menu">
                    <span aria-hidden="true">×</span>
                </button>
            </div>

            @foreach($groups as $group)
                <div class="cf-sidebar__group">
                    <p class="cf-sidebar__label">{{ $group['label'] }}</p>

                    @foreach($group['items'] as $item)
                        @php
                            [$route, , $label, $icon] = $item;
                            $active = request()->routeIs($route);
                        @endphp
                        <a href="{{ route($route) }}" @class(['cf-navlink', 'is-active' => $active])
                           @if($active) aria-current="page" @endif>
                            <x-ui.icon :name="$icon" />
                            <span>{{ $label }}</span>
                            @if($route === 'notifications.index' && $unread > 0)
                                <span class="cf-badge cf-badge--brand">{{ $unread }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </aside>

        <div class="cf-main">
            <header class="cf-topbar">
                {{-- The hamburger. Only rendered below the shell breakpoint (the
                     desktop sidebar is always present), and it carries the
                     unread count so a phone user does not have to open the
                     drawer to learn they have notifications. --}}
                <button type="button" class="cf-navtoggle"
                        @click="toggle()"
                        :aria-expanded="open ? 'true' : 'false'"
                        aria-controls="cf-sidebar"
                        aria-label="Open navigation menu">
                    <span class="cf-navtoggle__bars" aria-hidden="true">
                        <span :class="{ 'is-x': open }"></span>
                        <span :class="{ 'is-x': open }"></span>
                        <span :class="{ 'is-x': open }"></span>
                    </span>
                    @if($unread > 0)
                        <span class="cf-navtoggle__dot" aria-hidden="true"></span>
                    @endif
                </button>

                @if($user && Route::has('search.index'))
                    {{-- Live search over every module this user may see (GlobalSearchService).
                         Recent searches live in this browser's localStorage only — a per-viewer
                         convenience, wrapped in try/catch so it degrades to "no history". --}}
                    <div class="cfd-gsearch" x-data="cfSearch('{{ route('search.suggest') }}', '{{ route('search.index') }}')" @keydown.escape.window="close()" @click.outside="close()">
                        <form method="GET" action="{{ route('search.index') }}" class="cfd-search" role="search" @submit="remember()">
                            <x-ui.icon name="search" class="h-4 w-4" />
                            <input type="search" name="q" x-model="q" @input.debounce.250ms="fetchResults()" @focus="open = true"
                                @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter="go($event)"
                                placeholder="Search members, events, finance..." aria-label="Search ChurchFlow" autocomplete="off"
                                role="combobox" :aria-expanded="open" aria-controls="cf-search-panel">
                            <span x-show="loading" x-cloak class="cf-tiny cf-muted">…</span>
                            <button type="button" x-show="q" x-cloak @click="q=''; groups=[]; searched=false" class="cfd-search__clear" aria-label="Clear search">×</button>
                        </form>

                        <div id="cf-search-panel" class="cfd-gsearch__panel" x-show="open" x-cloak x-transition.opacity.duration.120ms>
                            <template x-if="error"><p class="cfd-empty">Search is unavailable right now. Please try again.</p></template>

                            <template x-if="!error && q.trim().length < 2">
                                <div>
                                    <p class="cfd-gsearch__heading" x-show="recent.length">Recent searches</p>
                                    <template x-for="r in recent" :key="r">
                                        <button type="button" class="cfd-gsearch__item" @click="q=r; fetchResults()"><span x-text="r"></span></button>
                                    </template>
                                    <p class="cfd-empty" x-show="!recent.length">Type at least 2 characters to search.</p>
                                </div>
                            </template>

                            <template x-if="!error && q.trim().length >= 2">
                                <div>
                                    <p class="cfd-empty" x-show="searched && !groups.length && !loading">No results for “<span x-text="q"></span>”.</p>
                                    <template x-for="g in groups" :key="g.key">
                                        <div>
                                            <p class="cfd-gsearch__heading" x-text="g.label"></p>
                                            <template x-for="it in g.items" :key="it.url">
                                                <a :href="it.url" class="cfd-gsearch__item" :class="{'is-active': flat.indexOf(it) === active}" @mouseenter="active = flat.indexOf(it)">
                                                    <span class="cfd-row__title" x-html="mark(it.title)"></span>
                                                    <span class="cfd-row__sub" x-text="it.subtitle" x-show="it.subtitle"></span>
                                                </a>
                                            </template>
                                        </div>
                                    </template>
                                    <a :href="allUrl()" class="cfd-gsearch__all" x-show="groups.length">View all results →</a>
                                </div>
                            </template>
                        </div>
                    </div>
                @else
                    <p class="cf-small cf-muted">{{ $church?->name ?? config('marketing.product.name', 'ChurchFlow') }}</p>
                @endif

                <div class="cfd-topbar__right">
                    @if($user)
                        {{-- ============ NOTIFICATIONS ============ --}}
                        {{-- The bell is a BUTTON now, not a link: it opens a panel.
                             "View all" inside the panel goes to the built page. The
                             count is still rendered server-side so it is correct on
                             first paint and with JavaScript still loading. --}}
                        <div class="cfd-menu" x-data="cfNotifications('{{ route('notifications.feed') }}', {{ $unread }})" @click.outside="close()" @keydown.escape.window="close()" x-init="load()">
                            <button type="button" class="cfd-iconbtn" @click="toggle()"
                                    :aria-expanded="open ? 'true' : 'false'" aria-controls="cf-notif-panel"
                                    aria-label="Notifications{{ $unread > 0 ? ' ('.$unread.' unread)' : '' }}">
                                <x-ui.icon name="bell" />
                                <span class="cfd-iconbtn__count" x-show="count > 0" x-cloak x-text="count > 99 ? '99+' : count">{{ $unread > 99 ? '99+' : $unread }}</span>
                                @if($unread > 0)<span class="cf-badge cf-badge--dot" aria-hidden="true"></span>@endif
                            </button>

                            <div class="cfd-menu__panel cfd-menu__panel--wide" id="cf-notif-panel" x-show="open" x-cloak
                                 x-transition.opacity.duration.120ms role="menu">
                                <div class="cfd-menu__head">
                                    <strong>Notifications</strong>
                                    <span class="cf-badge cf-badge--brand" x-show="count > 0" x-cloak x-text="count + ' unread'"></span>
                                </div>

                                <div class="cfd-menu__list">
                                    {{-- Server-rendered first paint; replaced by the
                                         feed below once it loads. --}}
                                    <template x-if="items.length === 0">
                                        <div class="cfd-menu__empty">
                                            @if($recentNotifications->isEmpty())
                                                Nothing yet. Decisions and updates appear here.
                                            @endif
                                        </div>
                                    </template>

                                    @if($recentNotifications->isNotEmpty())
                                        <div x-show="items.length === 0">
                                            @foreach($recentNotifications as $n)
                                                <a href="{{ $n->action_url ?: route('notifications.index') }}" class="cfd-menu__item {{ $n->read_at ? '' : 'is-unread' }}">
                                                    <span class="cfd-menu__dot"></span>
                                                    <span class="cfd-menu__body">
                                                        <span class="cfd-menu__title">{{ $n->title }}</span>
                                                        @if($n->body)<span class="cfd-menu__sub">{{ \Illuminate\Support\Str::limit($n->body, 70) }}</span>@endif
                                                        <span class="cfd-menu__when">{{ $n->created_at?->diffForHumans() }}</span>
                                                    </span>
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif

                                    <template x-for="n in items" :key="n.id">
                                        <a :href="n.url || '{{ route('notifications.index') }}'" class="cfd-menu__item" :class="n.unread ? 'is-unread' : ''">
                                            <span class="cfd-menu__dot"></span>
                                            <span class="cfd-menu__body">
                                                <span class="cfd-menu__title" x-text="n.title"></span>
                                                <span class="cfd-menu__sub" x-text="n.body" x-show="n.body"></span>
                                                <span class="cfd-menu__when" x-text="n.when"></span>
                                            </span>
                                        </a>
                                    </template>
                                </div>

                                <a href="{{ route('notifications.index') }}" class="cfd-menu__foot">View all notifications</a>
                            </div>
                        </div>

                        {{-- ============ PROFILE ============ --}}
                        <div class="cfd-menu" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                            <button type="button" class="cfd-user cfd-user--btn" @click="open = !open"
                                    :aria-expanded="open ? 'true' : 'false'" aria-controls="cf-profile-panel" aria-haspopup="menu">
                                <span class="cfd-user__avatar" aria-hidden="true">
                                    @if($avatarUrl)
                                        <img src="{{ $avatarUrl }}" alt="" class="cfd-user__img">
                                    @else
                                        {{ $user->initials() }}
                                    @endif
                                </span>
                                <span class="cfd-user__meta">
                                    <strong>{{ $user->name }}</strong>
                                    <small>{{ $user->roles->first()?->name ?? 'Member' }}</small>
                                </span>
                                <x-ui.icon name="chevron" class="h-4 w-4 cfd-user__caret" />
                            </button>

                            <div class="cfd-menu__panel" id="cf-profile-panel" x-show="open" x-cloak
                                 x-transition.opacity.duration.120ms role="menu" aria-label="Account">
                                <div class="cfd-menu__me">
                                    <span class="cfd-user__avatar cfd-user__avatar--lg" aria-hidden="true">
                                        @if($avatarUrl)
                                            <img src="{{ $avatarUrl }}" alt="" class="cfd-user__img">
                                        @else
                                            {{ $user->initials() }}
                                        @endif
                                    </span>
                                    <span class="cfd-menu__me-meta">
                                        <strong>{{ $user->name }}</strong>
                                        <small>{{ $user->email }}</small>
                                    </span>
                                </div>

                                {{-- Profile picture --}}
                                <div class="cfd-menu__section">
                                    <p class="cfd-menu__label">Profile picture</p>
                                    <form method="POST" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data" class="cfd-menu__upload">
                                        @csrf
                                        {{-- No inline onchange: the CSP has a nonce, so
                                             inline handlers are blocked outright. The form
                                             is submitted from the Alpine scope instead. --}}
                                        <input type="file" name="photo" id="cf-avatar-input" accept="image/png,image/jpeg,image/webp,image/gif"
                                               class="cfd-menu__file" required
                                               x-on:change="$el.form.requestSubmit()">
                                        <label for="cf-avatar-input" class="cf-btn cf-btn--secondary cf-btn--sm cf-btn--block" style="cursor:pointer">
                                            {{ $avatarUrl ? 'Change picture' : 'Upload picture' }}
                                        </label>
                                    </form>
                                    <p class="cfd-menu__hint">PNG, JPG, WebP or GIF up to 5MB.</p>
                                    @if($avatarUrl)
                                        <form method="POST" action="{{ route('profile.photo.destroy') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="cfd-menu__link cfd-menu__link--danger">Remove picture</button>
                                        </form>
                                    @endif
                                </div>

                                {{-- Password --}}
                                <div class="cfd-menu__section" x-data="{ open: false }">
                                    <button type="button" class="cfd-menu__link" @click="open = !open" :aria-expanded="open ? 'true' : 'false'" aria-controls="cf-password-form">
                                        <x-ui.icon name="lock" class="h-4 w-4" /> Change password
                                    </button>
                                    <form method="POST" action="{{ route('profile.password.update') }}" id="cf-password-form" x-show="open" x-cloak class="cfd-menu__form">
                                        @csrf
                                        <input type="password" name="current_password" class="cf-input" placeholder="Current password" required autocomplete="current-password">
                                        <input type="password" name="password" class="cf-input" placeholder="New password (min 8)" required minlength="8" autocomplete="new-password">
                                        <input type="password" name="password_confirmation" class="cf-input" placeholder="Confirm new password" required minlength="8" autocomplete="new-password">
                                        <button type="submit" class="cf-btn cf-btn--primary cf-btn--sm cf-btn--block">Save password</button>
                                    </form>
                                </div>

                                {{-- Two-step authentication --}}
                                <div class="cfd-menu__section" x-data="{ open: false }">
                                    <button type="button" class="cfd-menu__link" @click="open = !open" :aria-expanded="open ? 'true' : 'false'" aria-controls="cf-2fa-form">
                                        <x-ui.icon name="shield" class="h-4 w-4" />
                                        <span>Two-step authentication</span>
                                        @if($user->hasTwoFactorEnabled())
                                            <span class="cf-badge cf-badge--ok" style="margin-left:auto">On</span>
                                        @else
                                            <span class="cf-badge" style="margin-left:auto">Off</span>
                                        @endif
                                    </button>

                                    <div id="cf-2fa-form" x-show="open" x-cloak class="cfd-menu__form">
                                        @if($user->hasTwoFactorEnabled())
                                            <p class="cfd-menu__hint" style="margin:0">Your account asks for a code from your authenticator app at each sign-in.</p>
                                            <form method="POST" action="{{ route('profile.two-factor.recovery-codes') }}">
                                                @csrf
                                                <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm cf-btn--block">New recovery codes</button>
                                            </form>
                                            <form method="POST" action="{{ route('profile.two-factor.disable') }}">
                                                @csrf
                                                <input type="password" name="current_password" data-cf="password" class="cf-input" placeholder="Confirm with your password" required autocomplete="current-password">
                                                <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm cf-btn--block">Turn off two-step</button>
                                            </form>
                                        @elseif(filled($user->two_factor_secret))
                                            {{-- Setup started — show the provisioning URI so the
                                                 person can scan or type it, then confirm. --}}
                                            @php $totpUri = app(\App\Domains\Security\Services\TwoFactorService::class)->provisioningUri((string) $user->two_factor_secret, $user->email); @endphp
                                            <p class="cfd-menu__hint" style="margin:0">Add this to your authenticator app, then enter the 6-digit code.</p>
                                            {{-- No third-party QR service: a secret would
                                                 leave the app just to be drawn. The code is
                                                 shown for typing; the URI is what a QR
                                                 encodes, and every authenticator app can
                                                 import it directly. --}}
                                            <code class="cfd-menu__secret">{{ $user->two_factor_secret }}</code>
                                            <a href="{{ $totpUri }}" class="cfd-menu__link" style="font-size:.78rem">Open in authenticator app</a>
                                            <form method="POST" action="{{ route('profile.two-factor.confirm') }}">
                                                @csrf
                                                <input type="text" name="code" class="cf-input" placeholder="6-digit code" required inputmode="numeric" autocomplete="one-time-code" maxlength="7">
                                                <button type="submit" class="cf-btn cf-btn--primary cf-btn--sm cf-btn--block">Turn on two-step</button>
                                            </form>
                                        @else
                                            <p class="cfd-menu__hint" style="margin:0">Add a second check at sign-in using an authenticator app on your phone.</p>
                                            <form method="POST" action="{{ route('profile.two-factor.enable') }}">
                                                @csrf
                                                <button type="submit" class="cf-btn cf-btn--primary cf-btn--sm cf-btn--block">Set up two-step</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>

                                <div class="cfd-menu__section cfd-menu__section--flush">
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="cfd-menu__link cfd-menu__link--danger">
                                            <x-ui.icon name="logout" class="h-4 w-4" /> Sign out
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </header>

            <main class="cf-content">
                @if(session('status'))
                    <div class="cf-alert cf-alert--ok">{{ session('status') }}</div>
                @endif

                {{-- Recovery codes are shown ONCE, immediately after two-step is
                     turned on (or regenerated). They are stored HASHED, so this
                     flash is the only copy the person will ever see. --}}
                @if(session('recovery_codes'))
                    <div class="cf-alert cf-alert--ok" role="status" style="display:block">
                        <p style="margin:0 0 .5rem;font-weight:600">
                            Save these recovery codes somewhere safe. Each works once and they
                            are never shown again.
                        </p>
                        <div class="cfd-recovery">
                            @foreach(session('recovery_codes') as $code)
                                <code>{{ $code }}</code>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($errors->any())
                    <div class="cf-alert cf-alert--error">
                        <div>
                            <strong>Please correct the following:</strong>
                            <ul>
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

    @include('partials.loader')

    {{-- One custom confirmation dialog for every destructive action in the app,
         replacing the browser's native confirm(). See partials/confirm. --}}
    @include('partials.confirm')

    {{-- §24: the task reminder popup + chime. Polls for tasks whose reminder
         moment has passed and pops them up once. See partials/task-reminders. --}}
    @include('partials.task-reminders')

    <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
        document.addEventListener('alpine:init', () => {
            /*
             | The mobile drawer.
             |
             | "Smart" here means four specific behaviours, each of which is a
             | way a naive hamburger fails in real use:
             |
             |  1. It closes itself when the viewport grows past the breakpoint.
             |     Rotating a phone or resizing a window left the drawer open
             |     and the scrim covering a now-desktop layout, with no visible
             |     way to dismiss it.
             |  2. It locks body scroll while open. Without this the page behind
             |     scrolls under the drawer, and on iOS the drawer itself drifts.
             |  3. It traps Tab inside the drawer. A drawer full of links that
             |     keyboard users can tab straight out of (into content they
             |     cannot see) is worse than no drawer.
             |  4. It restores focus to the button that opened it, so closing
             |     does not dump the user back at the top of the document.
             */
            Alpine.data('cfNav', () => ({
                open: false,
                _breaker: null,
                _lastFocus: null,

                init() {
                    this._breaker = window.matchMedia('(min-width: 62rem)');
                    this._breaker.addEventListener('change', (e) => {
                        if (e.matches) this.close({ restoreFocus: false });
                    });

                    this.$watch('open', (isOpen) => {
                        document.documentElement.classList.toggle('cf-navlocked', isOpen);
                        if (isOpen) this._trapFocus();
                    });
                },

                toggle() {
                    if (this.open) { this.close(); return; }
                    this._lastFocus = document.activeElement;
                    this.open = true;
                },

                close({ restoreFocus = true } = {}) {
                    if (!this.open) return;
                    this.open = false;
                    document.documentElement.classList.remove('cf-navlocked');

                    if (restoreFocus && this._lastFocus && this._lastFocus.focus) {
                        this._lastFocus.focus();
                    }
                },

                // Keep Tab inside the open drawer. Re-queried on each keypress
                // rather than cached, because the link list is the same DOM the
                // server rendered per page and can differ between pages.
                _trapFocus() {
                    const panel = document.getElementById('cf-sidebar');
                    if (!panel) return;

                    const focusables = () => Array.from(
                        panel.querySelectorAll('a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])')
                    ).filter((el) => el.offsetParent !== null);

                    const onKeydown = (e) => {
                        if (e.key !== 'Tab') return;
                        const items = focusables();
                        if (!items.length) return;
                        const first = items[0];
                        const last = items[items.length - 1];

                        if (e.shiftKey && document.activeElement === first) {
                            e.preventDefault();
                            last.focus();
                        } else if (!e.shiftKey && document.activeElement === last) {
                            e.preventDefault();
                            first.focus();
                        }
                    };

                    document.addEventListener('keydown', onKeydown);
                    this.$watch('open', (isOpen) => {
                        if (!isOpen) document.removeEventListener('keydown', onKeydown);
                    });

                    // Move focus into the drawer so the next Tab starts inside it.
                    requestAnimationFrame(() => {
                        const items = focusables();
                        if (items.length) items[0].focus();
                    });
                },
            }));

            /*
             | The notification bell's dropdown.
             |
             | `items` starts EMPTY on purpose: the server already rendered the
             | newest few into the panel, and those stay visible until the feed
             | arrives. Starting with a spinner-shaped empty list would make a
             | correct page look broken for the length of one request.
             |
             | The count is decremented locally when an unread item is opened, so
             | the badge does not sit on a number the person has just read. The
             | authoritative count still comes from the server on the next load;
             | nothing is marked read here.
             */
            Alpine.data('cfNotifications', (feedUrl, initialCount) => ({
                open: false,
                count: Number(initialCount) || 0,
                items: [],
                loading: false,
                loaded: false,

                toggle() {
                    this.open = !this.open;
                    if (this.open) this.load();
                },
                close() { this.open = false; },

                load() {
                    if (this.loading) return;
                    this.loading = true;

                    fetch(feedUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                        .then((r) => (r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status))))
                        .then((data) => {
                            this.items = Array.isArray(data.items) ? data.items : [];
                            this.count = Number(data.unread) || 0;
                            this.loaded = true;
                        })
                        // A failed feed leaves the server-rendered items in place.
                        // The bell must not empty itself because one request failed.
                        .catch(() => {})
                        .finally(() => { this.loading = false; });
                },
            }));
        });
    </script>

    <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
        document.addEventListener('alpine:init', () => {
            Alpine.data('cfSearch', (suggestUrl, allUrl) => ({
                q: new URLSearchParams(location.search).get('q') || '', open: false, loading: false, error: false,
                searched: false, groups: [], active: -1, recent: [], ctl: null,
                init() { this.recent = this.loadRecent(); },
                get flat() { return this.groups.flatMap(g => g.items); },
                loadRecent() { try { return JSON.parse(localStorage.getItem('cf.recent') || '[]'); } catch (e) { return []; } },
                remember() {
                    const t = this.q.trim(); if (t.length < 2) return;
                    try { localStorage.setItem('cf.recent', JSON.stringify([t, ...this.loadRecent().filter(x => x !== t)].slice(0, 5))); } catch (e) {}
                },
                allUrl() { return allUrl + '?q=' + encodeURIComponent(this.q.trim()); },
                close() { this.open = false; this.active = -1; },
                move(d) { const n = this.flat.length; if (!n) return; this.active = (this.active + d + n) % n; },
                go(e) { if (this.active >= 0 && this.flat[this.active]) { e.preventDefault(); this.remember(); location.href = this.flat[this.active].url; } },
                // Escape the query for highlighting, then wrap matches; input is HTML-escaped first so x-html is safe.
                mark(text) {
                    const esc = s => s.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
                    const t = this.q.trim(); if (!t) return esc(text);
                    const rx = v => v.replace(/[.*+?^$(){}|[\]\\]/g, '\\$&');
                    return esc(text).replace(new RegExp(rx(esc(t)), 'ig'), m => '<mark>' + m + '</mark>');
                },
                async fetchResults() {
                    const t = this.q.trim(); this.open = true; this.active = -1; this.error = false;
                    if (t.length < 2) { this.groups = []; this.searched = false; return; }
                    this.ctl?.abort(); this.ctl = new AbortController(); this.loading = true;
                    try {
                        const r = await fetch(suggestUrl + '?q=' + encodeURIComponent(t), { headers: { Accept: 'application/json' }, signal: this.ctl.signal });
                        if (!r.ok) throw new Error(r.status);
                        this.groups = (await r.json()).groups; this.searched = true;
                    } catch (e) { if (e.name !== 'AbortError') this.error = true; }
                    finally { this.loading = false; }
                },
            }));
        });
    </script>
    @stack('scripts')
</body>
</html>

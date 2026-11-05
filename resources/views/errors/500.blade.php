{{--
 | 500 — something broke on our side.
 |
 | Until now the only answer to a server fault was Laravel's developer error
 | page: a full stack trace, the request's route parameters panel ("No routing
 | parameters" on requests that carry none), the SQL queries and the .env paths.
 | That is the right page for a developer on a local machine and the wrong page
 | for a church administrator mid-task — it is unreadable, it leaks internals,
 | and it offers nothing to do next.
 |
 | This is the ChurchFlow equivalent: the same guest shell the 419 page uses
 | (brand lockup, no sidebar, no auth() calls — an error page must never itself
 | depend on a signed-in user resolving), a plain explanation, and two real ways
 | out: back to where they were, or the dashboard.
 |
 | It deliberately prints NOTHING from the exception: no message, no class, no
 | trace, no request input. The details still go to the log server-side, which
 | is where an operator can act on them. Anything echoed here would be public.
 |
 | `$status` / `$exception` are supplied by Laravel when it renders this view;
 | neither is read. The page is the same for every server error, so a 503 and a
 | TypeError read identically to the person — which is correct, because the
 | distinction only matters to us.
--}}
<x-layouts.auth :title="'Something went wrong'" heading="Something went wrong"
    subheading="A problem on our side stopped this page from loading. Nothing you entered has been lost from our records.">

    @php
        /*
         | The shell needs the tenant database (it reads the church's branding)
         | and the route table. If the failure WAS the database or the routes,
         | resolving a route here would fail the same way — so the links are
         | resolved defensively, and a broken route() falls back to the root.
         */
        try {
            $backRoute = auth()->check() ? route('dashboard') : route('home');
            $contactRoute = auth()->check() ? route('support.index') : null;
        } catch (\Throwable) {
            $backRoute = '/';
            $contactRoute = null;
        }
    @endphp

    <div class="cf-card" style="display:grid;gap:1rem">
        <div class="cf-alert cf-alert--error" role="alert" style="margin:0">
            <span class="cf-alert__icon"><x-ui.icon name="shield" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">This is not something you did</p>
                <p class="cf-small" style="margin:.25rem 0 0">
                    ChurchFlow hit an unexpected error while preparing this page. Your church's data is
                    safe — nothing was deleted or changed by the failure.
                </p>
            </div>
        </div>

        <div style="display:flex;gap:.6rem;flex-wrap:wrap">
            <a href="{{ url()->previous() }}" class="cf-btn cf-btn--primary cf-btn--sm">
                <x-ui.icon name="arrow-right" class="h-4 w-4" style="transform:rotate(180deg)" /> Try again
            </a>

            @auth
                <a href="{{ $backRoute }}" class="cf-btn cf-btn--secondary cf-btn--sm">
                    <x-ui.icon name="grid" class="h-4 w-4" /> Go to dashboard
                </a>
            @else
                <a href="{{ $backRoute }}" class="cf-btn cf-btn--secondary cf-btn--sm">
                    <x-ui.icon name="grid" class="h-4 w-4" /> Go to the homepage
                </a>
            @endauth
        </div>

        <p class="cf-small cf-muted" style="margin:0">
            If it keeps happening, send us the time it occurred through
            @if ($contactRoute)
                <a href="{{ $contactRoute }}">Support</a>
            @else
                our contact page
            @endif
            and we will look into it straight away.
        </p>
    </div>

</x-layouts.auth>

{{--
 | 419 — the session, not the form, expired.
 |
 | Laravel's default answer to a stale CSRF token is "419 PAGE EXPIRED": a bare
 | white page with no way back. That is what a church administrator sees after
 | leaving a Budget (or any long form) open past SESSION_LIFETIME and pressing
 | submit — the work is still on screen, but the page they land on offers
 | nothing to do about it. It reads as "saving budgets is broken".
 |
 | CSRF verification itself is deliberately NOT relaxed for this. The token
 | expiring is the expected, correct behaviour; the missing piece was telling
 | the person what happened and putting them back where they were.
 |
 | `url.intended` is the safe redirect target: Laravel's guest redirect stores
 | the original GET URL there, and it is always an internal path, never a URL
 | taken from the request being rejected.
--}}
<x-layouts.auth
    :title="'Session ended'"
    heading="Your session ended"
    subheading="That page was open a little too long, so we could not verify it was still you.">

    <div class="cf-card" style="display:grid;gap:1rem">
        <div class="cf-alert cf-alert--error" role="alert" style="margin:0">
            <span class="cf-alert__icon"><x-ui.icon name="lock" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">Nothing was saved</p>
                <p class="cf-small" style="margin:.25rem 0 0">
                    Your sign-in expired before the form was submitted. Sign in again and
                    you will be taken straight back.
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('login') }}" style="display:grid;gap:1rem">
            @csrf

            <x-auth.field
                name="email"
                label="Email address"
                type="email"
                autocomplete="email"
                :autofocus="true" />

            <x-auth.field
                name="password"
                label="Password"
                type="password"
                autocomplete="current-password" />

            <button type="submit" class="cf-btn cf-btn--primary">Sign in and continue</button>
        </form>

        <p class="cf-small cf-muted" style="margin:0">
            Tip: on a form with several fields, the page stays valid for
            {{ (int) config('session.lifetime') }} minutes after it was opened.
        </p>
    </div>

</x-layouts.auth>

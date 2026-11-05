{{--
 | 422 — a request the plan does not allow (the plan limit page).
 |
 | "You've reached the branches limit for your current plan. Current: 1 / 1
 | branches. Upgrade your plan to add more branches."
 |
 | That message is correct and is kept verbatim, but it used to arrive as a bare
 | browser error document ("An Error Occurred: Unprocessable Content") — a dead
 | end with no way to upgrade and no way back. This is its own page: the limit
 | stated in plain terms, what it means, and the one action that fixes it.
 |
 | Nothing about the failure is hidden: $exception->getMessage() is the operator's
 | own sentence, so it is shown as written rather than paraphrased. It is printed
 | with {{ }} (escaped), never raw.
 |
 | The page is reached for a web request only. JSON callers keep the JSON 422
 | contract with the same message, because a fetch()/API client cannot follow a
 | page.
--}}
@php
    try {
        $backRoute = auth()->check() ? route('dashboard') : route('home');
        $upgradeRoute = auth()->check() ? route('billing.show') : null;
    } catch (\Throwable) {
        $backRoute = '/';
        $upgradeRoute = null;
    }

    $detail = isset($exception) && $exception ? trim($exception->getMessage()) : '';
@endphp

<x-layouts.auth :title="'Plan limit reached'" heading="Your plan limit has been reached"
    subheading="This action needs a little more room than your current plan allows.">

    <div class="cf-card" style="display:grid;gap:1rem">
        <div class="cf-alert cf-alert--error" role="alert" style="margin:0">
            <span class="cf-alert__icon"><x-ui.icon name="chart-up" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">Nothing was saved</p>
                <p class="cf-small" style="margin:.25rem 0 0">
                    @if ($detail !== '')
                        {{ $detail }}
                    @else
                        You have reached the limit your current plan allows. Upgrade your plan to add more.
                    @endif
                </p>
            </div>
        </div>

        <p class="cf-small cf-muted" style="margin:0">
            Moving to a larger plan raises this limit straight away, and everything already on
            your account stays exactly as it is.
        </p>

        <div style="display:flex;gap:.6rem;flex-wrap:wrap">
            @if ($upgradeRoute)
                <a href="{{ $upgradeRoute }}" class="cf-btn cf-btn--primary cf-btn--sm">
                    <x-ui.icon name="chart-up" class="h-4 w-4" /> View plans and upgrade
                </a>
            @else
                <a href="{{ route('plans.index') }}" class="cf-btn cf-btn--primary cf-btn--sm">
                    <x-ui.icon name="chart-up" class="h-4 w-4" /> View plans
                </a>
            @endif

            <a href="{{ $backRoute }}" class="cf-btn cf-btn--secondary cf-btn--sm">
                <x-ui.icon name="grid" class="h-4 w-4" /> Back to dashboard
            </a>
        </div>
    </div>

</x-layouts.auth>

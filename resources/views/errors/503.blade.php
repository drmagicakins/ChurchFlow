{{--
 | A server fault while ChurchFlow is loading.
 |
 | Kept SEPARATE from 500.blade.php on purpose: a 503 means "temporarily
 | unavailable" (maintenance, overload, a dependency down), which is a different
 | promise from "something broke". Telling a church their data failed is wrong
 | when the honest answer is "try again in a moment".
 |
 | Like the 500 page it prints nothing from the exception — no message, class,
 | trace or request input. Details belong in the log, which is private.
--}}
@php
    try {
        $backRoute = auth()->check() ? route('dashboard') : route('home');
    } catch (\Throwable) {
        $backRoute = '/';
    }
@endphp

<x-layouts.auth :title="'Temporarily unavailable'" heading="Temporarily unavailable"
    subheading="ChurchFlow is briefly offline for maintenance. This is not something you need to fix.">

    <div class="cf-card" style="display:grid;gap:1rem">
        <div class="cf-alert cf-alert--error" role="alert" style="margin:0">
            <span class="cf-alert__icon"><x-ui.icon name="lock" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">Nothing was saved or lost</p>
                <p class="cf-small" style="margin:.25rem 0 0">
                    Your church's records are untouched. Please try again in a few minutes.
                </p>
            </div>
        </div>

        <div style="display:flex;gap:.6rem;flex-wrap:wrap">
            <a href="{{ url()->previous() }}" class="cf-btn cf-btn--primary cf-btn--sm">
                <x-ui.icon name="arrow-right" class="h-4 w-4" style="transform:rotate(180deg)" /> Try again
            </a>
            <a href="{{ $backRoute }}" class="cf-btn cf-btn--secondary cf-btn--sm">
                <x-ui.icon name="grid" class="h-4 w-4" /> Go back to
                {{ auth()->check() ? 'dashboard' : 'the homepage' }}
            </a>
        </div>
    </div>

</x-layouts.auth>

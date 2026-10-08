{{--
 | Guest authentication shell.
 |
 | Deliberately NOT <x-layout>. The app shell (components/layout.blade.php) is the
 | AUTHENTICATED chrome: it resolves auth()->user(), renders the sidebar, the
 | permission-filtered nav and the notification bell, and calls $user->roles on the
 | topbar avatar. Wrapping /login in it means the sign-in page tries to render a
 | signed-in user who does not exist yet.
 |
 | That is not hypothetical — it is exactly what shipped in the zip integration:
 | login.blade.php was reduced to `<x-layout><h1>Sign In</h1></x-layout>`, which
 | rendered the app sidebar around a login form and left the page unusable.
 |
 | This shell is the guest equivalent: brand lockup, centred panel, no sidebar, no
 | topbar, no auth() calls. It reuses the .cf-auth* classes that already existed in
 | app.css and partials/theme.blade.php but had no view using them — so this is
 | finishing a page that was already scaffolded for, not a new design.
 |
 | Layout lives in components/layouts/ so <x-layouts.auth> resolves by convention
 | (the same rule that made <x-marketing-layout> work after the `layouts.marketing`
 | namespace bug). It is a components/ path, not resources/views/layouts/.
--}}

@props([
    'title' => null,
    'heading' => null,
    'subheading' => null,
])

@php
    $pageTitle =
        $title ??
        ($heading
            ? $heading . ' · ' . config('marketing.product.name', 'ChurchFlow')
            : config('marketing.product.name', 'ChurchFlow'));
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0b2a5b">

    <title>{{ $pageTitle }}</title>

    {{--
     | The theme partial carries the whole .cf-* design system as an inline <style>,
     | and it also emits @vite([...]) for the compiled bundle. Included here so the
     | module views depend on the styles even when the compiled bundle is stale —
     | same contract the app shell relies on.
     |
     | Do NOT add another @vite([...]) below: that is what used to happen here, and
     | it emitted the same <script type="module"> (and stylesheet) TWICE on every
     | guest page. The duplicate stylesheet is merely wasteful; the duplicate
     | module script is not — the second copy re-executes module top-level code and
     | makes the page's script graph ambiguous.
    --}}
    @include('partials.theme')

    @stack('head')
</head>

<body>
    <div class="cf-auth">
        <div class="cf-auth__panel">
            {{--
             | The real brand lockup, not the "CF" initials block. The mark is inline
             | SVG and the wordmark is live text, so it stays legible and needs no
             | raster asset — see components/ui/brand-logo.blade.php.
            --}}
            <div class="cf-auth__brandwrap">
                <x-ui.brand-logo :size="40" :href="route('home')" />
            </div>

            @if ($heading)
                <h1 style="text-align:center;font-size:1.5rem;font-weight:700;letter-spacing:-.02em;color:var(--ink)">
                    {{ $heading }}</h1>
            @endif

            @if ($subheading)
                <p class="cf-small cf-muted" style="text-align:center;margin-top:.4rem">{{ $subheading }}</p>
            @endif

            @if (session('status'))
                <div class="cf-alert cf-alert--ok" style="margin-top:1.2rem">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="cf-alert cf-alert--error" style="margin-top:1.2rem">
                    <div>
                        <strong>Please correct the following:</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div style="margin-top:1.4rem">
                {{ $slot }}
            </div>
        </div>
    </div>

    @stack('scripts')
</body>

</html>

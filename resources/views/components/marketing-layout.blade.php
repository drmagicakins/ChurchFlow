{{--
 | Marketing page layout.
 |
 | Lives in components/ as `marketing-layout.blade.php` so that <x-marketing-layout>
 | resolves as an anonymous Blade component. The previous location
 | (layouts/marketing.blade.php, referenced as <x-layouts.marketing>) did NOT
 | resolve: `layouts` is not a registered component namespace here, and the app's
 | own shell is <x-layout> pointing at components/layout.blade.php. That mismatch
 | was a hard 500 on the landing page — "Unable to locate a class or view for
 | component [layouts.marketing]" — verified in the browser, not assumed.
 |
 | Chosen over registering a `layouts` namespace because one component resolving
 | by convention is simpler than a namespace that exists for a single file.
--}}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0b2a5b">

    {{--
     | SEO.
     |
     | Every value is a prop with a sane default, so a new marketing page gets a
     | correct, unique title and description by passing two arguments rather than
     | by remembering to edit a head partial.
     |
     | The canonical URL is built from the current path WITHOUT the query string,
     | because ?utm_source=... variants must all point at one canonical URL or
     | search engines see the same page several times over.
    --}}
    @php
        $pageTitle = $title ?? config('marketing.product.name').' — '.config('marketing.product.tagline');
        $pageDescription = $description ?? config('marketing.product.description');
        $canonical = url()->current();
        $ogImage = asset('images/churchflow-logo.png');
    @endphp

    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <link rel="canonical" href="{{ $canonical }}">

    @isset($robots)
        <meta name="robots" content="{{ $robots }}">
    @endisset

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('marketing.product.name') }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:locale" content="en_NG">

    {{-- Twitter / X --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    <link rel="icon" href="{{ asset('images/churchflow-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/churchflow-logo.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{--
     | Alpine's pre-boot hide. x-cloak stops a flash of un-toggled content before
     | Alpine initialises (the mobile menu drawer and the Resources dropdown are
     | both in the DOM from the first paint). Declared here rather than in app.css
     | so it is guaranteed to be present even if the compiled bundle is stale.
    --}}
    <style>[x-cloak]{display:none!important}</style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{--
     | Structured data. SoftwareApplication is the honest type for a SaaS product
     | and drives the rich result. Prices are deliberately omitted — see the note
     | in config/marketing.php: there is no verified price to publish yet, and a
     | wrong price in structured data is worse than no price at all.
    --}}
    <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}" type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => config('marketing.product.name'),
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'description' => config('marketing.product.description'),
            'url' => $canonical,
            'slogan' => config('marketing.product.tagline'),
            'featureList' => collect(config('marketing.solution_modules'))->pluck('title')->values()->all(),
            'offers' => [
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'NGN',
                'offerCount' => count(config('billing.plans')),
                'availability' => 'https://schema.org/InStock',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>

    @stack('head')
</head>
<body class="mk-page">
    <a href="#main-content" class="cf-skip">Skip to content</a>

    <x-marketing.navbar />

    <main id="main-content">
        {{ $slot }}
    </main>

    <x-marketing.footer />
</body>
</html>

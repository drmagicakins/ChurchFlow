{{--
 | Footer.
 |
 | Three fixes over the ported version:
 |
 |  1. The logo used `brightness-0 invert`. That filter flattens the whole raster
 |     lockup to flat white, destroying the blue cross and green accent — the only
 |     two brand colours in the asset. The footer now uses <x-ui.brand-logo onDark>
 |     so "Church" is white and "Flow" stays green.
 |
 |  2. Social icons rendered unconditionally and pointed at href="#". A link to
 |     "#" is a dead control that looks functional, and a footer full of them is
 |     the fastest way to make a finished site look unfinished. They now come from
 |     config('marketing.social') and are skipped entirely while unset.
 |
 |  3. No column structure. The reference footer is a four-column sitemap; this
 |     now matches, which also gives every marketing route a second internal link
 |     (real navigation value, and it matters for crawl depth).
--}}

@php
    $social = collect(config('marketing.social'))->filter()->all();

    $columns = [
        'Product' => [
            ['label' => 'Features', 'route' => 'features'],
            ['label' => 'Pricing', 'route' => 'pricing'],
            ['label' => 'Watch Demo', 'route' => 'demo'],
            ['label' => 'Modules', 'href' => route('home').'#modules'],
        ],
        'Company' => [
            ['label' => 'About', 'route' => 'about'],
            ['label' => 'Contact', 'route' => 'contact'],
            ['label' => 'Support', 'route' => 'support'],
        ],
        'Resources' => [
            ['label' => 'Blog', 'route' => 'resources.blog'],
            ['label' => 'Help Center', 'route' => 'resources.help-center'],
            ['label' => 'Guides', 'route' => 'resources.guides'],
            ['label' => 'FAQ', 'href' => route('home').'#faq'],
        ],
    ];

    $socialIcons = [
        'facebook' => 'M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12Z',
        'x' => 'M18.9 3H22l-7.2 8.2L23 21h-6.9l-5.4-6.6L4.5 21H1.4l7.7-8.8L1 3h7.1l4.9 6.1L18.9 3Z',
        'instagram' => 'M12 2c2.7 0 3 0 4.1.06 1.1.05 1.8.22 2.4.46.7.27 1.2.6 1.7 1.1.5.5.9 1 1.1 1.7.24.6.4 1.3.46 2.4.06 1.1.06 1.4.06 4.1s0 3-.06 4.1c-.05 1.1-.22 1.8-.46 2.4a4.6 4.6 0 0 1-1.1 1.7 4.6 4.6 0 0 1-1.7 1.1c-.6.24-1.3.4-2.4.46-1.1.06-1.4.06-4.1.06s-3 0-4.1-.06c-1.1-.05-1.8-.22-2.4-.46a4.6 4.6 0 0 1-1.7-1.1 4.6 4.6 0 0 1-1.1-1.7c-.24-.6-.4-1.3-.46-2.4C2 15 2 14.7 2 12s0-3 .06-4.1c.05-1.1.22-1.8.46-2.4.24-.63.6-1.2 1.1-1.7.5-.5 1-.9 1.7-1.1.6-.24 1.3-.4 2.4-.46C9 2 9.3 2 12 2Zm0 5a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0 8.2a3.2 3.2 0 1 1 0-6.4 3.2 3.2 0 0 1 0 6.4Zm5.2-8.4a1.2 1.2 0 1 0 0-2.4 1.2 1.2 0 0 0 0 2.4Z',
        'linkedin' => 'M6.94 5a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM7 8.48H3V21h4V8.48Zm6.32 0H9.34V21h3.94v-6.57c0-3.66 4.77-4 4.77 0V21H22v-7.93c0-6.17-7.06-5.94-8.72-2.91V8.48Z',
    ];
@endphp

<footer class="mk-footer">
    <div class="mk-shell mk-footer__inner">
        {{-- Brand column --}}
        <div class="mk-footer__brand">
            <x-ui.brand-logo :href="route('home')" :size="30" :on-dark="true" />
            <p class="mk-footer__tagline">{{ config('marketing.product.tagline') }}</p>
            <p class="mk-footer__slogan">{{ config('marketing.product.slogan') }}</p>

            @if (! empty($social))
                <div class="mk-footer__social">
                    @foreach ($social as $network => $url)
                        <a
                            href="{{ $url }}"
                            rel="noopener noreferrer"
                            target="_blank"
                            aria-label="{{ ucfirst($network) }}"
                        >
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="{{ $socialIcons[$network] ?? $socialIcons['x'] }}" />
                            </svg>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Sitemap --}}
        @foreach ($columns as $heading => $links)
            <nav class="mk-footer__col" aria-labelledby="footer-{{ \Illuminate\Support\Str::slug($heading) }}">
                <h2 class="mk-footer__head" id="footer-{{ \Illuminate\Support\Str::slug($heading) }}">
                    {{ $heading }}
                </h2>
                @foreach ($links as $link)
                    <a href="{{ $link['href'] ?? route($link['route']) }}">{{ $link['label'] }}</a>
                @endforeach
            </nav>
        @endforeach
    </div>

    <div class="mk-shell mk-footer__base">
        <p>&copy; {{ date('Y') }} {{ config('marketing.product.name') }}. All rights reserved.</p>
        <div class="mk-footer__legal">
            <a href="{{ route('legal.privacy') }}">Privacy Policy</a>
            <a href="{{ route('legal.terms') }}">Terms of Service</a>
        </div>
    </div>
</footer>

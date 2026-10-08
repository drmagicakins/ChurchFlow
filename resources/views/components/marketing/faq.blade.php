{{--
 | FAQ.
 |
 | Answers the questions the pricing rules inevitably raise — when the church is
 | created, whether email really is included, how SMS is charged, what happens on
 | cancellation. That is the shortest route from "I understand the offer" to
 | "I am willing to pay", and without it those questions become support email.
 |
 | Built on native <details>/<summary> rather than an Alpine accordion: it works
 | with scripting off, it is keyboard-accessible and findable by in-page search
 | for free, and the open/closed state is the browser's problem rather than ours.
 | The FAQPage JSON-LD below is what makes it eligible for a rich result.
--}}

@php
    $faqs = config('marketing.faqs');
@endphp

<section class="mk-section mk-section--tint" id="faq">
    <div class="mk-shell">
        <div class="mk-head mk-head--center" data-reveal>
            <p class="mk-eyebrow" style="justify-content: center">Questions</p>
            <h2 class="mk-h2" style="margin-top: 0.85rem">Before You Get Started</h2>
            <p class="mk-lede" style="margin-top: 1rem">
                The things churches ask us most, answered plainly.
            </p>
        </div>

        <div class="mk-faqs" data-reveal>
            @foreach ($faqs as $i => $faq)
                <details class="mk-card mk-faq" @if ($i === 0) open @endif>
                    <summary>
                        {{ $faq['q'] }}
                    </summary>
                    <div class="mk-faq__body">
                        {{ $faq['a'] }}
                    </div>
                </details>
            @endforeach
        </div>

        <p class="mk-faqs__more" data-reveal>
            Still unsure?
            <a href="{{ route('contact') }}" class="mk-link">
                Talk to us
                <x-ui.icon name="arrow-right" class="h-4 w-4" />
            </a>
        </p>
    </div>
</section>

@push('head')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect(config('marketing.faqs'))->map(fn ($faq) => [
                '@type' => 'Question',
                'name' => $faq['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['a'],
                ],
            ])->values()->all(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endpush

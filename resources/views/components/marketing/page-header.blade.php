{{--
 | Inner-page header.
 |
 | Every marketing sub-page except the home page opens with this: an eyebrow, an
 | h1, a lede and optional action buttons, on the same tinted gradient as the hero
 | so the sub-pages feel like the same site rather than bolted on.
 |
 | The h1 is an h1 here and an h2 on the home page's sections only — each page has
 | exactly one h1, which is the whole point of having this component rather than
 | letting each page write its own header markup.
--}}

@props(['eyebrow', 'title', 'lede' => null, 'accent' => null])

<section class="mk-pagehead">
    <div class="mk-hero__bg" aria-hidden="true">
        <span class="mk-hero__blob mk-hero__blob--blue"></span>
        <span class="mk-hero__blob mk-hero__blob--green"></span>
    </div>

    <div class="mk-shell">
        <div class="mk-pagehead__inner" data-reveal="fade">
            <p class="mk-eyebrow">{{ $eyebrow }}</p>
            <h1 class="mk-h1 mk-pagehead__title">
                {{ $title }}
                @if ($accent)
                    <span class="mk-hero__accent">{{ $accent }}</span>
                @endif
            </h1>

            @if ($lede)
                <p class="mk-lede mk-pagehead__lede">{{ $lede }}</p>
            @endif

            @isset($actions)
                <div class="mk-pagehead__actions">{{ $actions }}</div>
            @endisset
        </div>
    </div>
</section>

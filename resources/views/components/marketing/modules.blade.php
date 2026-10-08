{{--
 | "Explore Our Modules".
 |
 | Two real defects fixed here. First, every card showed the same generic icon
 | path (a hamburger-looking squiggle), so eight distinct modules looked like one
 | card duplicated. Second, the cards were inert <div>s — they invited a click
 | that did nothing. Each now links to its panel in the feature explorer, so the
 | grid doubles as the table of contents for the section below it.
--}}

@php
    $modules = config('marketing.modules');
@endphp

<section class="mk-section mk-section--tint" id="modules">
    <div class="mk-shell">
        <div class="mk-head" data-reveal>
            <p class="mk-eyebrow">Modules</p>
            <h2 class="mk-h2" style="margin-top: 0.85rem">Explore Our Modules</h2>
            <p class="mk-lede" style="margin-top: 1rem">
                Powerful, flexible and easy to use. Discover how each module helps your church work
                smarter and grow stronger.
            </p>
        </div>

        <div class="mk-modules">
            @foreach ($modules as $i => $module)
                <a
                    href="#explorer"
                    class="mk-card mk-card--pad mk-card--interactive mk-module"
                    data-reveal
                    style="--reveal-delay: {{ ($i % 4) * 70 }}ms"
                    data-explorer-target="{{ $module['key'] }}"
                >
                    <span class="mk-icon mk-tone--{{ $module['tone'] }}">
                        <x-ui.icon :name="$module['icon']" class="h-[1.3rem] w-[1.3rem]" />
                    </span>
                    <h3 class="mk-module__title">{{ $module['title'] }}</h3>
                    <p class="mk-module__text">{{ $module['text'] }}</p>
                </a>
            @endforeach
        </div>
    </div>
</section>

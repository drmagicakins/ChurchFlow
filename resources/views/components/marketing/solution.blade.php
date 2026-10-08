{{--
 | "The ChurchFlow Solution".
 |
 | Each card links to the matching panel in the feature explorer below, so the
 | grid is navigation rather than decoration — clicking "Finance & Accounting"
 | scrolls to and selects the Finance screen. The anchor is read from config so
 | a module rename cannot silently leave three sets of links pointing nowhere.
 |
 | Each module now carries its own icon. The previous version used the same
 | shield-and-check path for all eight, which made the grid look like a
 | duplicated placeholder rather than eight distinct capabilities.
--}}

@php
    $solutionModules = config('marketing.solution_modules');
@endphp

<section class="mk-section mk-section--tint" id="solution">
    <div class="mk-shell">
        <div class="mk-head mk-head--center" data-reveal>
            <p class="mk-eyebrow" style="justify-content: center">The Solution</p>
            <h2 class="mk-h2" style="margin-top: 0.85rem">The ChurchFlow Solution</h2>
            <p class="mk-lede" style="margin-top: 1rem">
                ChurchFlow brings everything together — people, finance, activities, communication
                and more — with an intuitive interface, powerful tools and a seamless experience.
            </p>
            <div class="mk-solution__cta">
                <a href="{{ route('features') }}" class="mk-btn mk-btn--primary mk-btn--lg">
                    Explore All Features
                    <x-ui.icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>
        </div>

        <div class="mk-solution__grid">
            @foreach ($solutionModules as $i => $module)
                <a
                    href="#explorer"
                    class="mk-card mk-card--pad mk-card--interactive mk-solution__card"
                    data-reveal
                    style="--reveal-delay: {{ ($i % 4) * 70 }}ms"
                    data-explorer-target="{{ $module['anchor'] }}"
                >
                    <span class="mk-icon mk-icon--sm mk-tone--{{ $i % 2 === 0 ? 'blue' : 'green' }}">
                        <x-ui.icon :name="$module['icon']" class="h-[1.15rem] w-[1.15rem]" />
                    </span>
                    <p class="mk-solution__title">{{ $module['title'] }}</p>
                    <span class="mk-solution__link">
                        See it in action
                        <x-ui.icon name="arrow-right" class="h-3.5 w-3.5" />
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</section>

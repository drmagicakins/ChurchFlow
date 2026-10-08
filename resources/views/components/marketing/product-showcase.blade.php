{{--
 | Interactive feature explorer.
 |
 | This is the "teach, don't just advertise" section from the brief (section 26).
 | A visitor picks a module and sees what that screen actually contains.
 |
 | Two design decisions worth stating:
 |
 |  1. Each panel shows a TABLE of sample rows, not one large statistic. A big
 |     number proves nothing about a product — a church leader reads "Period /
 |     Status / Remittance" across four subvention submissions and immediately
 |     understands what the subvention module is for.
 |
 |  2. It is a real tablist, not just buttons that swap a div. That means
 |     role="tab"/"tabpanel", aria-selected, roving tabindex, and Left/Right/Home/
 |     End key handling, because a tab control that ignores arrow keys says one
 |     thing to a screen reader and does another to a keyboard.
 |
 | Panels are server-rendered and toggled with x-show rather than fetched, so the
 | content is in the HTML for search engines and works before Alpine boots — the
 | first panel is visible by default and x-cloak hides only the rest.
 |
 | WINDOW-LEVEL SELECTION LISTENER
 | The module grid and the solution grid above this section each render cards
 | carrying data-explorer-target="<key>". Those cards are plain anchor links to
 | #explorer, so without help they scroll here but leave whichever panel was
 | already selected — the visitor clicks "Subvention" and arrives at "Members".
 |
 | A delegated window listener is used rather than putting Alpine state on the
 | cards themselves: only one component owns `active`, and having twelve cards
 | each reach into it would make the state impossible to reason about. The
 | listener is registered on x-init so it is cleaned up with the component, and it
 | only reacts to clicks on elements that actually declare a target.
--}}

@php
    $screens = config('marketing.explorer');
    $keys = array_keys($screens);
    $first = $keys[0];
@endphp

<section
    class="mk-section"
    id="explorer"
    x-data="{
        active: '{{ $first }}',
        keys: @js($keys),
        select(key, focus = false) {
            if (! this.keys.includes(key)) return;
            this.active = key;
            if (focus) this.$nextTick(() => this.$refs['tab_' + key]?.focus());
        },
        onKey(delta) {
            const i = this.keys.indexOf(this.active);
            this.select(this.keys[(i + delta + this.keys.length) % this.keys.length], true);
        },
        onEdge(which) {
            this.select(which === 'first' ? this.keys[0] : this.keys[this.keys.length - 1], true);
        }
    }"
    x-init="
        window.addEventListener('click', (e) => {
            const card = e.target.closest('[data-explorer-target]');
            if (! card) return;
            select(card.dataset.explorerTarget);
        });
    "
>
    <div class="mk-shell">
        <div class="mk-head" data-reveal>
            <p class="mk-eyebrow">See it in action</p>
            <h2 class="mk-h2" style="margin-top: 0.85rem">One platform, every part of church life</h2>
            <p class="mk-lede" style="margin-top: 1rem">
                Pick a module to see the screen your team would actually be working in.
                Every view below is sample data, shown so you know what to expect.
            </p>
        </div>

        {{-- Tab strip. Scrolls horizontally on small screens rather than wrapping
             into four rows, which is what pushed the content below the fold. --}}
        <div class="mk-explorer__tabs" role="tablist" aria-label="Product modules" data-reveal>
            @foreach ($screens as $key => $screen)
                <button
                    type="button"
                    role="tab"
                    id="tab-{{ $key }}"
                    x-ref="tab_{{ $key }}"
                    :aria-selected="active === '{{ $key }}' ? 'true' : 'false'"
                    :tabindex="active === '{{ $key }}' ? 0 : -1"
                    aria-controls="panel-{{ $key }}"
                    @click="active = '{{ $key }}'"
                    @keydown.arrow-right.prevent="onKey(1)"
                    @keydown.arrow-left.prevent="onKey(-1)"
                    @keydown.home.prevent="onEdge('first')"
                    @keydown.end.prevent="onEdge('last')"
                    class="mk-tab"
                >
                    <x-ui.icon :name="$screen['icon']" class="h-3.5 w-3.5" />
                    {{ $screen['label'] }}
                </button>
            @endforeach
        </div>

        {{-- Preview frame --}}
        <div class="mk-explorer__frame" data-reveal="scale" style="--reveal-delay: 100ms">
            <div class="mk-app__bar">
                <span class="mk-app__dot"></span>
                <span class="mk-app__dot"></span>
                <span class="mk-app__dot"></span>
                <span class="mk-app__url">app.churchflow.com</span>
                <span class="mk-explorer__secure">
                    <x-ui.icon name="lock" class="h-3 w-3" />
                    Sample data
                </span>
            </div>

            @foreach ($screens as $key => $screen)
                <div
                    role="tabpanel"
                    id="panel-{{ $key }}"
                    aria-labelledby="tab-{{ $key }}"
                    tabindex="0"
                    x-show="active === '{{ $key }}'"
                    @if (! $loop->first) x-cloak @endif
                    class="mk-explorer__panel"
                >
                    <div class="mk-explorer__intro">
                        <span class="mk-icon mk-icon--sm mk-tone--{{ $loop->even ? 'green' : 'blue' }}">
                            <x-ui.icon :name="$screen['icon']" class="h-[1.15rem] w-[1.15rem]" />
                        </span>
                        <div>
                            <p class="mk-explorer__title">{{ $screen['label'] }}</p>
                            <p class="mk-explorer__headline">{{ $screen['headline'] }}</p>
                        </div>
                    </div>

                    <div class="mk-explorer__tablewrap">
                        <table class="mk-table">
                            <thead>
                                <tr>
                                    @foreach ($screen['columns'] as $column)
                                        <th scope="col">{{ $column }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($screen['rows'] as $row)
                                    <tr>
                                        @foreach ($row as $cellIndex => $cell)
                                            <td>
                                                @if ($cellIndex === count($row) - 1)
                                                    @php
                                                        // Status is the last column by convention across
                                                        // every screen, so it gets a chip. Matching on the
                                                        // text keeps config free of presentation details.
                                                        $chip = match (true) {
                                                            in_array($cell, ['Active', 'Approved', 'Delivered', 'Ready', 'Closed'], true) => 'mk-chip--ok',
                                                            in_array($cell, ['Returned', 'Needs credit', 'Follow-up'], true) => 'mk-chip--warn',
                                                            in_array($cell, ['Queued', 'Open', 'Under review', 'Scheduled', 'In progress'], true) => 'mk-chip--info',
                                                            default => '',
                                                        };
                                                    @endphp
                                                    <span class="mk-chip {{ $chip }}">{{ $cell }}</span>
                                                @else
                                                    {{ $cell }}
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <p class="mk-explorer__note">
                        <x-ui.icon name="sparkle" class="h-3.5 w-3.5" />
                        {{ $screen['footnote'] }}
                    </p>
                </div>
            @endforeach
        </div>

        <p class="mk-explorer__cta" data-reveal>
            Ready to see it with your own data?
            <a href="{{ route('register') }}" class="mk-link">
                Get started
                <x-ui.icon name="arrow-right" class="h-4 w-4" />
            </a>
        </p>
    </div>
</section>

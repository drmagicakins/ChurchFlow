{{--
 | /demo
 |
 | An interactive guided walkthrough of the product, using the same explorer data
 | as the home page. No video: there is no recorded demo, and embedding a fake or
 | empty player would be worse than not offering one.
 |
 | What is offered instead is genuinely useful — a clickable tour of each screen
 | with an explanation of what it does and why it is shaped that way — plus an
 | honest note that a live walkthrough is available on request.
 |
 | The explorer is driven by a single Alpine component so the tab state, the
 | keyboard handling and the panel switching live in one place. This is the same
 | pattern as the home page's explorer; the difference is that here it is the
 | whole page rather than one section.
--}}

@php
    $screens = config('marketing.explorer');
    $keys = array_keys($screens);

    // The narrative for each screen: what it is for, and the workflow it supports.
    $narration = [
        'members' =>
            'Start here. Every other module hangs off a member record — attendance, giving, department membership and pastoral follow-up all resolve to a person.',
        'finance' =>
            'Accounts hold the money. Every entry posts against one, and balances are derived rather than stored, so a balance can always be traced back to the entries that produced it.',
        'subvention' =>
            'The workflow is the point. A submission moves through submitted, reviewed, returned, reopened, approved or rejected — and every transition is recorded against the person who made it.',
        'events' =>
            'Events carry capacity and registrations, which is what turns a programme into something you can plan catering and seating for.',
        'attendance' =>
            'Attendance is entered in bulk against a service session, not per person. That is what makes it sustainable to keep up with every week.',
        'communication' =>
            'Email is included in every plan. SMS is drawn from a prepaid wallet, so the cost of a campaign is visible before it is sent.',
        'pastoral' =>
            'Cases, prayer requests and appointments, with notes added over time so a follow-up does not depend on remembering the last conversation.',
        'reports' =>
            'Reports read across the other modules. Nothing is entered here; everything is derived, which is why the numbers agree with each other.',
    ];
@endphp

<x-marketing-layout title="Product Demo — ChurchFlow"
    description="Walk through the ChurchFlow interface module by module: dashboard, members, finance, subvention, events, attendance, communication, pastoral care and reports.">
    <x-marketing.page-header eyebrow="Product demo" title="Walk through it," accent="screen by screen."
        lede="Every screen below is the real interface with sample data. Pick a module to see what your team would be working in.">
        <x-slot:actions>
            <a href="{{ route('contact') }}" class="mk-btn mk-btn--primary mk-btn--lg">
                Book a live walkthrough
                <x-ui.icon name="arrow-right" class="h-4 w-4" />
            </a>
            <a href="{{ route('features') }}" class="mk-btn mk-btn--secondary mk-btn--lg">Read the features</a>
        </x-slot:actions>
    </x-marketing.page-header>

    <section class="mk-section" id="tour" x-data="{
        active: '{{ $keys[0] }}',
        keys: @js($keys),
        select(key) {
            this.active = key;
            this.$nextTick(() => this.$refs['tab_' + key]?.focus());
        },
        onKey(delta) {
            const i = this.keys.indexOf(this.active);
            this.select(this.keys[(i + delta + this.keys.length) % this.keys.length]);
        }
    }">
        <div class="mk-shell">
            <div class="mk-demo">
                {{-- Module list: a vertical list on desktop, a scrolling strip on mobile --}}
                <div class="mk-demo__nav" role="tablist" aria-label="Demo modules" aria-orientation="vertical">
                    @foreach ($screens as $key => $screen)
                        <button type="button" role="tab" id="demo-tab-{{ $key }}"
                            x-ref="tab_{{ $key }}"
                            :aria-selected="active === '{{ $key }}' ? 'true' : 'false'"
                            :tabindex="active === '{{ $key }}' ? 0 : -1"
                            aria-controls="demo-panel-{{ $key }}" @click="active = '{{ $key }}'"
                            @keydown.arrow-down.prevent="onKey(1)" @keydown.arrow-up.prevent="onKey(-1)"
                            class="mk-demo__navitem">
                            <span class="mk-demo__navicon">
                                <x-ui.icon :name="$screen['icon']" class="h-3.5 w-3.5" />
                            </span>
                            {{ $screen['label'] }}
                        </button>
                    @endforeach
                </div>

                {{-- Panels --}}
                <div class="mk-demo__panels">
                    @foreach ($screens as $key => $screen)
                        <div role="tabpanel" id="demo-panel-{{ $key }}"
                            aria-labelledby="demo-tab-{{ $key }}" tabindex="0"
                            x-show="active === '{{ $key }}'" @if ($loop->index > 0) x-cloak @endif
                            class="mk-demo__panel">
                            <div class="mk-app mk-demo__frame">
                                <div class="mk-app__bar">
                                    <span class="mk-app__dot"></span>
                                    <span class="mk-app__dot"></span>
                                    <span class="mk-app__dot"></span>
                                    <span class="mk-app__url">app.churchflow.com/{{ $key }}</span>
                                    <span class="mk-explorer__secure">
                                        <x-ui.icon name="lock" class="h-3 w-3" />
                                        Sample data
                                    </span>
                                </div>

                                <div class="mk-demo__body">
                                    <div class="mk-demo__intro">
                                        <span
                                            class="mk-icon mk-icon--sm mk-tone--{{ $loop->even ? 'green' : 'blue' }}">
                                            <x-ui.icon :name="$screen['icon']" class="h-[1.15rem] w-[1.15rem]" />
                                        </span>
                                        <div>
                                            <h2 class="mk-demo__title">{{ $screen['label'] }}</h2>
                                            <p class="mk-demo__headline">{{ $screen['headline'] }}</p>
                                        </div>
                                    </div>

                                    <p class="mk-demo__narration">
                                        {{ $narration[$key] ?? '' }}
                                    </p>

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
                                                                        $chip = match (true) {
                                                                            in_array(
                                                                                $cell,
                                                                                [
                                                                                    'Active',
                                                                                    'Approved',
                                                                                    'Delivered',
                                                                                    'Ready',
                                                                                    'Closed',
                                                                                ],
                                                                                true,
                                                                            )
                                                                                => 'mk-chip--ok',
                                                                            in_array(
                                                                                $cell,
                                                                                [
                                                                                    'Returned',
                                                                                    'Needs credit',
                                                                                    'Follow-up',
                                                                                ],
                                                                                true,
                                                                            )
                                                                                => 'mk-chip--warn',
                                                                            default => 'mk-chip--info',
                                                                        };
                                                                    @endphp
                                                                    <span
                                                                        class="mk-chip {{ $chip }}">{{ $cell }}</span>
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
                                </div>
                            </div>

                            <p class="mk-demo__note">
                                <x-ui.icon name="sparkle" class="h-3.5 w-3.5" />
                                {{ $screen['footnote'] }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Live demo request --}}
    <section class="mk-section mk-section--tint" id="live">
        <div class="mk-shell">
            <div class="mk-head mk-head--center" data-reveal>
                <p class="mk-eyebrow" style="justify-content: center">Not a recording</p>
                <h2 class="mk-h2" style="margin-top: 0.85rem">See it with your own data</h2>
                <p class="mk-lede" style="margin-top: 1rem">
                    The screens above are sample data. For a walkthrough of your own structure — your
                    departments, your funds, your branch arrangement — talk to us and we will set it up.
                </p>
                <div class="mk-demo__actions">
                    <a href="{{ route('contact') }}" class="mk-btn mk-btn--primary mk-btn--lg">
                        Request a walkthrough
                        <x-ui.icon name="arrow-right" class="h-4 w-4" />
                    </a>
                </div>
            </div>
        </div>
    </section>

    <x-marketing.final-cta />
</x-marketing-layout>

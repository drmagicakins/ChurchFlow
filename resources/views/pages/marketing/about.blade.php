{{--
 | /about
 |
 | Written honestly: it says what the product is for and how it is built, without
 | inventing founding dates, headcount, funding or customer numbers, none of which
 | I have and all of which would be fabrications about a real company.
--}}

@php
    $principles = [
        [
            'icon' => 'users',
            'title' => 'Built around people, not records',
            'text' => 'A member record exists so someone can be followed up with, not so a database can be complete.',
        ],
        [
            'icon' => 'shield',
            'title' => 'Money handled properly',
            'text' =>
                'Entries post to accounts with an approval trail, and are voided rather than deleted. A church treasurer should never have to reconstruct history from memory.',
        ],
        [
            'icon' => 'lock',
            'title' => 'Each church, separated',
            'text' => 'Church data is isolated at the query layer, so one church can never see another\'s records.',
        ],
        [
            'icon' => 'flow',
            'title' => 'One system, not five',
            'text' =>
                'Members, finance, events, attendance and communication share one database, so a number is only ever entered once.',
        ],
    ];

    $built = [
        'Members, families, departments and groups',
        'Finance with accounts, budgets, loans and approvals',
        'Subvention with configurable rules and a full workflow',
        'Events, registrations and attendance',
        'Announcements, email and bulk SMS',
        'Pastoral care, prayer requests and appointments',
        'Reports across every module',
    ];
@endphp

<x-marketing-layout title="About — ChurchFlow"
    description="Why ChurchFlow exists: church administration should be organised, transparent and secure, so church leaders can spend their time on people rather than paperwork.">
    <x-marketing.page-header eyebrow="About" title="Software that respects" accent="how churches actually run."
        lede="ChurchFlow exists because church administration is usually held together by a spreadsheet, a register and one person's memory — and that works right up until it does not.">
        <x-slot:actions>
            <a href="{{ route('features') }}" class="mk-btn mk-btn--primary mk-btn--lg">
                See what it does
                <x-ui.icon name="arrow-right" class="h-4 w-4" />
            </a>
            <a href="{{ route('contact') }}" class="mk-btn mk-btn--secondary mk-btn--lg">Get in touch</a>
        </x-slot:actions>
    </x-marketing.page-header>

    <section class="mk-section" id="why">
        <div class="mk-shell">
            <div class="mk-about">
                <div class="mk-head" data-reveal>
                    <p class="mk-eyebrow">Why it exists</p>
                    <h2 class="mk-h2" style="margin-top: 0.85rem">The problem is not effort. It is tooling.</h2>

                    <div class="mk-about__prose">
                        <p>
                            Church administrators are not short of diligence. They are short of tools
                            that fit the job. A church has members with families and departments, money
                            with funds and approvals, programmes with attendance, and a parent body that
                            expects a remittance return — and almost every church runs all of that on
                            disconnected spreadsheets.
                        </p>
                        <p>
                            The cost is not just time. It is that the numbers cannot be trusted. A
                            treasurer who cannot reconcile a month without a week of work will stop
                            producing the report; a pastor who cannot see who stopped attending cannot
                            follow them up.
                        </p>
                        <p>
                            ChurchFlow puts those pieces in one system, with the structure and the audit
                            trail that the work actually requires. That is the whole idea.
                        </p>
                    </div>
                </div>

                <div class="mk-about__side" data-reveal="scale" style="--reveal-delay: 100ms">
                    <div class="mk-panel-dark mk-about__panel">
                        <p class="mk-about__panelhead">
                            <x-ui.icon name="sparkle" class="h-4 w-4" />
                            What is in the platform today
                        </p>
                        <ul class="mk-checks mk-checks--on-dark mk-about__list">
                            @foreach ($built as $item)
                                <li><span>{{ $item }}</span></li>
                            @endforeach
                        </ul>
                        <a href="{{ route('features') }}"
                            class="mk-btn mk-btn--on-dark mk-btn--block mk-about__panelcta">
                            Explore the modules
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mk-section mk-section--tint" id="principles">
        <div class="mk-shell">
            <div class="mk-head mk-head--center" data-reveal>
                <p class="mk-eyebrow" style="justify-content: center">Principles</p>
                <h2 class="mk-h2" style="margin-top: 0.85rem">How we build it</h2>
                <p class="mk-lede" style="margin-top: 1rem">
                    Four rules that decide what gets built and what gets refused.
                </p>
            </div>

            <div class="mk-principles">
                @foreach ($principles as $principle)
                    <article class="mk-card mk-card--pad mk-principle" data-reveal
                        style="--reveal-delay: {{ $loop->index * 70 }}ms">
                        <span class="mk-icon mk-tone--{{ $loop->even ? 'green' : 'blue' }}">
                            <x-ui.icon :name="$principle['icon']" class="h-[1.3rem] w-[1.3rem]" />
                        </span>
                        <h3 class="mk-principle__title">{{ $principle['title'] }}</h3>
                        <p class="mk-principle__text">{{ $principle['text'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.final-cta />
</x-marketing-layout>

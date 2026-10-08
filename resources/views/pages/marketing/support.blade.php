{{--
 | /support
 |
 | Support landing for existing churches. Topic-led rather than channel-led,
 | because the most common support need is "how do I do X", not "let me open a
 | ticket" — and the Help Center and Guides already answer most of those.
 |
 | No ticket form: there is no ticketing system behind it, and an intake form that
 | files into nothing is the same broken promise as a dead contact form.
--}}

@php
    $topics = [
        [
            'icon' => 'wallet',
            'title' => 'Billing and subscriptions',
            'text' => 'Changing plan, what happens on cancellation, and how payment verification works.',
            'link' => ['label' => 'Read the FAQ', 'url' => route('home') . '#faq'],
        ],
        [
            'icon' => 'chat',
            'title' => 'SMS credit and delivery',
            'text' => 'Topping up the wallet, why a message is queued, and delivery status meanings.',
            'link' => ['label' => 'Communication features', 'url' => route('features') . '#communication'],
        ],
        [
            'icon' => 'users',
            'title' => 'Members and imports',
            'text' => 'Importing from a spreadsheet, fixing duplicates and correcting records.',
            'link' => ['label' => 'People features', 'url' => route('features') . '#members'],
        ],
        [
            'icon' => 'receipt',
            'title' => 'Subvention and approvals',
            'text' => 'Rule sets, periods, and what each workflow status means for a submission.',
            'link' => ['label' => 'Subvention features', 'url' => route('features') . '#subvention'],
        ],
        [
            'icon' => 'lock',
            'title' => 'Accounts and access',
            'text' => 'Roles, permissions, and who can see finance or pastoral records.',
            'link' => ['label' => 'Security and access', 'url' => route('features') . '#students'],
        ],
        [
            'icon' => 'doc',
            'title' => 'Exports and reports',
            'text' => 'Getting your data out, and which report answers which question.',
            'link' => ['label' => 'Reports features', 'url' => route('features') . '#reports'],
        ],
    ];

    $resources = [
        [
            'icon' => 'doc',
            'title' => 'Help Center',
            'text' => 'Short answers to the questions churches ask most.',
            'route' => 'resources.help-center',
        ],
        [
            'icon' => 'route',
            'title' => 'Guides',
            'text' => 'Step-by-step walkthroughs for setup, imports and attendance.',
            'route' => 'resources.guides',
        ],
        [
            'icon' => 'clock',
            'title' => 'FAQ',
            'text' => 'Billing, SMS credit, and when your church account is created.',
            'url' => route('home') . '#faq',
        ],
    ];
@endphp

<x-marketing-layout title="Support — ChurchFlow"
    description="Get help with ChurchFlow: setup, billing, SMS credit, data import and account questions.">
    <x-marketing.page-header eyebrow="Support" title="Help, organised by" accent="what you are trying to do."
        lede="Most questions are answered below. Start with the topic that matches the problem rather than a ticket form.">
        <x-slot:actions>
            <a href="{{ route('resources.help-center') }}" class="mk-btn mk-btn--primary mk-btn--lg">
                Open the Help Center
                <x-ui.icon name="arrow-right" class="h-4 w-4" />
            </a>
            <a href="{{ route('resources.guides') }}" class="mk-btn mk-btn--secondary mk-btn--lg">Browse guides</a>
        </x-slot:actions>
    </x-marketing.page-header>

    <section class="mk-section" id="topics">
        <div class="mk-shell">
            <div class="mk-head" data-reveal>
                <p class="mk-eyebrow">Topics</p>
                <h2 class="mk-h2" style="margin-top: 0.85rem">Common support topics</h2>
            </div>

            <div class="mk-support__grid">
                @foreach ($topics as $topic)
                    <a href="{{ $topic['link']['url'] }}" class="mk-card mk-card--pad mk-card--interactive mk-support"
                        data-reveal style="--reveal-delay: {{ ($loop->index % 3) * 70 }}ms">
                        <span class="mk-icon mk-tone--{{ $loop->even ? 'blue' : 'green' }}">
                            <x-ui.icon :name="$topic['icon']" class="h-[1.3rem] w-[1.3rem]" />
                        </span>
                        <h3 class="mk-support__title">{{ $topic['title'] }}</h3>
                        <p class="mk-support__text">{{ $topic['text'] }}</p>
                        <span class="mk-support__link">
                            {{ $topic['link']['label'] }}
                            <x-ui.icon name="arrow-right" class="h-3.5 w-3.5" />
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mk-section mk-section--tint" id="self-serve">
        <div class="mk-shell">
            <div class="mk-head mk-head--center" data-reveal>
                <p class="mk-eyebrow" style="justify-content: center">Self-serve</p>
                <h2 class="mk-h2" style="margin-top: 0.85rem">Read it yourself</h2>
                <p class="mk-lede" style="margin-top: 1rem">
                    Everything we have written is public. No login required.
                </p>
            </div>

            <div class="mk-support__resources">
                @foreach ($resources as $resource)
                    <a href="{{ isset($resource['url']) ? $resource['url'] : route($resource['route']) }}"
                        class="mk-card mk-card--pad mk-card--interactive mk-support" data-reveal
                        style="--reveal-delay: {{ $loop->index * 70 }}ms">
                        <span class="mk-icon mk-icon--sm mk-tone--blue">
                            <x-ui.icon :name="$resource['icon']" class="h-[1.15rem] w-[1.15rem]" />
                        </span>
                        <h3 class="mk-support__title">{{ $resource['title'] }}</h3>
                        <p class="mk-support__text">{{ $resource['text'] }}</p>
                        <span class="mk-support__link">
                            Open
                            <x-ui.icon name="arrow-right" class="h-3.5 w-3.5" />
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.faq />
</x-marketing-layout>

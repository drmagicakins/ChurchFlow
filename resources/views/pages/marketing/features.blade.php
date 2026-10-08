{{--
 | /features
 |
 | The deep-detail page for every module. Structured as one anchored block per
 | module so the home page's module cards and the nav can deep-link straight to a
 | specific capability (#finance, #subvention, …) rather than dumping the visitor
 | at the top of a long page.
 |
 | Each block states what the module does and lists its concrete capabilities. The
 | content mirrors the modules that actually exist in the application, so the
 | marketing page cannot promise something the product does not have.
--}}

@php
    $modules = [
        [
            'key' => 'members',
            'icon' => 'users',
            'tone' => 'blue',
            'title' => 'People & Members',
            'summary' => 'One record per person, with the relationships and history that make it useful.',
            'points' => [
                'Member profiles with contact details, status and photo',
                'Families and household relationships',
                'Departments and small groups',
                'Bulk import and export from CSV / spreadsheet',
                'Bulk status changes for a whole group',
                'Custom fields for what your church tracks',
            ],
        ],
        [
            'key' => 'finance',
            'icon' => 'naira',
            'tone' => 'green',
            'title' => 'Finance & Accounting',
            'summary' => 'Offerings, expenses and budgets recorded against real accounts, with an approval trail.',
            'points' => [
                'Multiple accounts and funds, including restricted funds',
                'Income and expense entries against any account',
                'Transfers between accounts, recorded as pairs',
                'Budgets with actual-versus-budget tracking',
                'Loans with repayment schedules and payments',
                'Approval queue — nothing posts without sign-off',
                'Void rather than delete, so the audit trail survives',
                'Live balances and period reports',
            ],
        ],
        [
            'key' => 'subvention',
            'icon' => 'receipt',
            'tone' => 'violet',
            'title' => 'Subvention',
            'summary' => 'Rule-driven remittance to a parent body, with a workflow that survives scrutiny.',
            'points' => [
                'Configurable rule sets — percentages, tiers, exclusions',
                'Periods that group submissions for reconciliation',
                'Automatic calculation from recorded income',
                'Submission, review, return, reopen, approve, reject',
                'Every transition recorded against the user who made it',
                'Remittance totals per period for head office',
            ],
        ],
        [
            'key' => 'events',
            'icon' => 'calendar',
            'tone' => 'amber',
            'title' => 'Events & Calendar',
            'summary' => 'Plan programmes, take registrations and see who is coming.',
            'points' => [
                'Events with dates, times and capacity limits',
                'Public registration links for members',
                'Registrations counted against capacity',
                'Combined calendar view of events and open tasks',
                'Recurring services and midweek programmes',
            ],
        ],
        [
            'key' => 'attendance',
            'icon' => 'check-in',
            'tone' => 'blue',
            'title' => 'Attendance & Check-in',
            'summary' => 'Reliable records of who was present, which is what follow-up actually depends on.',
            'points' => [
                'Service and programme attendance sessions',
                'Bulk check-in — mark a whole section at once',
                'Per-service totals and trends over time',
                'Headcount against previous services',
                'Follow-up lists for people who have stopped attending',
            ],
        ],
        [
            'key' => 'communication',
            'icon' => 'mail',
            'tone' => 'green',
            'title' => 'Communication',
            'summary' => 'Reach the right people, on the right channel, without exporting anything.',
            'points' => [
                'Email notifications included in every plan',
                'Bulk SMS from a prepaid wallet, pay-as-you-go',
                'Targeted audiences — all members, a department, a group',
                'Announcements with audience types and visibility',
                'Delivery status per channel',
            ],
        ],
        [
            'key' => 'pastoral',
            'icon' => 'heart',
            'tone' => 'rose',
            'title' => 'Pastoral Care',
            'summary' => 'Prayer, counselling, welfare and appointments, held confidentially and followed up.',
            'points' => [
                'Prayer requests with status through to answered',
                'Pastoral cases with notes added over time',
                'Case assignment, escalation and closure',
                'Appointments scheduling',
                'Welfare requests tracked to resolution',
            ],
        ],
        [
            'key' => 'reports',
            'icon' => 'pie',
            'tone' => 'violet',
            'title' => 'Reports & Analytics',
            'summary' => 'The numbers that answer the questions a board or a parent body actually asks.',
            'points' => [
                'Membership growth over any period',
                'Income versus expenses by month and by fund',
                'Attendance trends by service and by department',
                'Subvention summaries per period',
                'Branch comparison for multi-branch churches',
            ],
        ],
    ];

    $journey = [
        ['icon' => 'route', 'title' => 'Choose a plan', 'text' => 'Pick the plan that matches your size.'],
        ['icon' => 'wallet', 'title' => 'Pay securely', 'text' => 'Card, transfer or USSD via our providers.'],
        ['icon' => 'shield', 'title' => 'We verify it', 'text' => 'Confirmed server-side against the provider.'],
        ['icon' => 'building', 'title' => 'Church created', 'text' => 'Your workspace is then provisioned.'],
    ];
@endphp

<x-marketing-layout title="Features — ChurchFlow"
    description="Explore every ChurchFlow module: members, finance, subvention, events, attendance, communication, pastoral care and reporting. See what each one does.">
    <x-marketing.page-header eyebrow="Features" title="Every part of church life," accent="in one platform."
        lede="ChurchFlow is built module by module around the work a church actually does — from the offering count on Sunday to the subvention return at quarter end.">
        <x-slot:actions>
            <a href="{{ route('register') }}" class="mk-btn mk-btn--primary mk-btn--lg">
                Get Started
                <x-ui.icon name="arrow-right" class="h-4 w-4" />
            </a>
            <a href="{{ route('demo') }}" class="mk-btn mk-btn--secondary mk-btn--lg">See the demo</a>
        </x-slot:actions>
    </x-marketing.page-header>

    {{-- Module index — an in-page table of contents, so a visitor can jump to the
         module they came for instead of scrolling past seven they did not. --}}
    <nav class="mk-section mk-section--tight" aria-label="Jump to a module">
        <div class="mk-shell">
            <ul class="mk-moduleindex" data-reveal>
                @foreach ($modules as $module)
                    <li>
                        <a href="#{{ $module['key'] }}">
                            <x-ui.icon :name="$module['icon']" class="h-4 w-4" />
                            {{ $module['title'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </nav>

    @foreach ($modules as $i => $module)
        <section class="mk-section {{ $loop->even ? 'mk-section--tint' : '' }}" id="{{ $module['key'] }}">
            <div class="mk-shell">
                <div class="mk-featureblock {{ $loop->odd ? '' : 'mk-featureblock--flip' }}">
                    <div class="mk-featureblock__lead" data-reveal>
                        <span class="mk-icon mk-tone--{{ $module['tone'] }}">
                            <x-ui.icon :name="$module['icon']" class="h-[1.35rem] w-[1.35rem]" />
                        </span>
                        <h2 class="mk-h2 mk-featureblock__title">{{ $module['title'] }}</h2>
                        <p class="mk-lede mk-featureblock__summary">{{ $module['summary'] }}</p>
                        <a href="{{ route('register') }}" class="mk-link mk-featureblock__cta">
                            Try it in ChurchFlow
                            <x-ui.icon name="arrow-right" class="h-4 w-4" />
                        </a>
                    </div>

                    <ul class="mk-featureblock__points" data-reveal="scale" style="--reveal-delay: 100ms">
                        @foreach ($module['points'] as $point)
                            <li>
                                <span class="mk-featureblock__tick" aria-hidden="true">
                                    <x-ui.icon name="check" class="h-3 w-3" />
                                </span>
                                {{ $point }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>
    @endforeach

    {{-- Onboarding journey, repeated here because a features visitor is often a
         buying visitor and this page may be their entry point. --}}
    <section class="mk-section mk-section--tint" id="getting-started">
        <div class="mk-shell">
            <div class="mk-head mk-head--center" data-reveal>
                <p class="mk-eyebrow" style="justify-content: center">Getting started</p>
                <h2 class="mk-h2" style="margin-top: 0.85rem">How you get in</h2>
                <p class="mk-lede" style="margin-top: 1rem">
                    Four steps, and your church workspace is created only after payment is verified.
                </p>
            </div>

            <ol class="mk-steps">
                @foreach ($journey as $step)
                    <li class="mk-step" data-reveal style="--reveal-delay: {{ $loop->index * 80 }}ms">
                        <div class="mk-step__top">
                            <span
                                class="mk-step__num mk-num">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="mk-step__icon">
                                <x-ui.icon :name="$step['icon']" class="h-[1.05rem] w-[1.05rem]" />
                            </span>
                        </div>
                        <h3 class="mk-step__title">{{ $step['title'] }}</h3>
                        <p class="mk-step__text">{{ $step['text'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <x-marketing.final-cta />
</x-marketing-layout>

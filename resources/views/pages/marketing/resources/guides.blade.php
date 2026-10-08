{{--
 | /resources/guides
 |
 | Setup walkthroughs. Written as ordered steps against the real onboarding
 | sequence — plan, payment, verification, provisioning, wizard — because that is
 | the order things actually happen in, and a guide that disagrees with the
 | product is worse than no guide.
--}}

@php
    $guides = [
        [
            'icon' => 'route',
            'title' => 'Getting your church set up',
            'summary' =>
                'From choosing a plan to your first sign-in, including what happens between payment and provisioning.',
            'steps' => [
                'Choose the plan that matches your church size and structure.',
                'Complete payment by card, bank transfer or USSD.',
                'Wait for verification — your workspace is provisioned only after the payment is confirmed against the provider.',
                'Sign in and complete the setup wizard: church details, first department, service times.',
                'Import your existing member list, if you have one.',
                'Invite your administrators and finance team, and set their roles.',
            ],
        ],
        [
            'icon' => 'users',
            'title' => 'Importing your member list',
            'summary' => 'Bringing an existing register or spreadsheet into ChurchFlow without duplicating records.',
            'steps' => [
                'Prepare your file with one row per person and clear column headings.',
                'Map your columns to member fields — name and status are required, the rest are optional.',
                'Run the import and review the results before confirming.',
                'Check for duplicates and merge or remove them.',
                'Set up families and departments, then attach members to them.',
            ],
        ],
        [
            'icon' => 'check-in',
            'title' => 'Running attendance every week',
            'summary' => 'Setting up a service session and taking attendance in a way you will actually keep up with.',
            'steps' => [
                'Create attendance sessions for your regular services.',
                'At the service, record attendance in bulk rather than person by person.',
                'Save the session — totals are added to the service history automatically.',
                'Review the trend against previous weeks.',
                'Generate the follow-up list for members who have stopped attending.',
            ],
        ],
        [
            'icon' => 'naira',
            'title' => 'Setting up finance properly',
            'summary' => 'Accounts, funds and approvals configured so a month reconciles without a week of work.',
            'steps' => [
                'Create the accounts your church actually uses, including restricted funds.',
                'Set opening balances.',
                'Decide which entry types need approval and configure the queue.',
                'Record income and expenses against the right account as they happen.',
                'Create budgets and compare actual against them at month end.',
                'Void rather than delete anything recorded in error, so the trail survives.',
            ],
        ],
        [
            'icon' => 'receipt',
            'title' => 'Configuring subvention',
            'summary' => 'Rule sets, periods and the approval workflow, for churches that remit to a parent body.',
            'steps' => [
                'Create a rule set describing how remittance is calculated for your church.',
                'Create the period you are remitting for.',
                'Create a submission and let ChurchFlow calculate the remittance from recorded income.',
                'Review the draft and submit it.',
                'Follow it through review, any return and correction, to approval.',
                'Reconcile the remittance total against the period.',
            ],
        ],
        [
            'icon' => 'chat',
            'title' => 'Messaging your congregation',
            'summary' => 'Email and SMS to the right audience, with the cost visible before you send.',
            'steps' => [
                'Decide the audience — all members, a department, or a group.',
                'For email, compose and send; email is included in your plan.',
                'For SMS, check your wallet balance and top up if needed.',
                'Send and watch the delivery status per channel.',
            ],
        ],
    ];
@endphp

<x-marketing-layout title="Guides — ChurchFlow"
    description="Step-by-step guides for setting up ChurchFlow, importing members, running attendance and configuring subvention rules.">
    <x-marketing.page-header eyebrow="Guides" title="Step-by-step," accent="in the order it happens."
        lede="Six walkthroughs covering setup, people, finance and communication. Each one is written against the real sequence of the product.">
        <x-slot:actions>
            <a href="{{ route('register') }}" class="mk-btn mk-btn--primary mk-btn--lg">
                Get started
                <x-ui.icon name="arrow-right" class="h-4 w-4" />
            </a>
            <a href="{{ route('resources.help-center') }}" class="mk-btn mk-btn--secondary mk-btn--lg">Help Center</a>
        </x-slot:actions>
    </x-marketing.page-header>

    <section class="mk-section">
        <div class="mk-shell">
            <div class="mk-guides">
                @foreach ($guides as $guide)
                    <article class="mk-card mk-card--pad mk-guide"
                        id="{{ \Illuminate\Support\Str::slug($guide['title']) }}" data-reveal
                        style="--reveal-delay: {{ ($loop->index % 2) * 80 }}ms">
                        <div class="mk-guide__head">
                            <span class="mk-icon mk-tone--{{ $loop->even ? 'blue' : 'green' }}">
                                <x-ui.icon :name="$guide['icon']" class="h-[1.3rem] w-[1.3rem]" />
                            </span>
                            <div>
                                <h2 class="mk-guide__title">{{ $guide['title'] }}</h2>
                                <p class="mk-guide__summary">{{ $guide['summary'] }}</p>
                            </div>
                        </div>

                        <ol class="mk-guide__steps">
                            @foreach ($guide['steps'] as $step)
                                <li>
                                    <span class="mk-guide__stepnum mk-num">{{ $loop->iteration }}</span>
                                    <span class="mk-guide__steptext">{{ $step }}</span>
                                </li>
                            @endforeach
                        </ol>

                        <a href="{{ route('register') }}" class="mk-link mk-guide__cta">
                            Start this in ChurchFlow
                            <x-ui.icon name="arrow-right" class="h-4 w-4" />
                        </a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.faq />

    <x-marketing.final-cta />
</x-marketing-layout>

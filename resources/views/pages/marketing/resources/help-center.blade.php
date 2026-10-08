{{--
 | /resources/help-center
 |
 | Article-led help index. The answers here are drawn from the same facts the FAQ
 | and the pricing rules state, grouped by the area of the product they belong to,
 | so a visitor can find the answer by the module they were using.
--}}

@php
    $sections = [
        [
            'icon' => 'rocket',
            'title' => 'Getting started',
            'articles' => [
                [
                    'q' => 'When is my church account created?',
                    'a' =>
                        'After your payment has been verified. You choose a plan and pay first; we confirm the payment against our provider, and only then is your church workspace provisioned.',
                ],
                [
                    'q' => 'What is the setup wizard?',
                    'a' =>
                        'A guided first-run flow: church details, your first department, and an optional member import. You can skip any step and return to it later.',
                ],
                [
                    'q' => 'Can I import my existing member list?',
                    'a' =>
                        'Yes. Members can be imported from a CSV or spreadsheet export, and exported again at any time.',
                ],
            ],
        ],
        [
            'icon' => 'wallet',
            'title' => 'Billing',
            'articles' => [
                [
                    'q' => 'Is email included in the price?',
                    'a' =>
                        'Yes. Email notifications are included in every plan at no additional cost and are not metered separately.',
                ],
                [
                    'q' => 'How is bulk SMS charged?',
                    'a' =>
                        'Separately and pay-as-you-go, from a prepaid wallet. Credit is consumed as you send, so a church that does not send bulk SMS never pays for it.',
                ],
                [
                    'q' => 'Which payment methods can we use?',
                    'a' =>
                        'Card, bank transfer and USSD through our payment providers. Every payment is confirmed server-side before your account is activated.',
                ],
                [
                    'q' => 'Can we change or cancel our plan?',
                    'a' =>
                        'Yes, at any time. We will make an export of your records available so you are not locked in.',
                ],
            ],
        ],
        [
            'icon' => 'users',
            'title' => 'Members and people',
            'articles' => [
                [
                    'q' => 'What is the difference between a member and a user?',
                    'a' =>
                        'A member is a person in your congregation. A user is someone who can sign in to ChurchFlow. Not every member needs a user account.',
                ],
                [
                    'q' => 'How do families and departments work?',
                    'a' =>
                        'A member can belong to a family and to one or more departments and groups. Both are used for filtering, reporting and targeted messaging.',
                ],
            ],
        ],
        [
            'icon' => 'naira',
            'title' => 'Finance',
            'articles' => [
                [
                    'q' => 'Why can a transaction be voided but not deleted?',
                    'a' =>
                        'Deleting financial history makes a report impossible to audit. A void records that the entry happened and was reversed, leaving the trail intact.',
                ],
                [
                    'q' => 'What happens when someone records an expense?',
                    'a' =>
                        'Depending on your configuration it either posts directly or enters an approval queue. Nothing posts without the sign-off your church requires.',
                ],
                [
                    'q' => 'How are account balances calculated?',
                    'a' =>
                        'Balances are derived from the entries posted against the account rather than stored separately, so every balance traces back to the transactions that produced it.',
                ],
            ],
        ],
        [
            'icon' => 'receipt',
            'title' => 'Subvention',
            'articles' => [
                [
                    'q' => 'What is a rule set?',
                    'a' =>
                        'The configuration that decides how remittance is calculated for your church — percentages, tiers and exclusions. Different churches can use different rules.',
                ],
                [
                    'q' => 'What do the submission statuses mean?',
                    'a' =>
                        'Submitted, under review, returned, reopened, approved and rejected. Every transition is recorded against the person who made it.',
                ],
                [
                    'q' => 'Can a returned submission be corrected?',
                    'a' =>
                        'Yes. A returned submission can be reopened, corrected and resubmitted without losing its history.',
                ],
            ],
        ],
        [
            'icon' => 'lock',
            'title' => 'Security and access',
            'articles' => [
                [
                    'q' => 'Can one church see another church\'s data?',
                    'a' =>
                        'No. Church data is isolated at the query layer, so records are scoped to the requesting church before they are returned.',
                ],
                [
                    'q' => 'Who can see finance and pastoral records?',
                    'a' =>
                        'Access is controlled by role and permission. Panels a user has no permission to see are not rendered for them at all.',
                ],
            ],
        ],
    ];
@endphp

<x-marketing-layout title="Help Center — ChurchFlow"
    description="Answers to the questions churches ask most about setting up ChurchFlow, billing, SMS credit and managing members.">
    <x-marketing.page-header eyebrow="Help Center" title="Answers to the" accent="questions churches ask."
        lede="Short, direct explanations of how ChurchFlow works — grouped by the part of the product you were using when the question came up.">
        <x-slot:actions>
            <a href="{{ route('resources.guides') }}" class="mk-btn mk-btn--primary mk-btn--lg">
                Step-by-step guides
                <x-ui.icon name="arrow-right" class="h-4 w-4" />
            </a>
            <a href="{{ route('support') }}" class="mk-btn mk-btn--secondary mk-btn--lg">Support topics</a>
        </x-slot:actions>
    </x-marketing.page-header>

    <section class="mk-section">
        <div class="mk-shell">
            <div class="mk-help">
                @foreach ($sections as $section)
                    <section class="mk-help__group" id="{{ \Illuminate\Support\Str::slug($section['title']) }}"
                        data-reveal>
                        <div class="mk-help__head">
                            <span class="mk-icon mk-icon--sm mk-tone--{{ $loop->even ? 'green' : 'blue' }}">
                                <x-ui.icon :name="$section['icon']" class="h-[1.15rem] w-[1.15rem]" />
                            </span>
                            <h2 class="mk-help__title">{{ $section['title'] }}</h2>
                        </div>

                        <div class="mk-faqs mk-faqs--inline">
                            @foreach ($section['articles'] as $article)
                                <details class="mk-card mk-faq">
                                    <summary>{{ $article['q'] }}</summary>
                                    <div class="mk-faq__body">{{ $article['a'] }}</div>
                                </details>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.final-cta />
</x-marketing-layout>

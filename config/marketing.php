<?php

/*
 |--------------------------------------------------------------------------
 | ChurchFlow marketing content
 |--------------------------------------------------------------------------
 |
 | Every word, price, statistic and testimonial on the public site lives here,
 | not in Blade. The brief is explicit (section 37): do not permanently hardcode
 | pricing, customer names, testimonials or statistics. Keeping them in a config
 | file means a non-developer can change a plan price without touching a view,
 | and it makes the placeholders obvious — `PRICE_TBD` and `Custom` are visibly
 | not-final, whereas a plausible-looking "₦45,000" quietly ships as fact.
 |
 | Anything marked PLACEHOLDER is deliberate and must be replaced with verified
 | data before launch. Nothing here is a claim we can substantiate yet.
 |
 | Prices are integers in the smallest display unit (naira, not kobo) because
 | nothing on the landing page performs arithmetic on them; the pricing engine
 | will own real money handling server-side.
 */

return [

    /*
     | Product identity. Used by the nav, footer, SEO metadata and JSON-LD, so the
     | tagline is stated once and cannot drift between them.
     */    'product' => [
        'name' => 'ChurchFlow',
        'tagline' => 'The Operating Platform for Modern Churches',
        'slogan' => 'Manage. Connect. Grow.',
        'description' => 'ChurchFlow is an all-in-one church management platform for members, finance, events, attendance, communication, pastoral care, subvention and reporting.',
    ],

    /*
     | Contact details. PLACEHOLDER — the brief forbids inventing final contact
     | data. `null` makes the contact page render an honest "not yet configured"
     | state instead of a dead mailto: to an address nobody owns.
     */
    'contact' => [
        'email' => null,
        'support_email' => null,
        'phone' => null,
        'address' => null,
    ],

    /*
     | Social accounts. PLACEHOLDER — empty means the footer renders no icons at
     | all rather than linking to "#", which is a dead link that looks live.
     */
    'social' => [
        'facebook' => null,
        'x' => null,
        'instagram' => null,
        'linkedin' => null,
    ],

    /*
     | Trust indicators directly under the hero.
     */
    'benefits' => [
        ['icon' => 'bolt', 'title' => 'Easy Setup', 'text' => 'Get started in minutes.'],
        ['icon' => 'shield', 'title' => 'Secure & Reliable', 'text' => 'Your data is protected.'],
        ['icon' => 'building', 'title' => 'Built for Churches', 'text' => 'Designed around real church operations.'],
    ],

    /*
     | Hero dashboard mockup. PLACEHOLDER sample data — this is illustrative UI,
     | not a real tenant. Labels are kept alongside values so the markup stays a
     | dumb loop instead of a hand-written grid that drifts from the numbers.
     */
    'demo' => [
        'greeting' => 'Good morning, Pastor John',
        'subtitle' => "Here's what's happening at Grace Assembly today.",
        'stats' => [
            ['label' => 'Members', 'value' => '1,284', 'delta' => '+42 this month', 'tone' => 'blue', 'icon' => 'users'],
            ['label' => 'Active members', 'value' => '842', 'delta' => '65% of total', 'tone' => 'green', 'icon' => 'check-in'],
            ['label' => 'Income', 'value' => '₦12,450,000', 'delta' => '+8.2% vs last month', 'tone' => 'plain', 'icon' => 'naira'],
            ['label' => 'Expenses', 'value' => '₦7,820,000', 'delta' => '-3.1% vs last month', 'tone' => 'plain', 'icon' => 'receipt'],
        ],
        // Bar heights are percentages, low to high, oldest to newest. Six months.
        'chart' => [
            'title' => 'Financial Overview',
            'caption' => 'Last 6 months',
            'bars' => [
                ['month' => 'Apr', 'income' => 40, 'expense' => 24],
                ['month' => 'May', 'income' => 55, 'expense' => 30],
                ['month' => 'Jun', 'income' => 48, 'expense' => 28],
                ['month' => 'Jul', 'income' => 70, 'expense' => 38],
                ['month' => 'Aug', 'income' => 60, 'expense' => 35],
                ['month' => 'Sep', 'income' => 82, 'expense' => 44],
            ],
        ],
        'events' => [
            'title' => 'Upcoming',
            'items' => [
                ['date' => '12 Oct', 'name' => 'Sunday Service', 'meta' => '842 expected'],
                ['date' => '15 Oct', 'name' => 'Midweek Bible Study', 'meta' => '310 expected'],
                ['date' => '19 Oct', 'name' => 'Youth Convention', 'meta' => '180 registered'],
            ],
        ],
        'activity' => [
            'title' => 'Recent activity',
            'items' => [
                ['icon' => 'naira', 'tone' => 'green', 'text' => 'Offering recorded — Sunday 1st service', 'time' => '2h ago'],
                ['icon' => 'user', 'tone' => 'blue', 'text' => '6 new members joined the workforce', 'time' => 'Yesterday'],
                ['icon' => 'megaphone', 'tone' => 'blue', 'text' => 'Announcement sent to 1,240 members', 'time' => '2 days ago'],
            ],
        ],
    ],

    /*
     | The four problems in the Challenge section.
     */
    'problems' => [
        ['icon' => 'users', 'title' => 'Disorganized member records', 'text' => 'Details scattered across registers, spreadsheets and notebooks.'],
        ['icon' => 'naira', 'title' => 'Manual financial tracking', 'text' => 'Offerings, expenses and budgets reconciled by hand every month.'],
        ['icon' => 'calendar', 'title' => 'Missed events and low attendance visibility', 'text' => 'No reliable record of who came, or who stopped coming.'],
        ['icon' => 'chat', 'title' => 'Poor communication with members', 'text' => 'Announcements that never reach the people who need them.'],
        ['icon' => 'chart-up', 'title' => 'Lack of visibility and proper reports', 'text' => 'No clear picture of growth, giving or branch performance.'],
        ['icon' => 'clock', 'title' => 'Time-consuming administration', 'text' => 'Hours a week spent on work software should be doing.'],
    ],

    /*
     | The eight solution modules. `anchor` matches the ids used by the product
     | showcase and the modules grid, so all three link to the same place and a
     | rename cannot silently break three sets of links.
     */
    'solution_modules' => [
        ['icon' => 'users', 'title' => 'People & Members', 'anchor' => 'members'],
        ['icon' => 'naira', 'title' => 'Finance & Accounting', 'anchor' => 'finance'],
        ['icon' => 'calendar', 'title' => 'Events & Calendar', 'anchor' => 'events'],
        ['icon' => 'check-in', 'title' => 'Attendance & Check-in', 'anchor' => 'attendance'],
        ['icon' => 'branches', 'title' => 'Departments & Branches', 'anchor' => 'modules'],
        ['icon' => 'mail', 'title' => 'Communication & Notifications', 'anchor' => 'communication'],
        ['icon' => 'heart', 'title' => 'Pastoral Care & Follow-ups', 'anchor' => 'pastoral'],
        ['icon' => 'pie', 'title' => 'Reports & Analytics', 'anchor' => 'reports'],
    ],

    /*
     | Onboarding steps. The order is the payment journey from the brief, and it is
     | load-bearing: ChurchFlow creates a church tenant only AFTER verified payment.
     | Do not reorder these to lead with "register free" — that misrepresents the
     | product as free-to-start.
     */
    'steps' => [
        [
            'n' => '01',
            'icon' => 'route',
            'title' => 'Choose a Plan',
            'text' => "Select the plan that fits your church's size and needs.",
        ],
        [
            'n' => '02',
            'icon' => 'wallet',
            'title' => 'Make Payment',
            'text' => 'Pay securely by card, transfer or USSD through our payment providers.',
        ],
        [
            'n' => '03',
            'icon' => 'shield',
            'title' => 'We Verify It',
            'text' => 'Your payment is confirmed server-side. Nothing is activated on a client-side "success" screen alone.',
        ],
        [
            'n' => '04',
            'icon' => 'rocket',
            'title' => 'Your Church Is Created',
            'text' => 'We provision your church workspace and walk you through the setup wizard.',
        ],
        [
            'n' => '05',
            'icon' => 'settings',
            'title' => 'Set Up & Start',
            'text' => 'Import members, invite your team and take a guided tour of the platform.',
        ],
    ],

    /*
     | The module grid. `anchor` links each card to its detail block in the product
     | showcase below it, so the cards are navigation rather than decoration.
     */
    'modules' => [
        ['key' => 'members', 'icon' => 'users', 'title' => 'Members', 'text' => 'Manage people, families, groups and departments.', 'tone' => 'blue'],
        ['key' => 'finance', 'icon' => 'naira', 'title' => 'Finance', 'text' => 'Track income, expenses, budgets and loans.', 'tone' => 'green'],
        ['key' => 'subvention', 'icon' => 'receipt', 'title' => 'Subvention', 'text' => 'Configurable submission, calculation and approval.', 'tone' => 'violet'],
        ['key' => 'events', 'icon' => 'calendar', 'title' => 'Events', 'text' => 'Organize programs, registrations and attendance.', 'tone' => 'amber'],
        ['key' => 'communication', 'icon' => 'mail', 'title' => 'Communication', 'text' => 'Email, SMS and targeted messaging.', 'tone' => 'blue'],
        ['key' => 'attendance', 'icon' => 'check-in', 'title' => 'Attendance', 'text' => 'Check-in, service records and follow-up lists.', 'tone' => 'green'],
        ['key' => 'pastoral', 'icon' => 'heart', 'title' => 'Pastoral Care', 'text' => 'Prayer, counselling, welfare and follow-ups.', 'tone' => 'rose'],
        ['key' => 'reports', 'icon' => 'pie', 'title' => 'Reports', 'text' => 'Insights and analytics across every module.', 'tone' => 'violet'],
    ],

    /*
     | Feature explorer screens. Each entry is a small table of sample rows rather
     | than one hero number, because a module is better explained by the work it
     | does than by a statistic a visitor cannot verify.
     */
    'explorer' => [
        'members' => [
            'label' => 'Members',
            'icon' => 'users',
            'headline' => 'Every member, family and department in one record.',
            'columns' => ['Member', 'Department', 'Status'],
            'rows' => [
                ['Grace Adeyemi', 'Choir', 'Active'],
                ['Emeka Nwosu', 'Ushering', 'Active'],
                ['Hannah Bello', 'Youth', 'Active'],
                ['Tunde Salami', 'Media', 'Follow-up'],
            ],
            'footnote' => 'Sample data for illustration.',
        ],
        'finance' => [
            'label' => 'Finance',
            'icon' => 'naira',
            'headline' => 'Income, expenses and budgets with a full audit trail.',
            'columns' => ['Account', 'Type', 'Balance'],
            'rows' => [
                ['Main Offering Account', 'Income', '₦4,820,000'],
                ['Building Project Fund', 'Restricted', '₦6,150,000'],
                ['Welfare Account', 'Expense', '₦1,480,000'],
                ['Missions Support', 'Restricted', '₦2,300,000'],
            ],
            'footnote' => 'Sample data for illustration.',
        ],
        'subvention' => [
            'label' => 'Subvention',
            'icon' => 'receipt',
            'headline' => 'Rule-driven submissions, calculations and approvals.',
            'columns' => ['Period', 'Status', 'Remittance'],
            'rows' => [
                ['September 2026', 'Approved', '₦1,240,000'],
                ['August 2026', 'Approved', '₦1,180,000'],
                ['July 2026', 'Under review', '₦1,095,000'],
                ['June 2026', 'Returned', '₦980,000'],
            ],
            'footnote' => 'Sample data for illustration.',
        ],
        'events' => [
            'label' => 'Events',
            'icon' => 'calendar',
            'headline' => 'Plan programs, take registrations, see who came.',
            'columns' => ['Event', 'Date', 'Capacity'],
            'rows' => [
                ['Sunday Service', '12 Oct', 'Unlimited'],
                ['Youth Convention', '19 Oct', '500'],
                ['Leaders Retreat', '02 Nov', '120'],
                ['Harvest Thanksgiving', '16 Nov', 'Unlimited'],
            ],
            'footnote' => 'Sample data for illustration.',
        ],
        'attendance' => [
            'label' => 'Attendance',
            'icon' => 'check-in',
            'headline' => 'Know who attended, and who to follow up with.',
            'columns' => ['Service', 'Attendance', 'Trend'],
            'rows' => [
                ['Sunday 1st service', '842', '+6.4%'],
                ['Midweek Bible study', '410', '+2.1%'],
                ['Youth service', '286', '-1.8%'],
                ['Prayer meeting', '192', '+4.7%'],
            ],
            'footnote' => 'Sample data for illustration.',
        ],
        'communication' => [
            'label' => 'Communication',
            'icon' => 'mail',
            'headline' => 'Email included. Bulk SMS when you need it.',
            'columns' => ['Channel', 'Audience', 'Status'],
            'rows' => [
                ['Email', 'All members (1,284)', 'Delivered'],
                ['Email', 'Department leaders (46)', 'Delivered'],
                ['SMS', 'Ushering team (38)', 'Queued'],
                ['SMS', 'All members (1,284)', 'Needs credit'],
            ],
            'footnote' => 'Sample data for illustration.',
        ],
        'pastoral' => [
            'label' => 'Pastoral Care',
            'icon' => 'heart',
            'headline' => 'Prayer requests, counselling and welfare follow-ups.',
            'columns' => ['Request', 'Assigned to', 'Status'],
            'rows' => [
                ['Prayer request', 'Pastor John', 'Open'],
                ['Counselling session', 'Rev. Mary', 'Scheduled'],
                ['Welfare support', 'Deacon Board', 'In progress'],
                ['Follow-up visit', 'Pastor John', 'Closed'],
            ],
            'footnote' => 'Sample data for illustration.',
        ],
        'reports' => [
            'label' => 'Reports',
            'icon' => 'pie',
            'headline' => 'Growth, giving and branch performance at a glance.',
            'columns' => ['Report', 'Period', 'Status'],
            'rows' => [
                ['Membership growth', 'September', 'Ready'],
                ['Income vs expenses', 'Q3 2026', 'Ready'],
                ['Attendance by service', 'September', 'Ready'],
                ['Subvention summary', 'Q3 2026', 'Ready'],
            ],
            'footnote' => 'Sample data for illustration.',
        ],
    ],

    /*
     | Plans.
     |
     | `price` is null on every plan on purpose. The brief forbids shipping
     | Plans. Prices come from config('billing.plans') — see the lengthy note
     | there on why there is exactly one place a price is written down. The
     | `price` key below is NOT an independent price; it is resolved from the
     | billing config by matching `key` to the billing plan's slug, so the
     | marketing page and the checkout page can never disagree about what a
     | plan costs. Only the display strings live here.
     */
    'plans' => [
        [
            'key' => 'starter',
            'name' => 'Starter',
            'tagline' => 'For small churches finding their feet.',
            'period' => 'per month',
            'popular' => false,
            'cta' => 'Get Started',
            'features' => [
                'Members, families and groups',
                'Attendance and event tracking',
                'Income and expense records',
                'Email notifications included',
                'Standard support',
            ],
        ],
        [
            'key' => 'growth',
            'name' => 'Growth',
            'tagline' => 'For growing churches and multi-department ministries.',
            'period' => 'per month',
            'popular' => true,
            'cta' => 'Get Started',
            'features' => [
                'Everything in Starter',
                'Finance, budgets and loans',
                'Subvention with approval workflow',
                'Pastoral care and follow-ups',
                'Reports and analytics',
                'Priority support',
            ],
        ],
        [
            'key' => 'denomination',
            'name' => 'Denomination',
            'tagline' => 'For multi-branch churches and governing bodies.',
            'period' => 'per month',
            'popular' => false,
            'cta' => 'Get Started',
            'features' => [
                'Everything in Growth',
                'Multiple branches and departments',
                'Consolidated reporting',
                'Role and permission management',
                'Bulk SMS wallet for the whole group',
                'Dedicated onboarding',
            ],
        ],
        [
            'key' => 'enterprise',
            'name' => 'Enterprise',
            'tagline' => 'For large organisations with bespoke requirements.',
            'period' => 'quoted individually',
            'popular' => false,
            'cta' => 'Talk to Us',
            'features' => [
                'Everything in Denomination',
                'Unlimited branches and records',
                'Custom modules and integrations',
                'Data migration assistance',
                'Custom service agreement',
                'Account manager',
            ],
        ],
    ],

    /*
     | Billing rules that the pricing section must communicate. Stated once here
     | because they appear in three sections and must never disagree.
     */
    'billing_notes' => [
        'Email notifications are included in every plan at no extra cost.',
        'Bulk SMS is billed separately and pay-as-you-go, from a prepaid wallet.',
        'Every plan starts with a 14-day free trial — no card required.',
        'Change, upgrade or cancel your plan at any time.',
    ],

    /*
     | Legal review state.
     |
     | While false, the privacy policy and terms render a visible "draft, not yet
     | legally reviewed" notice. Publishing an unreviewed policy as though it were
     | final is real legal exposure, so the default is the honest one: unverified
     | is shown as unverified.
     |
     | Flip to true only once a qualified professional has actually reviewed both
     | documents for the jurisdictions you operate in.
     */
    'legal_reviewed' => false,

    /*
     | Testimonials. PLACEHOLDER — these are illustrative and must be replaced with
     | real, permissioned quotes before launch. Fabricating named endorsements from
     | real-sounding churches is a legal and reputational risk, so `verified` is
     | false on all of them and the section renders a visible "illustrative" note
     | while that is the case.
     */
    'testimonials_verified' => false,

    'testimonials' => [
        [
            'quote' => 'Our records used to live in three registers and two laptops. Now everything is in one place, and I can see the whole church in one view.',
            'name' => 'Placeholder Quote 1',
            'role' => 'Replace before launch',
            'initials' => 'P1',
        ],
        [
            'quote' => 'The subvention workflow replaced a spreadsheet that took a week to reconcile. Approvals are now traceable from submission to remittance.',
            'name' => 'Placeholder Quote 2',
            'role' => 'Replace before launch',
            'initials' => 'P2',
        ],
        [
            'quote' => 'Attendance follow-up is what changed for us. We can see who has stopped coming and reach them the same week.',
            'name' => 'Placeholder Quote 3',
            'role' => 'Replace before launch',
            'initials' => 'P3',
        ],
    ],

    /*
     | FAQ. Answers the questions the pricing rules raise, which is the cheapest
     | way to move a visitor from "interesting" to "ready to pay".
     */
    'faqs' => [
        [
            'q' => 'When is my church account created?',
            'a' => 'After your payment has been verified. You choose a plan and pay first, we confirm the payment against our provider, and only then is your church workspace provisioned. This is why you will not see a dashboard until payment completes.',
        ],
        [
            'q' => 'Is email included in the price?',
            'a' => 'Yes. Email notifications are included in every plan at no additional cost, and are not metered separately.',
        ],
        [
            'q' => 'How is bulk SMS charged?',
            'a' => 'Separately, and pay-as-you-go. You top up a prepaid SMS wallet from your dashboard and credit is consumed as you send. It is not part of the subscription fee, so a church that does not send bulk SMS never pays for it.',
        ],
        [
            'q' => 'Which payment methods can we use?',
            'a' => 'Card, bank transfer and USSD through our payment providers. Every payment is verified server-side against the provider before your account is activated, so a client-side "payment successful" screen is never treated as proof of payment.',
        ],
        [
            'q' => 'What happens to our data if we cancel?',
            'a' => 'Your plan can be changed or cancelled at any time. We will make an export of your records available so you are not locked in.',
        ],
    ],
];

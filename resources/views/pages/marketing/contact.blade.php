{{--
 | /contact
 |
 | The contact details in config('marketing.contact') are all null, because the
 | brief forbids inventing final contact data and I have no real address, phone
 | number or inbox. Rather than render a dead mailto: to an address nobody owns,
 | the page detects the unconfigured state and says so, and offers the route that
 | DOES work — the registration journey.
 |
 | A contact form is also deliberately absent. There is no controller, no mail
 | transport configured for this, and no notification recipient; a form that
 | accepts a message and silently drops it is worse than no form, because the
 | sender believes they have reached someone.
--}}

@php
    $contact = config('marketing.contact');
    $configured = collect($contact)->filter()->isNotEmpty();

    $reasons = [
        [
            'icon' => 'rocket',
            'title' => 'Getting started',
            'text' => 'Choosing in from a spreadsheet.',
            'route' => 'pricing',
        ],
        [
            'icon' => 'branches',
            'title' => 'Multi-branch',
            'text' => 'Denominations and churches with several locations.',
            'route' => 'features',
        ],
        [
            'icon' => 'naira',
            'title' => 'Finance and subvention',
            'text' => 'Remittance rules, approvals and fund structure.',
            'route' => 'features',
        ],
        [
            'icon' => 'doc',
            'title' => 'Data migration',
            'text' => 'Bringing existing member and finance records across.',
            'route' => 'features',
        ],
    ];
@endphp

<x-marketing-layout title="Contact — ChurchFlow"
    description="Talk to the ChurchFlow team about your church, your denomination or a multi-branch rollout.">
    <x-marketing.page-header eyebrow="Contact" title="Talk to us about" accent="your church."
        lede="Whether you are a single congregation or a denomination with branches, we will walk through how ChurchFlow fits the way you already run." />

    <section class="mk-section" id="contact-options">
        <div class="mk-shell">
            <div class="mk-contact">
                <div class="mk-contact__main" data-reveal>
                    @if ($configured)
                        {{-- Rendered once real details are set in config/marketing.php --}}
                        <h2 class="mk-h3">Reach the team</h2>
                        <dl class="mk-contact__details">
                            @if ($contact['email'])
                                <div>
                                    <dt>General enquiries</dt>
                                    <dd><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></dd>
                                </div>
                            @endif
                            @if ($contact['support_email'])
                                <div>
                                    <dt>Support</dt>
                                    <dd><a
                                            href="mailto:{{ $contact['support_email'] }}">{{ $contact['support_email'] }}</a>
                                    </dd>
                                </div>
                            @endif
                            @if ($contact['phone'])
                                <div>
                                    <dt>Phone</dt>
                                    <dd><a
                                            href="tel:{{ preg_replace('/\s+/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a>
                                    </dd>
                                </div>
                            @endif
                            @if ($contact['address'])
                                <div>
                                    <dt>Address</dt>
                                    <dd>{{ $contact['address'] }}</dd>
                                </div>
                            @endif
                        </dl>
                    @else
                        <div class="mk-alert mk-alert--info">
                            <x-ui.icon name="clock" class="h-4 w-4" />
                            <div>
                                <strong>Contact channels are not configured yet.</strong>
                                <p class="mk-small" style="margin-top: 0.35rem">
                                    We are not going to print an email address or phone number that
                                    nobody monitors. Set the values in
                                    <code class="mk-code">config/marketing.php</code> under
                                    <code class="mk-code">contact</code> and this page will show them
                                    instead of this notice.
                                </p>
                            </div>
                        </div>

                        <h2 class="mk-h3" style="margin-top: 1.75rem">In the meantime</h2>
                        <p class="mk-body" style="margin-top: 0.6rem">
                            The fastest route is the one that already works: choose a plan and go
                            through registration. Your church workspace is created once payment is
                            verified, and you will be guided through setup from there.
                        </p>

                        <div class="mk-contact__actions">
                            <a href="{{ route('pricing') }}" class="mk-btn mk-btn--primary mk-btn--lg">
                                Choose a plan
                                <x-ui.icon name="arrow-right" class="h-4 w-4" />
                            </a>
                            <a href="{{ route('resources.help-center') }}" class="mk-btn mk-btn--secondary mk-btn--lg">
                                Read the Help Center
                            </a>
                        </div>
                    @endif
                </div>

                <div class="mk-contact__side" data-reveal="scale" style="--reveal-delay: 100ms">
                    <p class="mk-contact__sidehead">What people usually ask about</p>
                    <ul class="mk-reasons">
                        @foreach ($reasons as $reason)
                            <li>
                                <span class="mk-icon mk-icon--sm mk-tone--{{ $loop->even ? 'green' : 'blue' }}">
                                    <x-ui.icon :name="$reason['icon']" class="h-[1.1rem] w-[1.1rem]" />
                                </span>
                                <div>
                                    <p class="mk-reason__title">{{ $reason['title'] }}</p>
                                    <p class="mk-reason__text">{{ $reason['text'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <a href="{{ route('support') }}" class="mk-link mk-contact__support">
                        Support for existing churches
                        <x-ui.icon name="arrow-right" class="h-4 w-4" />
                    </a>
                </div>
            </div>
        </div>
    </section>

    <x-marketing.faq />

    <x-marketing.final-cta />
</x-marketing-layout>

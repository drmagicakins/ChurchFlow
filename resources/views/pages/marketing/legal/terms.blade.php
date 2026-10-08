{{--
 | /legal/terms
 |
 | Terms of service, written to describe the actual commercial arrangement rather
 | than as generic boilerplate. The billing rules stated here are the same ones the
 | pricing page and FAQ state — that consistency is the point.
 |
 | Same draft notice as the privacy policy, for the same reason: unreviewed terms
 | presented as final is a real exposure.
--}}

@php
    $reviewed = config('marketing.legal_reviewed', false);
@endphp

<x-marketing-layout title="Terms of Service — ChurchFlow"
    description="The terms governing use of the ChurchFlow church management platform.">
    <x-marketing.page-header eyebrow="Legal" title="Terms of Service"
        lede="The agreement between your church and ChurchFlow." />

    <section class="mk-section">
        <div class="mk-shell">
            <div class="mk-legal">
                @unless ($reviewed)
                    <div class="mk-alert mk-alert--warn">
                        <x-ui.icon name="shield" class="h-4 w-4" />
                        <div>
                            <strong>Draft — not yet legally reviewed.</strong>
                            <p class="mk-small" style="margin-top: 0.35rem">
                                These terms reflect how the service actually works, but they have not
                                been reviewed by a qualified legal professional. Do not treat them as
                                final until that review is complete.
                            </p>
                        </div>
                    </div>
                @endunless

                <article class="mk-legal__body">
                    <p class="mk-legal__updated">Last updated: {{ date('F Y') }}</p>

                    <h2>1. The service</h2>
                    <p>
                        ChurchFlow is a hosted church management platform. Your church subscribes to
                        it, and your authorised users access it through a web browser. The platform
                        is provided as a service; no software is installed on your own systems.
                    </p>

                    <h2>2. Your account and your church's workspace</h2>
                    <p>
                        An account is created for the individual who registers. Your church's
                        workspace — the tenant that holds your church's records — is created only
                        after your subscription payment has been verified against our payment
                        provider. A browser confirmation that a payment was successful is not, by
                        itself, treated as proof of payment.
                    </p>

                    <h2>3. Subscription and payment</h2>
                    <ul>
                        <li>
                            Plans are billed in advance on the cycle shown at the time of purchase.
                        </li>
                        <li>
                            Email notifications are included in every plan at no additional cost.
                        </li>
                        <li>
                            Bulk SMS is not included in the subscription. It is charged separately
                            and pay-as-you-go against a prepaid wallet, and credit is consumed as
                            messages are sent.
                        </li>
                        <li>
                            Payments are processed by third-party providers. We do not store full
                            card numbers.
                        </li>
                    </ul>

                    <h2>4. Changes and cancellation</h2>
                    <p>
                        You may change or cancel your plan at any time. On cancellation, access
                        continues to the end of the paid period, after which your workspace is
                        deactivated. We will make an export of your records available so that you
                        are not locked in.
                    </p>

                    <h2>5. Your responsibilities</h2>
                    <ul>
                        <li>
                            Your church is responsible for the accuracy and lawfulness of the
                            information it records about its members, and for having the basis on
                            which it holds that information.
                        </li>
                        <li>
                            You are responsible for who you grant access to within your church, and
                            for the roles and permissions you assign.
                        </li>
                        <li>
                            You are responsible for keeping administrator credentials secure.
                        </li>
                        <li>
                            You must not use the messaging features to send unsolicited messages to
                            people who have not consented to receive them.
                        </li>
                    </ul>

                    <h2>6. Acceptable use</h2>
                    <p>
                        The platform may not be used to break the law, to store unlawful material,
                        to attempt to access another church's data, or to interfere with the
                        service or with other customers' use of it.
                    </p>

                    <h2>7. Availability</h2>
                    <p>
                        We aim to keep the platform available and to give advance notice of planned
                        maintenance. We do not currently offer a contractual uptime guarantee
                        through this page; if your church requires one, it must be agreed separately
                        in writing.
                    </p>

                    <h2>8. Data</h2>
                    <p>
                        Your church's records remain your church's data. Our handling of that data
                        is described in the Privacy Policy. Financial entries are reversed by voiding
                        rather than deleted, so that your church's accounts remain auditable.
                    </p>

                    <h2>9. Ending the agreement</h2>
                    <p>
                        You may end this agreement at any time as described in section 4. We may
                        suspend an account that is being used in breach of section 6, and will tell
                        the account administrator if we do.
                    </p>

                    <h2>10. Changes to these terms</h2>
                    <p>
                        If these terms change in a way that affects your church, we will tell church
                        administrators before the change takes effect.
                    </p>
                </article>
            </div>
        </div>
    </section>
</x-marketing-layout>

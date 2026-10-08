{{--
 | /legal/privacy
 |
 | This is a real, substantive privacy policy describing how the platform actually
 | handles data — not lorem ipsum and not a fabricated legal document.
 |
 | It carries a visible draft notice because it has not been reviewed by a lawyer.
 | Publishing an unreviewed privacy policy as though it were final is a genuine
 | legal exposure, and silently shipping one would be the wrong call. The notice
 | is driven by config('marketing.legal_reviewed') so it disappears the moment
 | someone confirms review.
 |
 | The content is grounded in what the codebase actually does: tenant isolation at
 | the query layer, void-not-delete on finance entries, role-based visibility on
 | pastoral records. Claims about data handling that the product does not honour
 | would be worse than no policy at all.
--}}

@php
    $reviewed = config('marketing.legal_reviewed', false);
@endphp

<x-marketing-layout title="Privacy Policy — ChurchFlow"
    description="How ChurchFlow collects, stores, isolates and protects church and member data.">
    <x-marketing.page-header eyebrow="Legal" title="Privacy Policy"
        lede="How ChurchFlow handles the information your church puts into it." />

    <section class="mk-section">
        <div class="mk-shell">
            <div class="mk-legal">
                @unless ($reviewed)
                    <div class="mk-alert mk-alert--warn">
                        <x-ui.icon name="shield" class="h-4 w-4" />
                        <div>
                            <strong>Draft — not yet legally reviewed.</strong>
                            <p class="mk-small" style="margin-top: 0.35rem">
                                This policy describes the platform's actual behaviour accurately, but it
                                has not been reviewed by a qualified legal professional in the
                                jurisdictions ChurchFlow operates in. Do not treat it as final until that
                                review is complete.
                            </p>
                        </div>
                    </div>
                @endunless

                <article class="mk-legal__body">
                    <p class="mk-legal__updated">Last updated: {{ date('F Y') }}</p>

                    <h2>1. Who this policy is for</h2>
                    <p>
                        ChurchFlow is a church management platform. Our customers are churches,
                        ministries and denominational bodies ("your church"). When your church uses
                        ChurchFlow, your church decides what information is recorded about its members,
                        and ChurchFlow processes that information on your church's behalf.
                    </p>
                    <p>
                        This policy explains what we collect, why, how it is protected, and who can
                        see it.
                    </p>

                    <h2>2. Information we hold</h2>
                    <p>There are two categories, and they are treated differently.</p>

                    <h3>Information about your church's administrators</h3>
                    <p>
                        When someone signs in to ChurchFlow, we hold their name, email address,
                        password (stored only as a cryptographic hash, never in readable form), role
                        and sign-in history.
                    </p>

                    <h3>Information your church records about its members</h3>
                    <p>
                        Your church may record member names, contact details, family relationships,
                        department membership, attendance, giving and pastoral notes. This is your
                        church's data. We do not use it for our own purposes, and we do not sell it.
                    </p>

                    <h2>3. How your church's data is separated</h2>
                    <p>
                        Each church's records are isolated at the database query layer. Records are
                        scoped to the requesting church before they are returned, so one church cannot
                        read another church's data even if it knows a record identifier. This is not
                        a display-level filter; it is enforced on the query itself.
                    </p>

                    <h2>4. Who can see what inside your church</h2>
                    <p>
                        Access is controlled by role and permission. Finance records and pastoral
                        records are the most restricted. A user without permission to view a finance
                        panel does not receive that panel in the response at all — the data is not
                        sent and then hidden.
                    </p>

                    <h2>5. Payments</h2>
                    <p>
                        Subscription payments are processed by third-party payment providers. We
                        confirm each transaction against the provider server-side before activating a
                        church workspace. We do not store full card numbers on our systems.
                    </p>

                    <h2>6. Email and SMS</h2>
                    <p>
                        Messages sent through ChurchFlow are delivered through third-party email and
                        SMS providers. Message content and recipient addresses are shared with those
                        providers to the extent required to deliver the message.
                    </p>

                    <h2>7. Financial records</h2>
                    <p>
                        Financial entries are not deleted once recorded. A mistaken entry is voided,
                        which reverses it while preserving the record that it existed and who reversed
                        it. This exists so that a church's accounts remain auditable.
                    </p>

                    <h2>8. Retention and export</h2>
                    <p>
                        Your church's data is retained while your subscription is active. On
                        cancellation we make an export of your records available so you are not locked
                        in. You may also export your member list at any time from within the platform.
                    </p>

                    <h2>9. Your rights</h2>
                    <p>
                        Members with questions about information their church has recorded about them
                        should contact their church directly, since their church is the controller of
                        that information. Church administrators may contact us about the data their
                        church holds.
                    </p>

                    <h2>10. Changes</h2>
                    <p>
                        If this policy changes in a way that affects how your church's data is
                        handled, we will tell church administrators before the change takes effect.
                    </p>
                </article>
            </div>
        </div>
    </section>
</x-marketing-layout>

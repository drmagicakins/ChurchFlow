{{--
 | Final CTA.
 |
 | The last thing on the page before the footer, so it carries the strongest
 | contrast on the page: a navy gradient band with a white button. The supporting
 | line restates the commercial model rather than repeating a slogan, because this
 | is the point at which a visitor decides whether to click.
 |
 | It also offers a second, softer path (talk to a human) for the visitor who is
 | not ready to pay — a single hard CTA at the end of a page loses everyone who
 | has a question, and those are often the larger churches.
--}}

<section class="mk-section mk-section--tight" id="get-started">
    <div class="mk-shell">
        <div class="mk-gradient mk-ctaband" data-reveal="scale">
            <div class="mk-ctaband__glow" aria-hidden="true"></div>

            <div class="mk-ctaband__body">
                <p class="mk-eyebrow mk-ctaband__eyebrow">Get started</p>
                <h2 class="mk-ctaband__title">Join ChurchFlow today</h2>
                <p class="mk-ctaband__text">
                    Manage. Connect. Grow. Bring your people, finances, activities and
                    communication into one platform your whole team can use.
                </p>

                <div class="mk-ctaband__actions">
                    <a href="{{ route('register') }}" class="mk-btn mk-btn--white mk-btn--lg">
                        Get Started Now
                        <x-ui.icon name="arrow-right" class="h-4 w-4" />
                    </a>
                    <a href="{{ route('contact') }}" class="mk-btn mk-btn--on-dark mk-btn--lg">
                        Talk to us first
                    </a>
                </div>

                <ul class="mk-ctaband__points">
                    <li><x-ui.icon name="check" class="h-3.5 w-3.5" /> Email included in every plan</li>
                    <li><x-ui.icon name="check" class="h-3.5 w-3.5" /> Change or cancel anytime</li>
                    <li><x-ui.icon name="check" class="h-3.5 w-3.5" /> Your church, created after verified payment</li>
                </ul>
            </div>
        </div>
    </div>
</section>

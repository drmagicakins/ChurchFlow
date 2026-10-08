{{--
 | "How It Works" — the payment journey.
 |
 | The brief is emphatic (section 12) that ChurchFlow must NOT be presented as
 | register -> free church account -> dashboard -> pay later. The steps below are
 | therefore explicit that payment is verified BEFORE a church tenant exists, and
 | that a client-side "payment successful" screen is not proof of payment. This
 | is a product claim, not marketing copy, so it is stated plainly.
 |
 | Five steps rather than four: the reference had four, but it collapsed
 | "verification" into "make payment", and verification is the part that
 | distinguishes this product from one that activates on a browser callback. It
 | is also the part a finance-minded decision maker actually cares about.
--}}

@php
    $steps = config('marketing.steps');
@endphp

<section class="mk-section" id="how-it-works">
    <div class="mk-shell">
        <div class="mk-head mk-head--center" data-reveal>
            <p class="mk-eyebrow" style="justify-content: center">Get Started in 5 Easy Steps</p>
            <h2 class="mk-h2" style="margin-top: 0.85rem">How It Works</h2>
            <p class="mk-lede" style="margin-top: 1rem">
                Get your church up and running in minutes. Our simple process makes it easy to start
                using ChurchFlow — and your church workspace is created only once your payment is verified.
            </p>
        </div>

        <ol class="mk-steps">
            @foreach ($steps as $i => $step)
                <li class="mk-step" data-reveal style="--reveal-delay: {{ $i * 80 }}ms">
                    <div class="mk-step__top">
                        <span class="mk-step__num mk-num">{{ $step['n'] }}</span>
                        <span class="mk-step__icon">
                            <x-ui.icon :name="$step['icon']" class="h-[1.05rem] w-[1.05rem]" />
                        </span>
                    </div>
                    <h3 class="mk-step__title">{{ $step['title'] }}</h3>
                    <p class="mk-step__text">{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>

        <div class="mk-steps__foot" data-reveal>
            <x-ui.icon name="lock" class="h-4 w-4" />
            <p class="mk-small">
                A client-side "payment successful" screen is never treated as proof of payment.
                We confirm every transaction against the provider first.
            </p>
        </div>
    </div>
</section>

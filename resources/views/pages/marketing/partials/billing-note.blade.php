{{--
 | Shared billing-rules note.
 |
 | Used by both the home page's pricing section and the standalone pricing page.
 | Extracted so the commercial terms — email included, SMS pay-as-you-go, payment
 | verified before tenant creation — are stated in exactly one place. These are
 | claims about how money is handled, and two copies of them WILL drift.
--}}

@php
    $billingNotes = config('marketing.billing_notes');
    $billingJourney = ['Choose plan', 'Pay', 'We verify', 'Church created', 'Dashboard'];
@endphp

<div class="mk-billingnote" data-reveal>
    <div class="mk-billingnote__col">
        <p class="mk-billingnote__head">
            <x-ui.icon name="wallet" class="h-4 w-4" />
            How billing works
        </p>
        <ul class="mk-checks mk-billingnote__list">
            @foreach ($billingNotes as $note)
                <li><span>{{ $note }}</span></li>
            @endforeach
        </ul>
    </div>

    <div class="mk-billingnote__col">
        <p class="mk-billingnote__head">
            <x-ui.icon name="route" class="h-4 w-4" />
            What happens after you choose
        </p>
        <ol class="mk-billingnote__chain">
            @foreach ($billingJourney as $stage)
                <li>
                    <span class="mk-billingnote__dot mk-num">{{ $loop->iteration }}</span>
                    {{ $stage }}
                </li>
            @endforeach
        </ol>
        <p class="mk-billingnote__fine">
            A client-side &ldquo;payment successful&rdquo; screen is never treated as proof of
            payment — every transaction is confirmed server-side first.
        </p>
    </div>
</div>

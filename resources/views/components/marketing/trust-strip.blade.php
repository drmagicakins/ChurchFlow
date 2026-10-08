{{--
 | Trust strip.
 |
 | Sits directly under the hero and answers the question a visitor has before
 | they will read anything else: is this a real product or a landing page?
 |
 | It is honest about what it can currently claim. There are no customer logos
 | and no subscriber counts, because we do not have permissioned logos or a
 | verified count — inventing either would be a fabricated claim about real
 | organisations. What it shows instead are the four things that are true by
 | construction: data isolation, verified payments, email included, and the
 | module coverage that is actually built.
 |
 | Replace with real logos/counts once they exist; the commented block below
 | shows where they go.
--}}

@php
    $markers = [
        [
            'icon' => 'lock',
            'title' => 'Tenant-isolated',
            'text' => 'Each church\'s data is separated at the query layer.',
        ],
        [
            'icon' => 'shield',
            'title' => 'Verified payments',
            'text' => 'Server-side confirmation, never a client callback.',
        ],
        ['icon' => 'mail', 'title' => 'Email included', 'text' => 'Not metered, not an add-on. In every plan.'],
        ['icon' => 'chat', 'title' => 'SMS when you need it', 'text' => 'Pay-as-you-go from a prepaid wallet.'],
    ];
@endphp

<section class="mk-trust" aria-label="Platform guarantees">
    <div class="mk-shell">
        <div class="mk-trust__inner" data-reveal="fade">
            @foreach ($markers as $marker)
                <div class="mk-trust__item">
                    <span class="mk-trust__icon">
                        <x-ui.icon :name="$marker['icon']" class="h-[1.05rem] w-[1.05rem]" />
                    </span>
                    <div>
                        <p class="mk-trust__title">{{ $marker['title'] }}</p>
                        <p class="mk-trust__text">{{ $marker['text'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

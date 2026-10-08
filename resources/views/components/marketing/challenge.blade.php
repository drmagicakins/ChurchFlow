{{--
 | "Problems Churches Face" — the Challenge section.
 |
 | Previously this rendered six bare titles with one generic icon reused across
 | all of them. It now pairs each problem with its concrete symptom, because a
 | church administrator recognises "offerings reconciled by hand every month" as
 | their life and does not necessarily recognise "manual financial tracking" as
 | anything at all.
 |
 | The framed panel is deliberate: six items floating on white read as an
 | unfinished list, whereas one bordered surface reads as a finished component.
 | It also gives the section a different visual weight from the two that surround
 | it, so the page does not read as the same left-text/right-grid block repeated.
--}}

@php
    $problems = config('marketing.problems');
@endphp

<section class="mk-section" id="challenge">
    <div class="mk-shell">
        <div class="mk-challenge">
            <div class="mk-head" data-reveal>
                <p class="mk-eyebrow">The Challenge</p>
                <h2 class="mk-h2" style="margin-top: 0.85rem">Problems Churches Face</h2>
                <p class="mk-lede" style="margin-top: 1rem">
                    Managing a church is rewarding, but it comes with real challenges. From scattered
                    records to inefficient processes, many churches struggle with administration,
                    finance, communication and more.
                </p>

                <div class="mk-challenge__note">
                    <x-ui.icon name="shield" class="h-4 w-4" />
                    <p class="mk-small">
                        Every problem below is one ChurchFlow was built to remove — not worked around.
                    </p>
                </div>
            </div>

            <ul class="mk-challenge__grid" data-reveal="scale" style="--reveal-delay: 100ms">
                @foreach ($problems as $i => $problem)
                    <li class="mk-problem">
                        <span class="mk-icon mk-icon--sm mk-tone--{{ $i % 2 === 0 ? 'blue' : 'green' }}">
                            <x-ui.icon :name="$problem['icon']" class="h-[1.15rem] w-[1.15rem]" />
                        </span>
                        <div class="mk-problem__text">
                            <p class="mk-problem__title">{{ $problem['title'] }}</p>
                            <p class="mk-problem__body">{{ $problem['text'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>

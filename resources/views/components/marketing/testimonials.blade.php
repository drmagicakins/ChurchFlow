{{--
 | Testimonials.
 |
 | The quotes are placeholders and the names say so ("Placeholder Quote 1"), with
 | a visible notice above them while config('marketing.testimonials_verified') is
 | false. This is not fussiness: the previous version attached invented quotes to
 | named, real-sounding churches ("RCCG, Lagos Province"), which is a fabricated
 | endorsement of a specific real organisation. Rendering the placeholder state
 | honestly is the only safe option until real, permissioned quotes exist.
 |
 | Set testimonials_verified to true in config once the real quotes are in and
 | the notice disappears on its own.
--}}

@php
    $testimonials = config('marketing.testimonials');
    $verified = config('marketing.testimonials_verified');
@endphp

<section class="mk-section" id="testimonials">
    <div class="mk-shell">
        <div class="mk-head mk-head--center" data-reveal>
            <p class="mk-eyebrow" style="justify-content: center">Testimonials</p>
            <h2 class="mk-h2" style="margin-top: 0.85rem">What Church Leaders Say</h2>
            <p class="mk-lede" style="margin-top: 1rem">
                Built with church administrators, for church administrators — around the work that
                actually fills a week.
            </p>
        </div>

        @unless ($verified)
            <div class="mk-testimonials__notice" data-reveal>
                <x-ui.icon name="clock" class="h-4 w-4" />
                <p class="mk-small">
                    These quotes are <strong>illustrative placeholders</strong>, not customer
                    testimonials. They will be replaced with verified, permissioned quotes before
                    launch.
                </p>
            </div>
        @endunless

        <div class="mk-testimonials">
            @foreach ($testimonials as $i => $testimonial)
                <figure
                    class="mk-card mk-card--pad mk-testimonial"
                    data-reveal
                    style="--reveal-delay: {{ $i * 80 }}ms"
                >
                    <div class="mk-testimonial__stars" aria-hidden="true">
                        @for ($s = 0; $s < 5; $s++)
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 15l-5.2 2.6 1-5.8L1.5 7.7l5.9-.9L10 1.5Z"/></svg>
                        @endfor
                    </div>

                    <blockquote class="mk-testimonial__quote">
                        &ldquo;{{ $testimonial['quote'] }}&rdquo;
                    </blockquote>

                    <figcaption class="mk-testimonial__foot">
                        <span class="mk-testimonial__avatar" aria-hidden="true">
                            {{ $testimonial['initials'] }}
                        </span>
                        <div>
                            <p class="mk-testimonial__name">{{ $testimonial['name'] }}</p>
                            <p class="mk-testimonial__role">{{ $testimonial['role'] }}</p>
                        </div>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>

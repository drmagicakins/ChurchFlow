{{--
 | The PUBLIC page's answer for an event that has already taken place.
 |
 | Why this view exists at all: the controller used to answer with `abort(410)`.
 | The status was right and the message was even attached to it, but Laravel
 | renders a 4xx carrying a message through its exception view — and with
 | APP_DEBUG on (the development default) that is the full debug screen: "410
 | Gone", the exception class, a stack trace, the incoming headers and
 | "Body // No request body". A visitor who clicked a shared link learned
 | nothing from it.
 |
 | So the refusal is drawn on the church's own guest page instead — the same
 | `cf-*` design system the live registration form uses — while the response
 | still goes out as 410 Gone.
 |
 | Nothing is collected here and nothing can be submitted: this page carries no
 | form, because the point of it is that the event is over.
--}}
<x-layouts.auth :title="$event->title . ' · Registration closed'" :heading="$event->title" :subheading="$church?->name ? 'Registration · ' . $church->name : 'Event registration'">

    <div class="cf-alert cf-alert--warn" role="status">
        <span class="cf-alert__icon"><x-ui.icon name="clock" class="h-4 w-4" /></span>
        <div class="cf-alert__body">
            <p class="cf-alert__title">{{ $message }}</p>
            <p class="cf-small" style="margin:.3rem 0 0">
                Nobody can be registered for an event that has already happened — the list of who came
                has been kept as the record for that day.
            </p>
        </div>
    </div>

    <div class="cf-card" style="display:grid;gap:1rem;margin-top:1.2rem">
        <dl style="display:grid;grid-template-columns:auto 1fr;gap:.5rem 1rem;margin:0">
            <dt class="cf-label" style="margin:0">When</dt>
            <dd class="cf-small" style="margin:0">
                {{ $event->starts_at?->format('l, j M Y · g:i A') }}
                @if ($event->ends_at)
                    <br><span class="cf-muted">until {{ $event->ends_at->format('l, j M Y · g:i A') }}</span>
                @endif
            </dd>

            @if ($event->venue)
                <dt class="cf-label" style="margin:0">Where</dt>
                <dd class="cf-small" style="margin:0">{{ $event->venue }}</dd>
            @endif
        </dl>

        @if ($church)
            <p class="cf-small cf-muted" style="margin:0">
                If you think this is a mistake, please contact {{ $church->name }} directly.
            </p>
        @endif
    </div>

</x-layouts.auth>

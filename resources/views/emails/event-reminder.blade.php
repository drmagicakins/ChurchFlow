<!doctype html>
<html>

<body>
    <h2>{{ $event->title }}</h2>

    {{--
        $recipientName is set for BOTH kinds of recipient; $registration is only
        present for a visitor from the public form (a church member has no such
        row, and the party-size line below is a visitor-only fact).
    --}}
    <p>Hello {{ $recipientName ?? $registration->name ?? 'there' }},</p>

    <p>This is a reminder that you are registered for <strong>{{ $event->title }}</strong>.</p>

    <ul>
        @if ($when)
            <li><strong>When:</strong> {{ $when }}</li>
        @endif
        @if ($event->venue)
            <li><strong>Where:</strong> {{ $event->venue }}</li>
        @endif
        @if ($registration && $registration->guests > 0)
            <li><strong>Your party:</strong> {{ $registration->headcount() }} people (including you)</li>
        @endif
    </ul>

    @if ($event->description)
        <p>{{ $event->description }}</p>
    @endif

    <p>We look forward to seeing you.</p>

    <p>{{ $event->church?->name ?? 'Your church' }}</p>
</body>

</html>

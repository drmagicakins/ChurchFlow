<!doctype html>
<html>

<body>
    <h2>{{ $event->title }}</h2>

    <p>Hello {{ $registration->name }},</p>

    <p>This is a reminder that you are registered for <strong>{{ $event->title }}</strong>.</p>

    <ul>
        @if ($when)
            <li><strong>When:</strong> {{ $when }}</li>
        @endif
        @if ($event->venue)
            <li><strong>Where:</strong> {{ $event->venue }}</li>
        @endif
        @if ($registration->guests > 0)
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

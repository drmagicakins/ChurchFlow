<x-layout>
    <h1>{{ $event->title }}</h1>
    <p>{{ $event->venue }} — {{ $event->starts_at }}</p>
    <p>Registered: {{ $event->confirmedCount() }} @if($event->capacity) / {{ $event->capacity }} @endif</p>
</x-layout>

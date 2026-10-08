<x-layout>
    <h1>Events</h1>
    <ul>
    @forelse($events as $event)
        <li><a href="{{ route('events.show', $event) }}">{{ $event->title }}</a> — {{ $event->starts_at }}</li>
    @empty
        <li>No events yet. <a href="#">Create Your First Event</a></li>
    @endforelse
    </ul>
</x-layout>

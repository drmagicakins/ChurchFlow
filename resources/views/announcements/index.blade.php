<x-layout>
    <h1>Announcements</h1>
    <ul>
    @forelse($announcements as $a)
        <li><a href="{{ route('announcements.show', $a) }}">{{ $a->title }}</a></li>
    @empty
        <li>No announcements yet.</li>
    @endforelse
    </ul>
</x-layout>

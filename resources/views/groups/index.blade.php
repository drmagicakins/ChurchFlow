<x-layout>
    <h1>{{ ucfirst('groups') }}</h1>
    <ul>
    @forelse($groups as $item)
        <li><a href="{{ route('groups.show', $item) }}">{{ $item->name }}</a></li>
    @empty
        <li>Nothing here yet.</li>
    @endforelse
    </ul>
</x-layout>

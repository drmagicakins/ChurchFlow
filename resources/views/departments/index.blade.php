<x-layout>
    <h1>{{ ucfirst('departments') }}</h1>
    <ul>
    @forelse($departments as $item)
        <li><a href="{{ route('departments.show', $item) }}">{{ $item->name }}</a></li>
    @empty
        <li>Nothing here yet.</li>
    @endforelse
    </ul>
</x-layout>

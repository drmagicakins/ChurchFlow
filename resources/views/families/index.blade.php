<x-layout>
    <h1>{{ ucfirst('families') }}</h1>
    <ul>
    @forelse($families as $item)
        <li><a href="{{ route('families.show', $item) }}">{{ $item->name }}</a></li>
    @empty
        <li>Nothing here yet.</li>
    @endforelse
    </ul>
</x-layout>

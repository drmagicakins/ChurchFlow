<x-layout>
    <h1>Calendar: {{ $from->toDateString() }} – {{ $to->toDateString() }}</h1>
    <ul>
    @forelse($items as $item)
        <li>{{ $item['date'] }} — [{{ $item['type'] }}] {{ $item['title'] }}</li>
    @empty
        <li>Nothing scheduled in this range.</li>
    @endforelse
    </ul>
</x-layout>

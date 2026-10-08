<x-layout>
    <h1>Subvention Periods</h1>
    <ul>
    @forelse($periods as $p)
        <li>{{ $p->name }} ({{ $p->status }})</li>
    @empty
        <li>No periods yet.</li>
    @endforelse
    </ul>
</x-layout>

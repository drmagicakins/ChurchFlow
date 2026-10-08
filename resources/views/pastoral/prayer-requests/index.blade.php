<x-layout>
    <h1>Prayer Requests</h1>
    <ul>
    @forelse($requests as $r)
        <li>{{ $r->submitterName() }}: {{ $r->request }} ({{ $r->status }})</li>
    @empty
        <li>Nothing here yet.</li>
    @endforelse
    </ul>
</x-layout>

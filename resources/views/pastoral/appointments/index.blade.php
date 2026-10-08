<x-layout>
    <h1>Appointments</h1>
    <ul>
    @forelse($appointments as $a)
        <li>{{ $a->title }} — {{ $a->scheduled_at }} ({{ $a->status }})</li>
    @empty
        <li>Nothing scheduled.</li>
    @endforelse
    </ul>
</x-layout>

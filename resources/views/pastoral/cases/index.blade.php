<x-layout>
    <h1>Pastoral Cases</h1>
    <ul>
    @forelse($cases as $c)
        <li><a href="{{ route('pastoral.cases.show', $c) }}">{{ $c->member->full_name }} — {{ $c->type }}</a> ({{ $c->status }})</li>
    @empty
        <li>No cases assigned to you.</li>
    @endforelse
    </ul>
</x-layout>

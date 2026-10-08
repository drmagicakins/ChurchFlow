<x-layout>
    <h1>Subvention Rule Sets</h1>
    <ul>
    @forelse($ruleSets as $rs)
        <li><a href="{{ route('subvention.rule-sets.show', $rs) }}">{{ $rs->name }}</a> ({{ $rs->rules->count() }} rules)</li>
    @empty
        <li>No rule sets configured yet.</li>
    @endforelse
    </ul>
</x-layout>

<x-layout>
    <h1>Budgets</h1>
    <ul>
    @forelse($budgets as $b)
        <li><a href="{{ route('finance.budgets.show', $b) }}">{{ $b->name }}</a></li>
    @empty
        <li>No budgets yet.</li>
    @endforelse
    </ul>
</x-layout>

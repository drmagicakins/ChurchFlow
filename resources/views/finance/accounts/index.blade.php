<x-layout>
    <h1>Financial Accounts</h1>
    <ul>
    @forelse($accounts as $account)
        <li><a href="{{ route('finance.accounts.show', $account) }}">{{ $account->name }}</a> ({{ $account->type }})</li>
    @empty
        <li>No financial accounts yet. <a href="#">Set one up</a></li>
    @endforelse
    </ul>
</x-layout>

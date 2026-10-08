<x-layout>
    <h1>SMS Campaigns</h1>
    <p>Wallet balance: {{ $wallet->balance_units }} credits</p>
    <ul>
    @forelse($campaigns as $c)
        <li><a href="{{ route('sms.campaigns.show', $c) }}">{{ $c->name }}</a> ({{ $c->status }})</li>
    @empty
        <li>No campaigns yet.</li>
    @endforelse
    </ul>
</x-layout>

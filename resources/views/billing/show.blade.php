<x-layout>
    <h1>Billing</h1>

    <h2>Subscription</h2>
    @if($subscription)
        <p>{{ $subscription->plan->name }} — {{ $subscription->status }}</p>
        <p>Next billing date: {{ $subscription->current_period_end->toFormattedDateString() }}</p>
    @endif

    <h2>SMS Wallet</h2>
    <p>{{ $smsWallet->balance_units }} credits. Email notifications are included with your subscription; bulk SMS uses these credits.</p>

    <h2>Recent transactions</h2>
    <ul>
    @forelse($invoices as $invoice)
        <li>{{ $invoice->type }} — {{ $invoice->currency }} {{ $invoice->amount }} ({{ $invoice->status }})</li>
    @empty
        <li>No invoices yet.</li>
    @endforelse
    </ul>
</x-layout>

<x-layout>
    <h1>{{ $church->name }}</h1>
    @if($subscription)
        <p>{{ $subscription->plan->name }} — {{ $subscription->status }}</p>
    @endif
    <h2>Invoices</h2>
    <ul>
    @forelse($invoices as $invoice)
        <li>{{ $invoice->type }} — {{ $invoice->currency }} {{ $invoice->total() }} ({{ $invoice->status }})</li>
    @empty
        <li>No invoices.</li>
    @endforelse
    </ul>
</x-layout>

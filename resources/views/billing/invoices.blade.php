<x-layout>
    <h1>Invoices</h1>
    <ul>
    @forelse($invoices as $invoice)
        <li>#{{ $invoice->id }} {{ $invoice->type }} — {{ $invoice->currency }} {{ $invoice->amount }} — ref {{ $invoice->provider_reference }}</li>
    @empty
        <li>No invoices yet.</li>
    @endforelse
    </ul>
</x-layout>

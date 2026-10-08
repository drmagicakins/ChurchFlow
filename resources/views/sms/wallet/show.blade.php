<x-layout>
    <h1>SMS Wallet</h1>
    <p>Balance: {{ $wallet->balance_units }} credits</p>
    <table>
        <thead><tr><th>Date</th><th>Type</th><th>Units</th><th>Balance After</th><th>Description</th></tr></thead>
        <tbody>
        @forelse($transactions as $t)
            <tr>
                <td>{{ $t->created_at }}</td>
                <td>{{ $t->type }}</td>
                <td>{{ $t->units }}</td>
                <td>{{ $t->balance_after }}</td>
                <td>{{ $t->description }}</td>
            </tr>
        @empty
            <tr><td colspan="5">No SMS transactions yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</x-layout>

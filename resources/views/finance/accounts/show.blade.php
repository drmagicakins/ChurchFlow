<x-layout>
    <h1>{{ $account->name }}</h1>
    <p>Balance: {{ $balance }}</p>
    <table>
        <thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Amount</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($transactions as $t)
            <tr>
                <td>{{ $t->transacted_on->toDateString() }}</td>
                <td>{{ $t->type }}</td>
                <td>{{ $t->category }}</td>
                <td>{{ $t->amount }}</td>
                <td>{{ $t->approval_status }}</td>
            </tr>
        @empty
            <tr><td colspan="5">No financial transactions yet. <a href="#">Record Transaction</a></td></tr>
        @endforelse
        </tbody>
    </table>
</x-layout>

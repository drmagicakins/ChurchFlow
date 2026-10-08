<x-layout>
    <h1>Platform Billing Overview</h1>

    <h2>Revenue by type</h2>
    <ul>
    @forelse($revenueByType as $type => $total)
        <li>{{ $type }}: {{ $total }}</li>
    @empty
        <li>No paid invoices yet.</li>
    @endforelse
    </ul>

    <h2>Subscriptions by status</h2>
    <ul>
    @foreach($subscriptionsByStatus as $status => $count)
        <li>{{ $status }}: {{ $count }}</li>
    @endforeach
    </ul>

    <h2>Churches</h2>
    <table>
        <thead><tr><th>Church</th><th>Status</th><th>Branches</th><th></th></tr></thead>
        <tbody>
        @foreach($churches as $church)
            <tr>
                <td><a href="{{ route('platform-admin.billing.church', $church) }}">{{ $church->name }}</a></td>
                <td>{{ $church->status }}</td>
                <td>{{ $church->organizational_units_count }}</td>
                <td>
                    <form method="POST" action="{{ route('platform-admin.billing.suspend', $church) }}" style="display:inline">@csrf<button>Suspend</button></form>
                    <form method="POST" action="{{ route('platform-admin.billing.reactivate', $church) }}" style="display:inline">@csrf<button>Reactivate</button></form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</x-layout>

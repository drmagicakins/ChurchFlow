<x-layout>
    <h1>Pending Approvals</h1>
    <ul>
    @forelse($approvals as $a)
        <li>
            {{ class_basename($a->approvable_type) }} #{{ $a->approvable_id }}
            <form method="POST" action="{{ route('approvals.approve', $a) }}" style="display:inline">@csrf<button>Approve</button></form>
            <form method="POST" action="{{ route('approvals.reject', $a) }}" style="display:inline">@csrf<button>Reject</button></form>
        </li>
    @empty
        <li>Nothing pending approval.</li>
    @endforelse
    </ul>
</x-layout>

<x-layout>
    <h1>Notifications</h1>
    <ul>
    @forelse($items as $n)
        <li>
            <strong>{{ $n->title }}</strong> — {{ $n->body }}
            @unless($n->read_at)
                <form method="POST" action="{{ route('notifications.read', $n) }}" style="display:inline">@csrf<button>Mark read</button></form>
            @endunless
        </li>
    @empty
        <li>No notifications.</li>
    @endforelse
    </ul>
</x-layout>

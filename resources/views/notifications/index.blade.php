<x-layout title="Notifications · ChurchFlow">
    <div class="cf-page-head">
        <div>
            <h1>Notifications</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                {{ number_format($unreadCount) }} unread of {{ number_format($totalCount) }} total.
            </p>
        </div>
        @if($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Mark all as read</button>
            </form>
        @endif
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    {{-- Filters are plain links with a ?filter= query, so a view is a URL a
         person can bookmark, and it survives a redirect back to the page. --}}
    <nav class="cf-tabs" aria-label="Notification filters">
        <a href="{{ route('notifications.index', ['filter' => 'all']) }}" class="cf-tab {{ $filter === 'all' ? 'is-active' : '' }}">All</a>
        <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" class="cf-tab {{ $filter === 'unread' ? 'is-active' : '' }}">
            Unread @if($unreadCount > 0)<span class="cf-badge cf-badge--brand" style="margin-left:.3rem">{{ $unreadCount }}</span>@endif
        </a>
        <a href="{{ route('notifications.index', ['filter' => 'read']) }}" class="cf-tab {{ $filter === 'read' ? 'is-active' : '' }}">Read</a>
    </nav>

    <div class="cf-card cf-card--flush">
        @if($items->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">
                    @if($filter === 'unread') You're all caught up
                    @elseif($filter === 'read') Nothing read yet
                    @else No notifications yet
                    @endif
                </p>
                <p class="cf-small cf-muted">
                    Decisions on your subventions, approval outcomes and other updates appear here.
                </p>
            </div>
        @else
            <ul class="cf-notif-list">
                @foreach($items as $n)
                    <li class="cf-notif {{ $n->read_at ? '' : 'is-unread' }}">
                        <span class="cf-notif__dot" aria-hidden="true"></span>
                        <div class="cf-notif__body">
                            <div class="cf-notif__head">
                                <strong>{{ $n->title }}</strong>
                                @if($n->type)
                                    <span class="cf-badge">{{ str_replace('_', ' ', $n->type) }}</span>
                                @endif
                                <span class="cf-tiny cf-muted" style="margin-left:auto">{{ $n->created_at?->diffForHumans() }}</span>
                            </div>
                            @if($n->body)
                                <p class="cf-small cf-muted" style="margin:.25rem 0 0">{{ $n->body }}</p>
                            @endif
                            <div class="cf-notif__actions">
                                @if($n->action_url)
                                    <a href="{{ $n->action_url }}" class="cf-btn cf-btn--secondary cf-btn--sm">View</a>
                                @endif
                                @unless($n->read_at)
                                    <form method="POST" action="{{ route('notifications.read', $n) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Mark read</button>
                                    </form>
                                @endunless
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if($items->hasPages())
        <div style="padding:1rem 1.35rem">{{ $items->links() }}</div>
    @endif

    <style>
        .cf-notif-list { list-style: none; margin: 0; padding: 0; }
        .cf-notif {
            display: flex; gap: .8rem; padding: 1rem 1.35rem;
            border-bottom: 1px solid var(--line, #e5e7eb);
        }
        .cf-notif:last-child { border-bottom: 0; }
        .cf-notif.is-unread { background: var(--surface-sunken, #f8fafc); }
        .cf-notif__dot {
            flex: 0 0 auto; width: .55rem; height: .55rem; margin-top: .4rem;
            border-radius: 50%; background: var(--line, #cbd5e1);
        }
        .cf-notif.is-unread .cf-notif__dot { background: var(--cf-brand, #1677ff); }
        .cf-notif__body { flex: 1 1 auto; min-width: 0; }
        .cf-notif__head { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
        .cf-notif__actions { display: flex; gap: .4rem; margin-top: .6rem; }
        .cf-tabs { display: flex; gap: .3rem; border-bottom: 1px solid var(--line, #e5e7eb); margin-bottom: 1.4rem; flex-wrap: wrap; }
        .cf-tab { display: inline-flex; align-items: center; padding: .6rem .9rem; font-size: .88rem; font-weight: 500; color: var(--muted, #64748b); text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -1px; }
        .cf-tab.is-active { color: var(--cf-brand, #1677ff); border-bottom-color: var(--cf-brand, #1677ff); }
    </style>
</x-layout>

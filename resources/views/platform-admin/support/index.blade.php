<x-layout title="Support Tickets · Platform Admin · ChurchFlow">
    @php
        $statusTone = fn(?string $s) => match ($s) {
            'open' => 'cf-badge--brand',
            'in_progress', 'waiting_on_customer' => 'cf-badge--warn',
            'resolved' => 'cf-badge--ok',
            default => '',
        };
        $priorityTone = fn(?string $p) => match ($p) {
            'urgent', 'high' => 'cf-badge--danger',
            'normal' => 'cf-badge--brand',
            default => '',
        };
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>Support tickets</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Every church's tickets, urgent first. Replies you post are visible to the church;
                internal notes are not.
            </p>
        </div>
        <a href="{{ route('platform-admin.dashboard') }}" class="cf-btn cf-btn--secondary cf-btn--sm">← Overview</a>
    </div>

    @if (session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">{{ session('status') }}</p>
            </div>
        </div>
    @endif

    {{-- Filters. A GET form is deliberately NOT given the loading overlay
         (data-cf-no-loading): it is a cheap navigation, not a data mutation,
         and flashing a full-screen spinner on a filter click is noise. --}}
    <form method="GET" action="{{ route('platform-admin.support.index') }}" class="cf-card" data-cf-no-loading
        style="margin-bottom:1.2rem">
        <div class="cf-card__body" style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:flex-end">
            <div class="cf-field" style="min-width:11rem">
                <label class="cf-label" for="status">Status</label>
                <select name="status" id="status" class="cf-select">
                    <option value="">All statuses</option>
                    @foreach (['open', 'in_progress', 'waiting_on_customer', 'resolved', 'closed'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>
                            {{ str_replace('_', ' ', ucfirst($s)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="cf-field" style="min-width:11rem">
                <label class="cf-label" for="priority">Priority</label>
                <select name="priority" id="priority" class="cf-select">
                    <option value="">All priorities</option>
                    @foreach (['urgent', 'high', 'normal', 'low'] as $p)
                        <option value="{{ $p }}" @selected(request('priority') === $p)>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Filter</button>
            @if (request()->filled('status') || request()->filled('priority'))
                <a href="{{ route('platform-admin.support.index') }}" class="cf-btn cf-btn--ghost cf-btn--sm">Clear</a>
            @endif
        </div>
    </form>

    <div class="cf-card cf-card--flush">
        @if ($tickets->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No tickets</p>
                <p class="cf-small cf-muted">Nothing matches these filters. Tickets raised by churches appear here.</p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Church</th>
                            <th>Subject</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th style="text-align:right">Opened</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tickets as $ticket)
                            <tr>
                                <td>{{ $ticket->church?->name ?? '—' }}</td>
                                <td>
                                    <a
                                        href="{{ route('platform-admin.support.show', $ticket) }}">{{ $ticket->subject }}</a>
                                    <div class="cf-tiny cf-muted">
                                        {{ str_replace('_', ' ', ucfirst($ticket->category)) }}</div>
                                </td>
                                <td><span
                                        class="cf-badge {{ $priorityTone($ticket->priority) }}">{{ ucfirst($ticket->priority) }}</span>
                                </td>
                                <td><span
                                        class="cf-badge {{ $statusTone($ticket->status) }}">{{ str_replace('_', ' ', ucfirst($ticket->status)) }}</span>
                                </td>
                                <td class="cf-small cf-muted" style="text-align:right;white-space:nowrap">
                                    {{ $ticket->created_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="padding:1rem 1.2rem">
                {{ $tickets->withQueryString()->links() }}
            </div>
        @endif
    </div>
</x-layout>

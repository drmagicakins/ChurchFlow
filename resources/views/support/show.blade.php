<x-layout :title="$ticket->subject . ' · Support · ChurchFlow'">
    @php
        $statusTone = match ($ticket->status) {
            'open' => 'cf-badge--brand',
            'in_progress', 'waiting_on_customer' => 'cf-badge--warn',
            'resolved' => 'cf-badge--ok',
            default => '',
        };
        $priorityTone = match ($ticket->priority) {
            'urgent', 'high' => 'cf-badge--danger',
            'normal' => 'cf-badge--brand',
            default => '',
        };
    @endphp

    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin:0">
                <a href="{{ route('support.index') }}">← All tickets</a>
            </p>
            <h1 style="margin:.35rem 0 0">{{ $ticket->subject }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.35rem">
                Opened {{ $ticket->created_at?->diffForHumans() }} ·
                {{ str_replace('_', ' ', ucfirst($ticket->category)) }}
            </p>
        </div>
        <div style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap">
            <span class="cf-badge {{ $priorityTone }}">{{ ucfirst($ticket->priority) }}</span>
            <span class="cf-badge {{ $statusTone }}">{{ str_replace('_', ' ', ucfirst($ticket->status)) }}</span>
        </div>
    </div>

    @if (session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">{{ session('status') }}</p>
            </div>
        </div>
    @endif

    @if ($ticket->resolved_at)
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">Marked resolved {{ $ticket->resolved_at->diffForHumans() }}. Reply below to
                    reopen it.</p>
            </div>
        </div>
    @endif

    <div class="cf-grid cf-grid--2">
        <div class="cf-card">
            <div class="cf-card__head">
                <h2>What you told us</h2>
            </div>
            <div class="cf-card__body">
                <p style="white-space:pre-line;margin:0">{{ $ticket->description }}</p>

                @if ($ticket->screenshot)
                    {{-- Served only through the signed, tenant-checked download
                         route — never a bare storage path. --}}
                    <p class="cf-small" style="margin-top:1rem">
                        <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('files.show', now()->addMinutes(30), ['uploadedFile' => $ticket->screenshot->id]) }}"
                            target="_blank" rel="noopener">
                            View attached screenshot ↗
                        </a>
                    </p>
                @endif
            </div>
        </div>

        <div class="cf-card cf-card--flush">
            <div class="cf-card__head">
                <h2>Conversation</h2>
                <span class="cf-tiny cf-muted">{{ $replies->count() }}
                    {{ \Illuminate\Support\Str::plural('reply', $replies->count()) }}</span>
            </div>

            <div class="cf-card__body" style="display:grid;gap:.9rem">
                @forelse($replies as $reply)
                    @php $isStaff = $reply->author?->is_platform_admin ?? false; @endphp
                    <div
                        style="border-left:3px solid {{ $isStaff ? 'var(--cf-brand, #1677FF)' : 'var(--line, #e5e7eb)' }};padding:.2rem 0 .2rem .8rem">
                        <p class="cf-tiny cf-muted" style="margin:0">
                            <strong>{{ $isStaff ? 'ChurchFlow Support' : $reply->author?->name ?? 'You' }}</strong>
                            · {{ $reply->created_at?->diffForHumans() }}
                        </p>
                        <p style="margin:.3rem 0 0;white-space:pre-line">{{ $reply->body }}</p>
                    </div>
                @empty
                    <p class="cf-small cf-muted" style="margin:0">No replies yet — we'll respond here.</p>
                @endforelse
            </div>

            @if (!in_array($ticket->status, ['closed'], true))
                <div class="cf-card__body" style="border-top:1px solid var(--line, #e5e7eb)">
                    <form method="POST" action="{{ route('support.reply', $ticket) }}" class="cf-form">
                        @csrf
                        <x-form.textarea name="body" label="Add a reply" required maxlength="5000" rows="3" />
                        <div class="cf-form__actions" style="justify-content:flex-end">
                            <button type="submit" class="cf-btn cf-btn--primary">Send reply</button>
                        </div>
                    </form>
                </div>
            @else
                <div class="cf-card__body" style="border-top:1px solid var(--line, #e5e7eb)">
                    <p class="cf-small cf-muted" style="margin:0">This ticket is closed. Open a new one if the problem
                        returns.</p>
                </div>
            @endif
        </div>
    </div>
</x-layout>

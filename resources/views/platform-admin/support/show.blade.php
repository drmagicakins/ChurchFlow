<x-layout :title="$ticket->subject . ' · Support · Platform Admin · ChurchFlow'">
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
        // Assignment can only go to platform staff — matching the controller's
// validation (exists:users,id) and SupportTicketService::assign()'s own
        // abort_unless($assignee->is_platform_admin).
        $staff = \App\Models\User::where('is_platform_admin', true)->orderBy('name')->get();
    @endphp

    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin:0">
                <a href="{{ route('platform-admin.support.index') }}">← All tickets</a>
            </p>
            <h1 style="margin:.35rem 0 0">{{ $ticket->subject }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.35rem">
                {{ $ticket->church?->name ?? 'Unknown church' }} ·
                opened {{ $ticket->created_at?->diffForHumans() }} ·
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

    <div class="cf-grid cf-grid--2">
        <div style="display:grid;gap:1.2rem">
            <div class="cf-card">
                <div class="cf-card__head">
                    <h2>Reported issue</h2>
                </div>
                <div class="cf-card__body">
                    <p style="white-space:pre-line;margin:0">{{ $ticket->description }}</p>

                    @if ($ticket->screenshot)
                        <p class="cf-small" style="margin-top:1rem">
                            <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('files.show', now()->addMinutes(30), ['uploadedFile' => $ticket->screenshot->id]) }}"
                                target="_blank" rel="noopener">View attached screenshot ↗</a>
                        </p>
                    @endif
                </div>
            </div>

            <div class="cf-card">
                <div class="cf-card__head">
                    <h2>Manage</h2>
                </div>
                <div class="cf-card__body" style="display:grid;gap:1rem">
                    <form method="POST" action="{{ route('platform-admin.support.assign', $ticket) }}"
                        class="cf-form">
                        @csrf
                        <div class="cf-field">
                            <label class="cf-label" for="assigned_to">Assign to</label>
                            <div style="display:flex;gap:.5rem">
                                <select name="assigned_to" id="assigned_to" class="cf-select" required>
                                    <option value="">Choose staff…</option>
                                    @foreach ($staff as $member)
                                        <option value="{{ $member->id }}" @selected($ticket->assigned_to === $member->id)>
                                            {{ $member->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Assign</button>
                            </div>
                        </div>
                    </form>

                    @if ($ticket->assignee)
                        <p class="cf-small cf-muted" style="margin:0">Currently assigned to
                            <strong>{{ $ticket->assignee->name }}</strong>.</p>
                    @endif

                    <div
                        style="display:flex;gap:.5rem;flex-wrap:wrap;border-top:1px solid var(--line, #e5e7eb);padding-top:1rem">
                        @if (!in_array($ticket->status, ['resolved', 'closed'], true))
                            <form method="POST" action="{{ route('platform-admin.support.resolve', $ticket) }}">
                                @csrf
                                <button type="submit" class="cf-btn cf-btn--primary cf-btn--sm">Mark resolved</button>
                            </form>
                        @endif

                        @if ($ticket->status !== 'closed')
                            <form method="POST" action="{{ route('platform-admin.support.close', $ticket) }}">
                                @csrf
                                <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Close ticket</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="cf-card cf-card--flush">
            <div class="cf-card__head">
                <h2>Conversation</h2>
                <span class="cf-tiny cf-muted">Internal notes visible to staff only</span>
            </div>

            <div class="cf-card__body" style="display:grid;gap:.9rem">
                @forelse($replies as $reply)
                    @php $isStaff = $reply->author?->is_platform_admin ?? false; @endphp
                    <div
                        style="border-left:3px solid {{ $reply->is_internal_note ? '#f59e0b' : ($isStaff ? 'var(--cf-brand, #1677FF)' : 'var(--line, #e5e7eb)') }};padding:.2rem 0 .2rem .8rem">
                        <p class="cf-tiny cf-muted" style="margin:0">
                            <strong>{{ $reply->author?->name ?? 'Unknown' }}</strong>
                            @if ($reply->is_internal_note)
                                <span class="cf-badge cf-badge--warn" style="margin-left:.3rem">Internal note</span>
                            @endif
                            · {{ $reply->created_at?->diffForHumans() }}
                        </p>
                        <p style="margin:.3rem 0 0;white-space:pre-line">{{ $reply->body }}</p>
                    </div>
                @empty
                    <p class="cf-small cf-muted" style="margin:0">No replies yet.</p>
                @endforelse
            </div>

            <div class="cf-card__body" style="border-top:1px solid var(--line, #e5e7eb)">
                <form method="POST" action="{{ route('platform-admin.support.reply', $ticket) }}" class="cf-form">
                    @csrf
                    <x-form.textarea name="body" label="Reply" required maxlength="5000" rows="3"
                        hint="Unchecking “internal note” posts this to the church." />
                    <x-form.checkbox name="is_internal_note" label="Internal note (not visible to the church)"
                        :value="1" />
                    <div class="cf-form__actions" style="justify-content:flex-end">
                        <button type="submit" class="cf-btn cf-btn--primary">Send</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layout>

<x-layout title="Support · ChurchFlow">
    @php
        // Support is a conversation log, not a report — the tone mapping reads
        // status/priority the same way the platform-admin billing views do, so
        // a church and a platform admin describe the same ticket identically.
        $statusTone = fn(?string $s) => match ($s) {
            'open' => 'cf-badge--brand',
            'in_progress' => 'cf-badge--warn',
            'waiting_on_customer' => 'cf-badge--warn',
            'resolved' => 'cf-badge--ok',
            'closed' => '',
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
            <h1>Support</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Raise an issue and follow it through with the ChurchFlow team. Every ticket you
                open is private to your church.
            </p>
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
        {{-- The form. The global loader (partials/loader) covers this
             automatically on submit — no per-form wiring needed. --}}
        <x-form.card title="Open a ticket"
            description="Tell us what went wrong and, if it helps, attach a screenshot. You'll get a reply here."
            :action="route('support.store')" submit="Submit ticket" submit-icon="chat" enctype="multipart/form-data">
            <x-form.input name="subject" label="Subject" required maxlength="255"
                hint="A one-line summary — e.g. “CSV export returns an empty file”." />

            <x-form.select name="category" label="Category" required :options="[
                'billing' => 'Billing',
                'technical' => 'Technical',
                'feature_request' => 'Feature request',
                'other' => 'Other',
            ]" />

            <x-form.select name="priority" label="Priority" :options="[
                'low' => 'Low',
                'normal' => 'Normal',
                'high' => 'High',
                'urgent' => 'Urgent',
            ]" :value="'normal'"
                hint="Choose Urgent only if your church cannot operate — it pages someone." />

            <x-form.textarea name="description" label="Description" required maxlength="5000" rows="5"
                hint="What you expected, what happened, and anything you had already tried." />

            <div class="cf-field">
                <label class="cf-field__label" for="screenshot">Screenshot <span
                        class="cf-muted">(optional)</span></label>
                <input type="file" name="screenshot" id="screenshot" accept="image/*" class="cf-input">
                <p class="cf-field__hint cf-small cf-muted">JPEG, PNG, WebP or GIF, up to 5MB.</p>
            </div>
        </x-form.card>

        {{-- The ticket list. --}}
        <div class="cf-card cf-card--flush">
            <div class="cf-card__head">
                <h2>Your tickets</h2>
                <span class="cf-tiny cf-muted">{{ $tickets->total() }} total</span>
            </div>

            @if ($tickets->isEmpty())
                <div class="cf-empty">
                    <p class="cf-empty__title">No tickets yet</p>
                    <p class="cf-small cf-muted">Tickets you raise will appear here with their status and replies.</p>
                </div>
            @else
                <div class="cf-table-wrap">
                    <table class="cf-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th style="text-align:right">Opened</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tickets as $ticket)
                                <tr>
                                    <td>
                                        <a href="{{ route('support.show', $ticket) }}">{{ $ticket->subject }}</a>
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
                    {{ $tickets->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layout>

<x-layout title="Prayer Requests · ChurchFlow">
    @php
        $canManage = auth()->user()->hasPermission('pastoral.manage');
        $symbol = '₦';
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>Prayer requests</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Visible only to the pastor a request is assigned to — never to general administrators,
                regardless of what other access they hold.
            </p>
        </div>
        <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="pr-create" aria-expanded="false">
            <x-ui.icon name="heart" class="h-4 w-4" /> Log a request
        </button>
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    <div data-panel="pr-create" hidden style="margin-bottom:1.2rem">
        <x-form.card
            title="Log a prayer request"
            description="A request can come from a member or from a visitor with no record — that's why the name field is free text rather than a member picker."
            :action="route('pastoral.prayer-requests.store')"
            submit="Log request"
            layout="inline"
        >
            <x-form.input name="submitted_by_name" label="Submitted by" placeholder="Name of the person asking"
                hint="Leave blank if they'd rather stay anonymous." />
            <x-form.select name="status" label="Status" :value="'open'"
                :options="['open' => 'Open', 'praying' => 'Being prayed for', 'answered' => 'Answered', 'closed' => 'Closed']" />
            <x-form.textarea name="request" label="The request" rows="4" required wide
                placeholder="Record it as they said it." />
        </x-form.card>
    </div>

    <div class="cf-card cf-card--flush">
        @if($requests->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No prayer requests</p>
                <p class="cf-small cf-muted">Requests assigned to you appear here. Use <strong>Log a request</strong> to add one.</p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr><th>From</th><th>Request</th><th>Status</th><th style="text-align:right">Logged</th></tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $r)
                            <tr>
                                <td>{{ $r->submitterName() ?: 'Anonymous' }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($r->request, 90) }}</td>
                                <td>
                                    @php $tone = match ($r->status) {
                                        'answered' => 'cf-badge--ok',
                                        'closed' => 'cf-badge--danger',
                                        'praying' => 'cf-badge--brand',
                                        default => 'cf-badge--warn',
                                    }; @endphp
                                    <span class="cf-badge {{ $tone }}">{{ str_replace('_', ' ', ucfirst($r->status)) }}</span>
                                </td>
                                <td class="cf-small cf-muted" style="text-align:right;white-space:nowrap">{{ $r->created_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @push('scripts')
        <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
            (function () {
                var btn = document.querySelector('[data-toggle="pr-create"]');
                var panel = document.querySelector('[data-panel="pr-create"]');
                if (!btn || !panel) return;
                btn.addEventListener('click', function () {
                    var isOpen = !panel.hasAttribute('hidden');
                    if (isOpen) { panel.setAttribute('hidden', ''); }
                    else { panel.removeAttribute('hidden'); panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
                    btn.setAttribute('aria-expanded', String(!isOpen));
                });
                if (panel.querySelector('[aria-invalid="true"]')) { panel.removeAttribute('hidden'); }
            })();
        </script>
    @endpush
</x-layout>

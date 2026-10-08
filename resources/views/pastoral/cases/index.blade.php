<x-layout title="Pastoral Cases · ChurchFlow">
    @php $canManage = auth()->user()->hasPermission('pastoral.manage'); @endphp

    <div class="cf-page-head">
        <div>
            <h1>Pastoral cases</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Counseling, welfare, hospital visits and follow-up. Notes on a case are append-only —
                a correction is always a new note, never an edit to the history.
            </p>
        </div>
        <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="case-create" aria-expanded="false">
            <x-ui.icon name="heart" class="h-4 w-4" /> Open a case
        </button>
    </div>

    @if (session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">{{ session('status') }}</p>
            </div>
        </div>
    @endif

    <div data-panel="case-create" hidden style="margin-bottom:1.2rem">
        <x-form.card title="Open a pastoral case"
            description="A case is visible only to you and to anyone holding pastoral.manage. It is never visible to general administrators."
            :action="route('pastoral.cases.store')" submit="Open case" layout="inline">
            <x-form.input name="member_id" label="Member ID" type="number" required
                hint="The member this case concerns." />

            <x-form.select name="type" label="Case type" required placeholder="Choose a type" :options="[
                'counseling' => 'Counseling',
                'welfare' => 'Welfare / benevolence',
                'hospital_visit' => 'Hospital visit',
                'new_member_follow_up' => 'New-member follow-up',
            ]" />

            <x-form.select name="status" label="Status" :value="'open'" :options="['open' => 'Open', 'in_progress' => 'In progress', 'closed' => 'Closed']" />

            <x-form.textarea name="summary" label="Summary" rows="4" wide required
                placeholder="What is this case about, in a sentence or two?"
                hint="Record facts, not conclusions — the detail belongs in the case notes once it is open." />
        </x-form.card>
    </div>

    <div class="cf-card cf-card--flush">
        @if ($cases->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No cases assigned to you</p>
                <p class="cf-small cf-muted">
                    Pastoral cases are strictly scoped: you see the ones assigned to you, and nothing else.
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cases as $c)
                            <tr>
                                <td>{{ $c->member?->full_name ?? '—' }}</td>
                                <td><span class="cf-badge">{{ str_replace('_', ' ', ucfirst($c->type)) }}</span></td>
                                <td>
                                    @php
                                        $tone = match ($c->status) {
                                            'closed' => 'cf-badge--ok',
                                            'in_progress' => 'cf-badge--brand',
                                            default => 'cf-badge--warn',
                                        };
                                    @endphp
                                    <span
                                        class="cf-badge {{ $tone }}">{{ str_replace('_', ' ', ucfirst($c->status)) }}</span>
                                </td>
                                <td style="text-align:right">
                                    <a href="{{ route('pastoral.cases.show', $c) }}"
                                        class="cf-btn cf-btn--secondary cf-btn--sm">Open</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @push('scripts')
        <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
            (function() {
                var btn = document.querySelector('[data-toggle="case-create"]');
                var panel = document.querySelector('[data-panel="case-create"]');
                if (!btn || !panel) return;
                btn.addEventListener('click', function() {
                    var isOpen = !panel.hasAttribute('hidden');
                    if (isOpen) {
                        panel.setAttribute('hidden', '');
                    } else {
                        panel.removeAttribute('hidden');
                        panel.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                    btn.setAttribute('aria-expanded', String(!isOpen));
                });
                if (panel.querySelector('[aria-invalid="true"]')) {
                    panel.removeAttribute('hidden');
                }
            })();
        </script>
    @endpush
</x-layout>

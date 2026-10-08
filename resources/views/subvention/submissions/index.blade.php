<x-layout title="Subvention Submissions · ChurchFlow">
    @php
        $canSubmit = auth()->user()->hasPermission('subvention.submit');
        $canManage = auth()->user()->hasPermission('subvention.manage');
        $symbol = '₦';
        $branches = \App\Models\OrganizationalUnit::query()->orderBy('name')->get();
        $periods = \App\Models\SubventionPeriod::query()->latest('id')->get();
        $ruleSets = \App\Models\SubventionRuleSet::query()->orderBy('name')->get();
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>Subvention submissions</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Calculations are versioned, never overwritten — re-running one always creates a new
                record, so a previous figure can't silently change.
            </p>
        </div>
        @if ($canSubmit)
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="sub-create" aria-expanded="false">
                <x-ui.icon name="wallet" class="h-4 w-4" /> New submission
            </button>
        @endif
    </div>

    @if (session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">{{ session('status') }}</p>
            </div>
        </div>
    @endif

    @if ($canSubmit && $periods->isNotEmpty() && $ruleSets->isNotEmpty())
        <div data-panel="sub-create" hidden style="margin-bottom:1.2rem">
            <x-form.card title="Create a subvention submission"
                description="Pick the period and the rule set that applies, then record the branch's figures. Nothing is calculated until you submit — this only records the inputs."
                :action="route('subvention.submissions.store')" submit="Save draft" submit-icon="wallet" layout="inline">
                <x-form.select name="subvention_period_id" label="Period" required placeholder="Choose a period"
                    :options="$periods->map(fn($p) => ['value' => $p->id, 'label' => $p->name])->all()" />

                <x-form.select name="subvention_rule_set_id" label="Rule set" required placeholder="Choose a rule set"
                    :options="$ruleSets->map(fn($r) => ['value' => $r->id, 'label' => $r->name])->all()" hint="Determines which rates and deductions apply." />

                @if ($branches->isNotEmpty())
                    <x-form.select name="organizational_unit_id" label="Branch" required placeholder="Choose a branch"
                        :options="$branches->map(fn($b) => ['value' => $b->id, 'label' => $b->name])->all()" />
                @endif

                <x-form.input name="figures[general_tithe]" label="General tithe" type="number" step="0.01"
                    min="0" :value="'0'" hint="The figures the rule set is applied against." />
                <x-form.input name="figures[salary]" label="Salary total" type="number" step="0.01" min="0"
                    :value="'0'" />
                <x-form.input name="figures[admin_figure]" label="Administrative figure" type="number" step="0.01"
                    min="0" :value="'0'" />
            </x-form.card>
        </div>
    @elseif($canSubmit)
        <div class="cf-alert cf-alert--warn" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="info" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">A submission needs a period and a rule set first</p>
                @if ($canManage)
                    <p class="cf-small" style="margin:.3rem 0 0">
                        <a href="{{ route('subvention.periods.index') }}">Create a period</a> and a
                        <a href="{{ route('subvention.rule-sets.index') }}">rule set</a>, then come back here.
                    </p>
                @else
                    <p class="cf-small" style="margin:.3rem 0 0">Ask someone with subvention settings access to set
                        these up.</p>
                @endif
            </div>
        </div>
    @endif

    <div class="cf-card cf-card--flush">
        @if ($submissions->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No submissions yet</p>
                <p class="cf-small cf-muted">
                    @if ($canSubmit)
                        Use <strong>New submission</strong> above to record a branch's figures for a period.
                    @else
                        Submissions created by your team will appear here.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Branch</th>
                            <th>Period</th>
                            <th>Status</th>
                            <th style="text-align:right">Remittance</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($submissions as $s)
                            <tr>
                                <td><a
                                        href="{{ route('subvention.submissions.show', $s) }}">{{ $s->organizationalUnit?->name ?? '—' }}</a>
                                </td>
                                <td class="cf-small">{{ $s->period?->name ?? '—' }}</td>
                                <td>
                                    @php
                                        $tone = match ($s->status) {
                                            'approved' => 'cf-badge--ok',
                                            'rejected' => 'cf-badge--danger',
                                            'returned' => 'cf-badge--warn',
                                            'submitted', 'under_review' => 'cf-badge--brand',
                                            default => '',
                                        };
                                    @endphp
                                    <span
                                        class="cf-badge {{ $tone }}">{{ str_replace('_', ' ', ucfirst($s->status)) }}</span>
                                </td>
                                <td class="cf-mono" style="text-align:right;white-space:nowrap">
                                    {{ $symbol }}{{ number_format((float) ($s->latestCalculation?->remittance_amount ?? 0), 2) }}
                                </td>
                                <td style="text-align:right">
                                    <a href="{{ route('subvention.submissions.show', $s) }}"
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
                var btn = document.querySelector('[data-toggle="sub-create"]');
                var panel = document.querySelector('[data-panel="sub-create"]');
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

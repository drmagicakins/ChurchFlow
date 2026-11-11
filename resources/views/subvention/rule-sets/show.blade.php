<x-layout :title="$ruleSet->name.' · Subvention Rule Set · ChurchFlow'">
    @php
        $symbol = '₦';
        $canManage = auth()->user()->can('update', $ruleSet);
        $typeLabel = fn ($t) => match ($t) {
            'percentage' => 'Percentage',
            'fixed' => 'Fixed amount',
            'capped_percentage' => 'Percentage (capped)',
            default => ucfirst((string) $t),
        };
        $fieldLabel = fn (?string $f) => $f ? ucfirst(str_replace('_', ' ', $f)) : '—';
    @endphp

    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin:0"><a href="{{ route('subvention.rule-sets.index') }}">← All rule sets</a></p>
            <h1 style="margin:.35rem 0 0">{{ $ruleSet->name }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.35rem">
                {{ $ruleSet->rules->count() }} {{ \Illuminate\Support\Str::plural('rule', $ruleSet->rules->count()) }} ·
                {{ $ruleSet->rules->where('classification', 'retention')->count() }} retention,
                {{ $ruleSet->rules->where('classification', 'deduction')->count() }} deduction
            </p>
        </div>
        @if($canManage)
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="ruleset-edit" aria-expanded="false">
                <x-ui.icon name="sliders" class="h-4 w-4" /> Edit rules
            </button>
        @endif
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    <div class="cf-card cf-card--flush">
        <div class="cf-card__head">
            <h2>Rules</h2>
            <span class="cf-tiny cf-muted">Order is the order they are listed; amounts are summed per classification</span>
        </div>

        @if($ruleSet->rules->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No rules in this set</p>
                <p class="cf-small cf-muted">A rule set with no rules calculates nothing. Add rules to make it meaningful.</p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Rule</th>
                            <th>Applied to</th>
                            <th>Type</th>
                            <th style="text-align:right">Rate</th>
                            <th style="text-align:right">Cap</th>
                            <th>Classification</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ruleSet->rules->sortBy('sort_order') as $rule)
                            <tr>
                                <td><strong>{{ $rule->name }}</strong></td>
                                <td class="cf-small cf-muted">{{ $fieldLabel($rule->base_field) }}</td>
                                <td class="cf-small">{{ $typeLabel($rule->type) }}</td>
                                <td class="cf-mono" style="text-align:right;white-space:nowrap">
                                    @if($rule->type === 'fixed')
                                        {{ $symbol }}{{ number_format((float) $rule->fixed_amount, 2) }}
                                    @else
                                        {{ rtrim(rtrim(number_format((float) $rule->rate, 2), '0'), '.') }}%
                                    @endif
                                </td>
                                <td class="cf-mono" style="text-align:right;white-space:nowrap">
                                    {{ $rule->cap_amount !== null ? $symbol.number_format((float) $rule->cap_amount, 2) : '—' }}
                                </td>
                                <td>
                                    <span class="cf-badge {{ $rule->classification === 'retention' ? 'cf-badge--ok' : 'cf-badge--warn' }}">
                                        {{ ucfirst($rule->classification) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($canManage)
        {{-- Editing replaces the rules wholesale: a rule removed here stops
             applying. Past calculations are unaffected — each one stores its own
             breakdown at calculation time. --}}
        <div id="edit-rules" data-panel="ruleset-edit" hidden style="margin-top:1.2rem">
            <x-form.card title="Edit this rule set"
                description="Update the name and rules. Saving replaces the rules below in place; a rule you remove stops being applied to new submissions."
                :action="route('subvention.rule-sets.update', $ruleSet)" method="PUT" submit="Save changes" submit-icon="sliders">
                <x-form.input name="name" label="Rule set name" required :value="$ruleSet->name" />

                <div class="cf-field cf-field--wide">
                    <label class="cf-label">Rules</label>
                    @include('subvention.rule-sets.partials.rules-editor', ['rules' => $ruleSet->rules, 'prefix' => 'edit'])
                </div>
            </x-form.card>
        </div>
    @endif

    <div class="cf-card" style="margin-top:1.2rem">
        <div class="cf-card__body">
            <h2 style="margin:0 0 .4rem;font-size:.95rem">How this set is applied</h2>
            <ul class="cf-small cf-muted" style="margin:0;padding-left:1.1rem;display:grid;gap:.3rem">
                <li><strong>Retention</strong> rules are summed into the branch's retention total.</li>
                <li><strong>Deduction</strong> rules are summed into the deduction total.</li>
                <li>Shortfall = retention − deductions. A positive shortfall is what the branch remits to HQ.</li>
                <li>An active loan on the branch is deducted from the shortfall, never below zero.</li>
            </ul>
        </div>
    </div>

    @if($canManage)
        @push('scripts')
            <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
                (function () {
                    var btn = document.querySelector('[data-toggle="ruleset-edit"]');
                    var panel = document.querySelector('[data-panel="ruleset-edit"]');
                    if (!btn || !panel) return;
                    btn.addEventListener('click', function () {
                        var open = !panel.hasAttribute('hidden');
                        if (open) { panel.setAttribute('hidden', ''); }
                        else { panel.removeAttribute('hidden'); panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
                        btn.setAttribute('aria-expanded', String(!open));
                    });
                    if (panel.querySelector('[aria-invalid="true"]')) { panel.removeAttribute('hidden'); }
                })();
            </script>
        @endpush
    @endif
</x-layout>

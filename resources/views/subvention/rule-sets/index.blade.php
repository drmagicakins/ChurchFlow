<x-layout title="Subvention Rule Sets · ChurchFlow">
    @php
        $canManage = auth()->user()->hasPermission('subvention.manage');
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>Subvention rule sets</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                A rule set is the schedule of retentions and deductions applied to a branch's figures. The
                RCCG legacy groups (Group 1/2/3) are expressed as rule sets here — no rates are hard-coded in
                the calculation engine.
            </p>
        </div>
        @if($canManage)
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="ruleset-create" aria-expanded="false">
                <x-ui.icon name="sliders" class="h-4 w-4" /> New rule set
            </button>
        @endif
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    @if($canManage)
        <div data-panel="ruleset-create" hidden style="margin-bottom:1.2rem">
            <x-form.card title="Create a rule set"
                description="Name the set, then add the rules it applies. Retention rules build up what the branch keeps; deduction rules are summed and compared against it. Nothing is calculated until a branch files against this set."
                :action="route('subvention.rule-sets.store')" submit="Create rule set" submit-icon="sliders">
                <x-form.input name="name" label="Rule set name" required placeholder="e.g. RCCG Group 1"
                    hint="Shown when someone picks a rule set for a submission." />

                <div class="cf-field cf-field--wide">
                    <label class="cf-label">Rules</label>
                    @include('subvention.rule-sets.partials.rules-editor', ['rules' => [], 'prefix' => 'create'])
                </div>
            </x-form.card>
        </div>
    @endif

    @if($errors->any() && old('name') && ! old('__edit'))
        @push('scripts')
            <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
                document.addEventListener('DOMContentLoaded', function () {
                    var p = document.querySelector('[data-panel="ruleset-create"]');
                    var b = document.querySelector('[data-toggle="ruleset-create"]');
                    if (p) { p.removeAttribute('hidden'); }
                    if (b) { b.setAttribute('aria-expanded', 'true'); }
                });
            </script>
        @endpush
    @endif

    <div class="cf-card cf-card--flush">
        @if($ruleSets->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No rule sets configured</p>
                <p class="cf-small cf-muted">A rule set describes which figures are retained and which are deducted. Create one to start filing subventions.</p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Rule set</th>
                            <th style="text-align:right">Rules</th>
                            <th style="text-align:right">Retentions</th>
                            <th style="text-align:right">Deductions</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ruleSets as $ruleSet)
                            <tr>
                                <td><a href="{{ route('subvention.rule-sets.show', $ruleSet) }}"><strong>{{ $ruleSet->name }}</strong></a></td>
                                <td class="cf-mono" style="text-align:right">{{ $ruleSet->rules->count() }}</td>
                                <td class="cf-mono" style="text-align:right">{{ $ruleSet->rules->where('classification', 'retention')->count() }}</td>
                                <td class="cf-mono" style="text-align:right">{{ $ruleSet->rules->where('classification', 'deduction')->count() }}</td>
                                <td style="text-align:right">
                                    <a href="{{ route('subvention.rule-sets.show', $ruleSet) }}" class="cf-btn cf-btn--secondary cf-btn--sm">View rules</a>
                                    @if($canManage)
                                        <a href="{{ route('subvention.rule-sets.show', $ruleSet) }}#edit-rules" class="cf-btn cf-btn--secondary cf-btn--sm">Edit</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($canManage)
        @push('scripts')
            <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
                (function () {
                    var btn = document.querySelector('[data-toggle="ruleset-create"]');
                    var panel = document.querySelector('[data-panel="ruleset-create"]');
                    if (!btn || !panel) return;
                    btn.addEventListener('click', function () {
                        var open = !panel.hasAttribute('hidden');
                        if (open) { panel.setAttribute('hidden', ''); }
                        else { panel.removeAttribute('hidden'); panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
                        btn.setAttribute('aria-expanded', String(!open));
                    });
                })();
            </script>
        @endpush
    @endif
</x-layout>

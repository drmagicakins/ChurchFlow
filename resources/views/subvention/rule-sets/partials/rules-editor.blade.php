{{--
 | The rule rows for a rule set, shared by the create form (index) and the edit
 | form (show). Rendering them from one partial means a field added to a rule
 | cannot exist on one form and be missing from the other.
 |
 | Each row carries an index in its field names (rules[N][name], rules[N][type],
 | ...) which the controller's validation expects (rules.*.*). The "Add rule"
 | button clones a template row and renumbers, so the indices stay contiguous —
 | a gap would simply be ignored by Laravel, but a gap also means the row order
 | silently stops matching what the user sees.
 |
 | Props:
 |   rules  — existing rules to prefill (edit) or [] (create)
 |   prefix — a unique string so two editors on one page don't clash
--}}
@props(['rules' => [], 'prefix' => 'rules'])

@php
    $rules = collect($rules)->values();

    // The base fields a rule can be applied to. These are the figure keys a
    // submission records (see SubventionSubmissionController/RCCG reference),
    // offered as a datalist so a custom base_field can still be typed.
    $baseFields = [
        'general_tithe' => 'General tithe',
        'minister_tithe' => 'Minister tithe',
        'offering' => 'Offerings',
        'special_offering' => 'Special / thanksgiving',
        'admin_base' => 'Administrative base',
        'salary' => 'Salary total',
    ];
@endphp

<div class="cf-rules-editor" data-rules-editor="{{ $prefix }}">
    <div class="cf-rules-editor__head">
        <span>Rule</span>
        <span>Applied to</span>
        <span>Type</span>
        <span>Rate / amount</span>
        <span>Cap</span>
        <span>Classification</span>
        <span></span>
    </div>

    <div data-rules-rows>
        @forelse($rules as $i => $rule)
            <div class="cf-rules-editor__row" data-rule-row>
                <input type="text" name="rules[{{ $i }}][name]" value="{{ $rule->name ?? '' }}"
                    class="cf-input" placeholder="e.g. General Tithe Retention" required />

                <input type="text" name="rules[{{ $i }}][base_field]" value="{{ $rule->base_field ?? '' }}"
                    class="cf-input" list="{{ $prefix }}-base-fields" placeholder="general_tithe" />

                <select name="rules[{{ $i }}][type]" class="cf-select" data-rule-type>
                    @foreach (['percentage' => 'Percentage', 'fixed' => 'Fixed', 'capped_percentage' => 'Capped %'] as $value => $label)
                        <option value="{{ $value }}" @selected(($rule->type ?? 'percentage') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <input type="number" step="0.01" min="0"
                    name="rules[{{ $i }}][{{ ($rule->type ?? 'percentage') === 'fixed' ? 'fixed_amount' : 'rate' }}]"
                    value="{{ ($rule->type ?? 'percentage') === 'fixed' ? $rule->fixed_amount ?? '' : $rule->rate ?? '' }}"
                    class="cf-input" data-rule-amount placeholder="25" />

                <input type="number" step="0.01" min="0" name="rules[{{ $i }}][cap_amount]"
                    value="{{ $rule->cap_amount ?? '' }}" class="cf-input" placeholder="—" />

                <select name="rules[{{ $i }}][classification]" class="cf-select">
                    <option value="retention" @selected(($rule->classification ?? 'retention') === 'retention')>Retention</option>
                    <option value="deduction" @selected(($rule->classification ?? '') === 'deduction')>Deduction</option>
                </select>

                <button type="button" class="cf-btn cf-btn--secondary cf-btn--sm" data-rule-remove
                    aria-label="Remove this rule">×</button>
            </div>
        @empty
            <div class="cf-rules-editor__row" data-rule-row>
                <input type="text" name="rules[0][name]" value="" class="cf-input"
                    placeholder="e.g. General Tithe Retention" required />
                <input type="text" name="rules[0][base_field]" value="" class="cf-input"
                    list="{{ $prefix }}-base-fields" placeholder="general_tithe" />
                <select name="rules[0][type]" class="cf-select" data-rule-type>
                    <option value="percentage">Percentage</option>
                    <option value="fixed">Fixed</option>
                    <option value="capped_percentage">Capped %</option>
                </select>
                <input type="number" step="0.01" min="0" name="rules[0][rate]" value=""
                    class="cf-input" data-rule-amount placeholder="25" />
                <input type="number" step="0.01" min="0" name="rules[0][cap_amount]" value=""
                    class="cf-input" placeholder="—" />
                <select name="rules[0][classification]" class="cf-select">
                    <option value="retention">Retention</option>
                    <option value="deduction">Deduction</option>
                </select>
                <button type="button" class="cf-btn cf-btn--secondary cf-btn--sm" data-rule-remove
                    aria-label="Remove this rule">×</button>
            </div>
        @endforelse
    </div>

    <datalist id="{{ $prefix }}-base-fields">
        @foreach ($baseFields as $key => $label)
            <option value="{{ $key }}">{{ $label }}</option>
        @endforeach
    </datalist>

    <button type="button" class="cf-btn cf-btn--secondary cf-btn--sm" data-rule-add style="margin-top:.6rem">
        + Add rule
    </button>
</div>

@once
    @push('scripts')
        <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
            (function() {
                /*
                 * The rules editor. Delegated from the document so it works for
                 * every editor on the page (the create form and the edit form)
                 * without re-binding, and so a form rendered later still works.
                 */
                function nextIndex(rows) {
                    var max = -1;
                    rows.forEach(function(row) {
                        row.querySelectorAll('[name^="rules["]').forEach(function(input) {
                            var m = /rules\[(\d+)\]/.exec(input.name);
                            if (m) {
                                max = Math.max(max, parseInt(m[1], 10));
                            }
                        });
                    });
                    return max + 1;
                }

                document.addEventListener('click', function(e) {
                    var add = e.target.closest('[data-rule-add]');
                    if (add) {
                        var editor = add.closest('[data-rules-editor]');
                        var rows = editor.querySelector('[data-rules-rows]');
                        var template = rows.querySelector('[data-rule-row]');
                        var index = nextIndex(rows.querySelectorAll('[data-rule-row]'));
                        var clone = template.cloneNode(true);

                        clone.querySelectorAll('input, select').forEach(function(el) {
                            if (!el.name) return;
                            el.name = el.name.replace(/rules\[\d+\]/, 'rules[' + index + ']');
                            if (el.tagName === 'INPUT' && el.type !== 'number') {
                                el.value = '';
                            } else if (el.tagName === 'INPUT') {
                                el.value = '';
                            }
                        });

                        rows.appendChild(clone);
                        return;
                    }

                    var remove = e.target.closest('[data-rule-remove]');
                    if (remove) {
                        var container = remove.closest('[data-rules-rows]');
                        // Never remove the last row: an empty editor has no
                        // field to type in and no obvious way back.
                        if (container.querySelectorAll('[data-rule-row]').length > 1) {
                            remove.closest('[data-rule-row]').remove();
                        }
                        return;
                    }
                });

                // A "Fixed" rule has an amount, not a rate — swap the field name
                // so the controller receives fixed_amount for a fixed rule.
                document.addEventListener('change', function(e) {
                    var select = e.target.closest('[data-rule-type]');
                    if (!select) return;
                    var row = select.closest('[data-rule-row]');
                    var amount = row.querySelector('[data-rule-amount]');
                    if (!amount) return;
                    var isFixed = select.value === 'fixed';
                    amount.name = amount.name.replace(/\[(rate|fixed_amount)\]$/, isFixed ? '[fixed_amount]' :
                        '[rate]');
                    amount.placeholder = isFixed ? '5000' : '25';
                });
            })
            ();
        </script>
    @endpush
@endonce

<style>
    .cf-rules-editor__head,
    .cf-rules-editor__row {
        display: grid;
        grid-template-columns: 2fr 1.4fr 1.1fr 1fr 0.9fr 1.1fr auto;
        gap: .5rem;
        align-items: center;
    }

    .cf-rules-editor__head {
        font-size: .72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--muted, #64748b);
        padding: 0 .1rem .4rem;
    }

    .cf-rules-editor__row {
        padding: .35rem 0;
        border-top: 1px solid var(--line, #e5e7eb);
    }

    .cf-rules-editor__row .cf-input,
    .cf-rules-editor__row .cf-select {
        width: 100%;
    }

    @media (max-width: 900px) {
        .cf-rules-editor__head {
            display: none;
        }

        .cf-rules-editor__row {
            grid-template-columns: 1fr 1fr;
            border-top: 1px solid var(--line, #e5e7eb);
            padding: .8rem 0;
        }
    }
</style>

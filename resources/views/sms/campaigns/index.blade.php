<x-layout title="SMS Campaigns · ChurchFlow">
    @php
        $canSend = auth()->user()->hasPermission('sms.manage');
        $symbol = '₦';
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>SMS campaigns</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Estimates are free. Confirming reserves the exact units shown — so the number you
                approve is the number you're charged, even if membership changes in between.
            </p>
        </div>
        @if ($canSend)
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="sms-create" aria-expanded="false">
                <x-ui.icon name="chat" class="h-4 w-4" /> New Campaign
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

    {{-- The wallet balance leads the page because it is the constraint that
         decides whether a campaign can actually send. --}}
    <div class="cf-card" style="margin-bottom:1.2rem">
        <div class="cf-card__body"
            style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
            <div class="cf-stat">
                <span class="cf-stat__label">SMS wallet balance</span>
                <span class="cf-stat__value">{{ number_format($wallet->balance_units) }}</span>
                <span class="cf-stat__hint">{{ $wallet->ownerLabel() }} · units available to send</span>
            </div>
            <a href="{{ route('sms.wallet.show') }}" class="cf-btn cf-btn--secondary cf-btn--sm">Top up credits</a>
        </div>
    </div>

    @if ($canSend)
        <div data-panel="sms-create" hidden style="margin-bottom:1.2rem"
            x-data="{ audience: '{{ old('audience_type', 'church_wide') }}' }">
            <x-form.card title="Start a campaign"
                description="Nothing is sent yet. Creating a campaign only saves the message and audience — you'll see the exact recipient count and unit cost before confirming."
                :action="route('sms.campaigns.store')" submit="Save campaign" submit-icon="chat" layout="inline">
                <x-form.input name="name" label="Campaign name" required
                    placeholder="e.g. Easter service reminder" />

                <x-form.select name="audience_type" label="Who receives it" :value="old('audience_type', 'church_wide')" :options="[
                    'church_wide' => 'Entire church',
                    'branch' => 'A specific branch',
                    'department' => 'A department',
                    'group' => 'A group',
                ]"
                    hint="Only members with a phone number are counted." x-model="audience" data-audience-select />

                {{-- §19/§21: choosing an audience reveals WHICH branch/department/
                     group — exactly the same reveal pattern as the announcements
                     form, so the two composers behave identically. Every field is
                     present but hidden, so switching audience never loses a value
                     and required_if only fires for the chosen one. --}}
                <div x-show="audience === 'branch'" x-cloak>
                    <x-form.select name="organizational_unit_id" label="Branch"
                        :value="old('organizational_unit_id')" placeholder="Choose a branch..."
                        :required="old('audience_type') === 'branch'"
                        :options="$branches->map(fn ($b) => ['value' => $b->id, 'label' => $b->name])->all()" />
                </div>

                <div x-show="audience === 'department'" x-cloak>
                    <x-form.select name="department_id" label="Department"
                        :value="old('department_id')" placeholder="Choose a department..."
                        :required="old('audience_type') === 'department'"
                        :options="$departments->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->all()" />
                </div>

                <div x-show="audience === 'group'" x-cloak>
                    <x-form.select name="group_id" label="Group"
                        :value="old('group_id')" placeholder="Choose a group..."
                        :required="old('audience_type') === 'group'"
                        :options="$groups->map(fn ($g) => ['value' => $g->id, 'label' => $g->name])->all()" />
                </div>

                <x-form.textarea name="message" label="Message" rows="5" required wide
                    placeholder="Keep it under 160 characters to stay within one SMS unit."
                    hint="Longer messages are split into multiple units automatically — the cost estimate shows exactly how many." />
            </x-form.card>
        </div>
    @endif

    <div class="cf-card cf-card--flush">
        @if ($campaigns->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No campaigns yet</p>
                <p class="cf-small cf-muted">
                    @if ($canSend)
                        Use <strong>New Campaign</strong> above — you'll see the cost before anything sends.
                    @else
                        Campaigns created by your team will appear here.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th>Audience</th>
                            <th style="text-align:right">Units</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($campaigns as $c)
                            <tr>
                                <td><a href="{{ route('sms.campaigns.show', $c) }}">{{ $c->name }}</a></td>
                                <td><span
                                        class="cf-badge">{{ str_replace('_', ' ', $c->audience_type ?? 'church wide') }}</span>
                                </td>
                                <td class="cf-mono" style="text-align:right">{{ number_format($c->total_units ?? 0) }}
                                </td>
                                <td>
                                    @php
                                        $tone = match ($c->status) {
                                            'completed', 'sent' => 'cf-badge--ok',
                                            'partially_failed' => 'cf-badge--warn',
                                            'failed' => 'cf-badge--danger',
                                            default => 'cf-badge--brand',
                                        };
                                    @endphp
                                    <span
                                        class="cf-badge {{ $tone }}">{{ str_replace('_', ' ', ucfirst($c->status)) }}</span>
                                </td>
                                <td style="text-align:right">
                                    <a href="{{ route('sms.campaigns.show', $c) }}"
                                        class="cf-btn cf-btn--secondary cf-btn--sm">View</a>
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
                var btn = document.querySelector('[data-toggle="sms-create"]');
                var panel = document.querySelector('[data-panel="sms-create"]');
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

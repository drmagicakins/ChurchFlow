<x-layout title="Financial Accounts · ChurchFlow">
    @php
        $canRecord = auth()->user()->hasPermission('finance.record');
        $symbol = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€'][$church?->currency ?? 'NGN'] ?? '';
        $total = $accounts->sum(fn ($a) => (float) $a->balance());
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>Financial Accounts</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Balances are computed live from the transaction ledger every time — never stored and edited,
                so an account balance can't drift from its own history.
            </p>
        </div>
        @if($canRecord)
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="account-create" aria-expanded="false">
                <x-ui.icon name="wallet" class="h-4 w-4" /> Add Account
            </button>
        @endif
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    @if($accounts->isNotEmpty())
        <div class="cf-card" style="margin-bottom:1.2rem">
            <div class="cf-card__body">
                <div class="cf-stat">
                    <span class="cf-stat__label">Combined balance across all accounts</span>
                    <span class="cf-stat__value">{{ $symbol }}{{ number_format($total, 2) }}</span>
                    <span class="cf-stat__hint">{{ $accounts->count() }} {{ \Illuminate\Support\Str::plural('account', $accounts->count()) }}</span>
                </div>
            </div>
        </div>
    @endif

    @if($canRecord)
        <div data-panel="account-create" hidden style="margin-bottom:1.2rem">
            <x-form.card
                title="Add a financial account"
                description="An account is a place money sits — a bank account, a cash box, a building fund. Transactions are always recorded against one."
                :action="route('finance.accounts.store')"
                submit="Create account"
                submit-icon="wallet"
                layout="inline"
            >
                <x-form.input name="name" label="Account name" required placeholder="e.g. Main Bank Account" />

                <x-form.select name="type" label="Account type" :value="'bank'" :options="[
                    'bank' => 'Bank account',
                    'cash' => 'Cash',
                    'mobile_money' => 'Mobile money',
                    'other' => 'Other',
                ]" />

                <x-form.input name="opening_balance" label="Opening balance" type="number" step="0.01"
                    hint="Optional. Recorded as a starting transaction, never as a hidden adjustment." />

                <x-form.textarea name="description" label="Description" rows="2" wide />
            </x-form.card>
        </div>
    @endif

    <div class="cf-card cf-card--flush">
        @if($accounts->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No financial accounts yet</p>
                <p class="cf-small cf-muted">
                    @if($canRecord)
                        Create one above to start recording income and expenses.
                    @else
                        Accounts created by your finance team will appear here.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr><th>Account</th><th>Type</th><th style="text-align:right">Balance</th><th style="text-align:right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach($accounts as $account)
                            <tr>
                                <td><a href="{{ route('finance.accounts.show', $account) }}">{{ $account->name }}</a></td>
                                <td><span class="cf-badge">{{ ucfirst(str_replace('_', ' ', $account->type)) }}</span></td>
                                <td class="cf-mono" style="text-align:right;white-space:nowrap">
                                    {{ $symbol }}{{ number_format((float) $account->balance(), 2) }}
                                </td>
                                <td style="text-align:right">
                                    <a href="{{ route('finance.accounts.show', $account) }}" class="cf-btn cf-btn--secondary cf-btn--sm">Open</a>
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
            (function () {
                var btn = document.querySelector('[data-toggle="account-create"]');
                var panel = document.querySelector('[data-panel="account-create"]');
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

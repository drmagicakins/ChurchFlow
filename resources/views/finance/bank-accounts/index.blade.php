<x-layout title="Bank accounts · Finance · ChurchFlow">
    @php
        $me = auth()->id();
        $myAccount = $accounts->firstWhere('user_id', $me);
    @endphp

    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin:0"><a href="{{ route('finance.accounts.index') }}">← Financial
                    accounts</a></p>
            <h1 style="margin:.35rem 0 0">Settlement bank accounts</h1>
            <p class="cf-small cf-muted" style="margin-top:.35rem">
                @if ($isAdmin)
                    The accounts your pastors want their subvention or remittance paid into. Verify an account before
                    transferring to it.
                @else
                    Tell us where to pay your subvention or remittance. The registrar reads this to make the transfer.
                @endif
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

    @if ($errors->any())
        <div class="cf-alert cf-alert--error" role="alert">
            <span class="cf-alert__icon"><x-ui.icon name="alert" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">There is a problem with your submission.</p>
                <ul class="cf-alert__list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="cf-grid cf-grid--2">
        {{-- The form: a pastor sets their own; an admin can set one for a pastor. --}}
        <x-form.card :title="$myAccount ? 'Update your bank account' : 'Add your bank account'"
            description="Pick your bank from the list, then enter the account name and the 10-digit account number exactly as it appears on your statement."
            :action="route('finance.bank-accounts.store')" submit="Save bank account" submit-icon="wallet">
            @if ($isAdmin)
                <x-form.select name="user_id" label="Pastor (parish)" placeholder="Myself" :options="$pastors->map(fn($p) => ['value' => $p->id, 'label' => $p->name . ' · ' . $p->email])->all()"
                    hint="Leave blank to record an account for yourself." />
            @endif

            <x-form.select name="bank_code" label="Bank" required placeholder="Choose a bank" :value="$myAccount?->bank_code"
                :options="collect($banks)
                    ->map(fn($name, $code) => ['value' => $code, 'label' => $name])
                    ->values()
                    ->all()" hint="Every bank licensed by the Central Bank of Nigeria is listed." />

            <x-form.input name="account_name" label="Account name" required :value="$myAccount?->account_name"
                placeholder="e.g. John Adeyemi" hint="Exactly as the bank holds it — a mismatch delays the transfer." />

            <x-form.input name="account_number" label="Account number" required inputmode="numeric" maxlength="10"
                :value="$myAccount?->account_number" placeholder="0123456789" hint="10 digits, no spaces." />
        </x-form.card>

        {{-- The list. A pastor sees their own row; an admin sees every row. --}}
        <div class="cf-card cf-card--flush">
            <div class="cf-card__head">
                <h2>{{ $isAdmin ? 'Pastor settlement accounts' : 'Your saved account' }}</h2>
                <span class="cf-tiny cf-muted">{{ $accounts->count() }}
                    {{ \Illuminate\Support\Str::plural('account', $accounts->count()) }}</span>
            </div>

            @if ($accounts->isEmpty())
                <div class="cf-empty">
                    <p class="cf-empty__title">No bank account on file yet</p>
                    <p class="cf-small cf-muted">
                        @if ($isAdmin)
                            Accounts your pastors add will appear here.
                        @else
                            Use the form beside this to add the account we should pay into.
                        @endif
                    </p>
                </div>
            @else
                <div class="cf-table-wrap">
                    <table class="cf-table">
                        <thead>
                            <tr>
                                @if ($isAdmin)
                                    <th>Pastor</th>
                                @endif
                                <th>Bank</th>
                                <th>Account</th>
                                <th>Status</th>
                                <th style="text-align:right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($accounts as $account)
                                <tr>
                                    @if ($isAdmin)
                                        <td>
                                            <strong>{{ $account->user?->name ?? '—' }}</strong>
                                            <div class="cf-tiny cf-muted cf-mono">{{ $account->user?->email }}</div>
                                        </td>
                                    @endif
                                    <td>{{ $account->bank_name }}</td>
                                    <td>
                                        {{ $account->account_name }}
                                        <div class="cf-tiny cf-muted cf-mono">{{ $account->maskedNumber() }}</div>
                                    </td>
                                    <td>
                                        @if ($account->is_verified)
                                            <span class="cf-badge cf-badge--ok">Verified</span>
                                        @else
                                            <span class="cf-badge cf-badge--warn">Unverified</span>
                                        @endif
                                    </td>
                                    <td style="text-align:right;white-space:nowrap">
                                        @if ($isAdmin)
                                            <form method="POST"
                                                action="{{ route('finance.bank-accounts.verify', $account) }}"
                                                style="display:inline">
                                                @csrf
                                                <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">
                                                    {{ $account->is_verified ? 'Unverify' : 'Verify' }}
                                                </button>
                                            </form>
                                        @endif
                                        @if (auth()->user()->can('delete', $account))
                                            <form method="POST"
                                                action="{{ route('finance.bank-accounts.destroy', $account) }}"
                                                style="display:inline"
                                                data-confirm="Remove this bank account? Subvention transfers would have nowhere to go until it is re-added."
                                                data-confirm-title="Remove bank account?" data-confirm-ok="Remove">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="cf-btn cf-btn--danger cf-btn--sm">Remove</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="cf-card__body" style="border-top:1px solid var(--line, #e5e7eb)">
                    <p class="cf-tiny cf-muted" style="margin:0">
                        Account numbers are shown masked in this list. The registrar can reveal the full number when
                        making a transfer.
                    </p>
                </div>
            @endif
        </div>
    </div>
</x-layout>

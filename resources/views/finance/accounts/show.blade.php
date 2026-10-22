<x-layout :title="$account->name . ' · ChurchFlow'">
    @php
        $canRecord = auth()->user()->hasPermission('finance.record');
        $symbol =
            ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€'][
                $account->currency ?? ($church?->currency ?? 'NGN')
            ] ?? '';
        $members = \App\Models\Member::query()->orderBy('full_name')->limit(500)->get();
        $hasTransactions = $transactions->total() > 0;
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>{{ $account->name }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                <span class="cf-badge">{{ ucfirst(str_replace('_', ' ', $account->type)) }}</span>
                · Balance is computed live from the ledger below, never stored and edited.
            </p>
        </div>
        <a href="{{ route('finance.accounts.index') }}" class="cf-btn cf-btn--secondary cf-btn--sm">← All accounts</a>
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
            <span class="cf-alert__icon"><x-ui.icon name="lock" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">That transaction was not recorded.</p>
                <ul class="cf-alert__list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="cf-card" style="margin-bottom:1.2rem">
        <div class="cf-card__body">
            <div class="cf-stat">
                <span class="cf-stat__label">Current balance</span>
                <span class="cf-stat__value">{{ $symbol }}{{ number_format((float) $balance, 2) }}</span>
                <span class="cf-stat__hint">
                    Approved income less approved and unvoided expenses.
                </span>
            </div>
        </div>
    </div>

    @if ($canRecord)
        {{-- The two forms a church actually uses day to day: record income
             (tithes, offerings, donations) and record an expense.

             Income posts immediately; an expense is created PENDING and enters
             the approval workflow, so the form says so rather than letting
             someone assume the money has left. That asymmetry is the core
             financial invariant of this app and the form is where a user meets
             it, so it is labelled here and not only in the audit trail.

             Both forms carry the loader by default: a double-submitted tithe
             is a real, wrong ledger row. --}}
        <div class="cf-grid cf-grid--2" style="margin-bottom:1.2rem">
            <x-form.card :title="'Record income — tithe, offering or donation'"
                description="Income posts to the account immediately and cannot be edited afterwards; a mistake is voided with a reason, which leaves the original row intact."
                :action="route('finance.transactions.income', $account)" submit="Record income" submit-icon="wallet" layout="inline"
                data-cf-loading-text="Recording income…">
                <x-form.input name="amount" label="Amount ({{ $symbol }})" type="number" step="0.01"
                    min="0.01" required placeholder="0.00" hint="Must be greater than zero." />

                <x-form.select name="category" label="Type" :value="'tithe'" :options="[
                    'tithe' => 'Tithe',
                    'offering' => 'Offering',
                    'donation' => 'Donation',
                    'pledge' => 'Pledge redemption',
                    'thanksgiving' => 'Thanksgiving',
                    'building_fund' => 'Building fund',
                    'missions' => 'Missions',
                    'other_income' => 'Other income',
                ]"
                    hint="Used by the budget and giving reports." />

                <x-form.input name="transacted_on" label="Date received" type="date" :value="now()->toDateString()" required />

                <x-form.select name="member_id" label="Member (optional)" placeholder="Not linked to a member"
                    :options="$members
                        ->map(
                            fn($m) => [
                                'value' => $m->id,
                                'label' => $m->full_name . ' (' . $m->membership_number . ')',
                            ],
                        )
                        ->all()" hint="Linking a gift to a member makes giving statements possible later." />

                <x-form.textarea name="description" label="Note" rows="2" wide
                    placeholder="e.g. Sunday first service tithe" />
            </x-form.card>

            <x-form.card title="Record an expense"
                description="An expense does NOT reduce the balance until it is approved. It is created pending and enters the approval queue immediately — the balance above excludes it until then."
                :action="route('finance.transactions.expense', $account)" submit="Submit for approval" layout="inline"
                data-cf-loading-text="Submitting for approval…">
                <x-form.input name="amount" label="Amount ({{ $symbol }})" type="number" step="0.01"
                    min="0.01" required placeholder="0.00" />
                <x-form.input name="transacted_on" label="Date" type="date" :value="now()->toDateString()" required />

                <x-form.input name="category" label="Category" placeholder="e.g. Utilities, Maintenance" />

                <x-form.textarea name="description" label="What was this for" rows="2" wide required
                    placeholder="An approver who cannot tell what this was for will have to ask." />
            </x-form.card>
        </div>

        @if ($accounts = \App\Models\FinancialAccount::query()->where('is_active', true)->where('id', '!=', $account->id)->orderBy('name')->get())
            @if ($accounts->isNotEmpty())
                <div style="margin-bottom:1.2rem">
                    <x-form.card title="Transfer between accounts"
                        description="Recorded as two linked rows sharing one transfer group — never a single row that quietly touches two balances."
                        :action="route('finance.transactions.transfer')" submit="Transfer" layout="inline" data-cf-loading-text="Transferring…">
                        <input type="hidden" name="from_account_id" value="{{ $account->id }}">

                        <x-form.select name="to_account_id" label="Transfer to" required placeholder="Choose an account"
                            :options="$accounts->map(fn($a) => ['value' => $a->id, 'label' => $a->name])->all()" />

                        <x-form.input name="amount" label="Amount ({{ $symbol }})" type="number" step="0.01"
                            min="0.01" required />
                        <x-form.input name="transacted_on" label="Date" type="date" :value="now()->toDateString()" required />
                        <x-form.textarea name="description" label="Reason" rows="2" wide />
                    </x-form.card>
                </div>
            @endif
        @endif
    @endif

    {{-- The ledger. A searchable grid rather than a bare table: this is the
         list that grows without limit, and it is the one people scan for a
         single row ("the ₦50,000 on the 12th"). --}}
    <h2 class="cf-small cf-muted"
        style="margin:0 0 .8rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em">
        Transaction ledger
    </h2>

    @php
        $columns = [
            [
                'key' => 'transacted_on',
                'label' => 'Date',
                'search' => false,
                'render' => fn($t) => '<span class="cf-mono cf-small">' .
                    e($t->transacted_on?->format('d M Y')) .
                    '</span>',
            ],
            [
                'key' => 'type',
                'label' => 'Type',
                'render' => fn($t) => '<span class="cf-badge ' .
                    ($t->type === 'income' ? 'cf-badge--ok' : ($t->type === 'expense' ? 'cf-badge--danger' : '')) .
                    '">' .
                    e(ucfirst($t->type)) .
                    '</span>',
            ],
            [
                'key' => 'category',
                'label' => 'Category',
                'render' => fn($t) => e($t->category ? str_replace('_', ' ', ucfirst($t->category)) : '—'),
            ],
            [
                'key' => 'description',
                'label' => 'Note',
                'render' => fn($t) => e(\Illuminate\Support\Str::limit($t->description ?? '—', 60)),
            ],
            [
                'key' => 'approval_status',
                'label' => 'Status',
                'render' => function ($t) {
                    $tone = match ($t->approval_status) {
                        'approved', 'not_required' => 'cf-badge--ok',
                        'rejected' => 'cf-badge--danger',
                        default => 'cf-badge--warn',
                    };
                    $label = $t->is_voided ?? false ? 'Voided' : str_replace('_', ' ', ucfirst($t->approval_status));

                    return '<span class="cf-badge ' . $tone . '">' . e($label) . '</span>';
                },
            ],
            [
                'key' => 'amount',
                'label' => 'Amount',
                'align' => 'right',
                'search' => false,
                'render' => function ($t) use ($symbol) {
                    $isCredit = $t->type === 'income';
                    $colour = $isCredit
                        ? 'color:var(--ok-ink,#047857)'
                        : ($t->type === 'expense'
                            ? 'color:var(--danger,#b91c1c)'
                            : '');

                    return '<span class="cf-mono" style="white-space:nowrap;' .
                        $colour .
                        '">' .
                        ($isCredit ? '+' : ($t->type === 'expense' ? '−' : '')) .
                        $symbol .
                        number_format((float) $t->amount, 2) .
                        '</span>';
                },
            ],
        ];
    @endphp

    <x-grid.data-table :rows="$transactions" :columns="$columns"
        search-placeholder="Filter this page — amount, category, note…" :empty="$hasTransactions
            ? 'No transactions match the current filters.'
            : 'No transactions on this account yet.'" :empty-hint="$hasTransactions ? null : 'Record income or an expense above and they will appear here.'"
        :counter="number_format($transactions->total()) . ' transactions'">
        <x-slot:rowActions>
            @if ($canRecord)
                @php
                    $rowActions = fn($t) => $t->is_voided ?? false
                        ? '<span class="cf-tiny cf-muted">voided</span>'
                        : '<form method="POST" action="' .
                            route('finance.transactions.void', $t) .
                            '" data-confirm="Void this transaction? The row stays in the ledger and a reason is required." data-confirm-title="Void transaction?" data-confirm-ok="Void" style="display:inline" data-cf-no-loading>' .
                            csrf_field() .
                            '<input type="hidden" name="reason" value="Corrected in error">' .
                            '<button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Void</button>' .
                            '</form>';
                @endphp
            @else
                @php $rowActions = fn ($t) => ''; @endphp
            @endif
        </x-slot:rowActions>
    </x-grid.data-table>
</x-layout>

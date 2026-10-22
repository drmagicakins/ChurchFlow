<x-layout title="Loans · Finance · ChurchFlow">
    @php
        $canManage = auth()->user()->hasPermission('loans.manage');
        $symbol = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€'][$church?->currency ?? 'NGN'] ?? '';
        $branches = \App\Models\OrganizationalUnit::query()->orderBy('name')->get();
        $members = \App\Models\Member::query()->orderBy('full_name')->limit(500)->get();
        // A pastor who does not manage finance may still REQUEST a loan; the
        // request is created pending, against their own branch.
        $canRequest = ! $canManage;
        $myBranch = auth()->user()?->organizationalUnit;
    @endphp

    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin:0"><a href="{{ route('finance.accounts.index') }}">← Financial accounts</a></p>
            <h1 style="margin:.35rem 0 0">Loans</h1>
            <p class="cf-small cf-muted" style="margin-top:.35rem">
                Money lent to a branch or a member, repaid in monthly deductions. Outstanding balances are
                derived from the payment ledger — never a stored figure that can drift.
            </p>
        </div>
        @if($canManage)
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="loan-create" aria-expanded="false">
                <x-ui.icon name="naira" class="h-4 w-4" /> New Loan
            </button>
        @else
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="loan-create" aria-expanded="false">
                <x-ui.icon name="naira" class="h-4 w-4" /> Request a Loan
            </button>
        @endif
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    @if($errors->any())
        <div class="cf-alert cf-alert--error" role="alert">
            <span class="cf-alert__icon"><x-ui.icon name="alert" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">There is a problem with your submission.</p>
                <ul class="cf-alert__list">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    @if($canManage || $canRequest)
        <div data-panel="loan-create" hidden style="margin-bottom:1.2rem">
            @if($canManage)
                <x-form.card
                    title="Record a new loan"
                    description="A loan belongs to exactly one borrower — a branch or a member, not both."
                    :action="route('finance.loans.store')"
                    submit="Create loan"
                    submit-icon="naira"
                    layout="inline"
                >
                    @if($branches->isNotEmpty())
                        <x-form.select name="organizational_unit_id" label="Borrowing branch" placeholder="— or choose a member below —"
                            :options="$branches->map(fn ($b) => ['value' => $b->id, 'label' => $b->name])->all()" />
                    @endif

                    <x-form.select name="member_id" label="Borrowing member" placeholder="— or choose a branch above —"
                        :options="$members->map(fn ($m) => ['value' => $m->id, 'label' => $m->full_name . ' (' . $m->membership_number . ')'])->all()" />

                    <x-form.input name="principal_amount" :label="'Principal ('.$symbol.')'" type="number" step="0.01" min="0.01" required placeholder="0.00" />
                    <x-form.input name="monthly_deduction" :label="'Monthly deduction ('.$symbol.')'" type="number" step="0.01" min="0.01" required placeholder="0.00"
                        hint="How much is recovered each month." />
                    <x-form.input name="interest_rate" label="Interest rate (%)" type="number" step="0.01" min="0" :value="'0'"
                        hint="Optional. 0 for an interest-free welfare loan." />
                    <x-form.textarea name="notes" label="Notes" rows="2" wide />
                </x-form.card>
            @else
                {{-- The borrower is the signed-in pastor's own branch, decided
                     on the server. Shown read-only so the request is unambiguous. --}}
                <x-form.card
                    title="Request a loan"
                    description="Your request goes to your church's finance team with a pending status. Nothing is lent until it is approved."
                    :action="route('finance.loans.store')"
                    submit="Submit request"
                    submit-icon="naira"
                    layout="inline"
                >
                    <div class="cf-field">
                        <span class="cf-label">Requesting for</span>
                        <p class="cf-small" style="margin:.35rem 0 0;font-weight:600">
                            {{ $myBranch?->name ?? 'Your own record' }}
                        </p>
                        <p class="cf-hint" style="margin:.2rem 0 0">Taken from your account — a pastor requests for their own branch.</p>
                    </div>

                    <x-form.input name="principal_amount" :label="'Amount requested ('.$symbol.')'" type="number" step="0.01" min="0.01" required placeholder="0.00" />
                    <x-form.input name="monthly_deduction" :label="'Monthly repayment ('.$symbol.')'" type="number" step="0.01" min="0.01" required placeholder="0.00"
                        hint="How much should be recovered each month once approved." />
                    <x-form.input name="interest_rate" label="Interest rate (%)" type="number" step="0.01" min="0" :value="'0'"
                        hint="Optional. 0 for an interest-free welfare loan." />
                    <x-form.textarea name="notes" label="Reason for the request" rows="2" wide
                        placeholder="A short note for the finance team." />
                </x-form.card>
            @endif
        </div>
    @endif

    <div class="cf-card cf-card--flush">
        @if($loans->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No loans yet</p>
                <p class="cf-small cf-muted">
                    @if($canManage)
                        Record your first loan above. Repayments are logged against it from its own page.
                    @else
                        Use <strong>Request a Loan</strong> above; approved loans will appear here.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Borrower</th>
                            <th style="text-align:right">Principal</th>
                            <th style="text-align:right">Outstanding</th>
                            <th style="text-align:right">Monthly</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($loans as $loan)
                            @php $outstanding = $loanService->outstandingBalance($loan); @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('finance.loans.show', $loan) }}">{{ $loan->borrowerLabel() }}</a>
                                    <div class="cf-tiny cf-muted">
                                        {{-- Was `member_id ? 'Member' : 'Branch'`, which
                                             labelled a row with NEITHER as "Branch" — the
                                             exact shape of the old borrowerless rows, and it
                                             would also mislabel a request whose branch link
                                             was removed later. --}}
                                        {{ $loan->member_id ? 'Member' : ($loan->organizational_unit_id ? 'Branch' : 'No borrower') }}
                                    </div>
                                </td>
                                <td class="cf-mono" style="text-align:right;white-space:nowrap">{{ $symbol }}{{ number_format((float) $loan->principal_amount, 2) }}</td>
                                <td class="cf-mono" style="text-align:right;white-space:nowrap">{{ $symbol }}{{ number_format((float) $outstanding, 2) }}</td>
                                <td class="cf-mono" style="text-align:right;white-space:nowrap">{{ $symbol }}{{ number_format((float) $loan->monthly_deduction, 2) }}</td>
                                <td>
                                    @php $tone = match ($loan->status) {
                                        'closed' => 'cf-badge--ok',
                                        'defaulted' => 'cf-badge--danger',
                                        'pending' => 'cf-badge--warn',
                                        default => 'cf-badge--brand',
                                    }; @endphp
                                    <span class="cf-badge {{ $tone }}">{{ $loan->status === 'pending' ? 'Awaiting approval' : ucfirst($loan->status) }}</span>
                                </td>
                                <td style="text-align:right">
                                    <a href="{{ route('finance.loans.show', $loan) }}" class="cf-btn cf-btn--secondary cf-btn--sm">Open</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($loans->hasPages())
        <div style="padding:1rem 1.35rem">{{ $loans->links() }}</div>
    @endif

    @push('scripts')
        <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
            (function () {
                var btn = document.querySelector('[data-toggle="loan-create"]');
                var panel = document.querySelector('[data-panel="loan-create"]');
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

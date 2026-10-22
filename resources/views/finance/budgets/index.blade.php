<x-layout title="Budgets · Finance · ChurchFlow">
    @php
        $canManage = auth()->user()->hasPermission('budgets.manage');
        $symbol = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€'][$church?->currency ?? 'NGN'] ?? '';
        $accounts = \App\Models\FinancialAccount::query()->where('is_active', true)->orderBy('name')->get();
    @endphp

    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin:0"><a href="{{ route('finance.accounts.index') }}">← Financial accounts</a></p>
            <h1 style="margin:.35rem 0 0">Budgets</h1>
            <p class="cf-small cf-muted" style="margin-top:.35rem">
                A plan for a period, compared against what was actually spent. Actuals come from approved
                expenses — never a figure typed in by hand.
            </p>
        </div>
        @if($canManage)
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="budget-create" aria-expanded="false">
                <x-ui.icon name="pie" class="h-4 w-4" /> New Budget
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
                <ul class="cf-alert__list">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if($canManage)
        <div data-panel="budget-create" hidden style="margin-bottom:1.2rem">
            <x-form.card
                title="Create a budget"
                description="Name the budget, choose its period, then add one line per spending category. Each category is matched against approved expenses of the same name."
                :action="route('finance.budgets.store')"
                submit="Create budget"
                submit-icon="pie"
                layout="inline"
            >
                <x-form.input name="name" label="Budget name" required placeholder="e.g. 2026 Operating Budget" />

                <x-form.select name="financial_account_id" label="Account" placeholder="All accounts"
                    :options="$accounts->map(fn ($a) => ['value' => $a->id, 'label' => $a->name])->all()"
                    hint="Optional. Restrict actuals to one account, or leave blank to count every account." />

                <x-form.input name="period_start" label="Period starts" type="date" :value="now()->startOfYear()->toDateString()" required />
                <x-form.input name="period_end" label="Period ends" type="date" :value="now()->endOfYear()->toDateString()" required />

                <div class="cf-small cf-muted" style="grid-column:1/-1;margin-top:.4rem">
                    <strong>Planned lines.</strong> Add the categories you are budgeting for. You can add more later by editing the budget.
                </div>

                @for($i = 0; $i < 3; $i++)
                    <x-form.input :name="'items['.$i.'][category]'" label="Category {{ $i + 1 }}" placeholder="e.g. Utilities" />
                    <x-form.input :name="'items['.$i.'][planned_amount]'" :label="'Planned amount ('.$symbol.')'" type="number" step="0.01" min="0" placeholder="0.00" />
                @endfor
            </x-form.card>
        </div>
    @endif

    <div class="cf-card cf-card--flush">
        @if($budgets->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No budgets yet</p>
                <p class="cf-small cf-muted">
                    @if($canManage)
                        Create your first budget above to track planned spending against actuals.
                    @else
                        Budgets created by your finance team will appear here.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr><th>Budget</th><th>Period</th><th>Account</th><th>Status</th><th style="text-align:right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach($budgets as $b)
                            <tr>
                                <td><a href="{{ route('finance.budgets.show', $b) }}">{{ $b->name }}</a></td>
                                <td class="cf-small cf-muted" style="white-space:nowrap">
                                    {{ $b->period_start?->format('d M Y') }} – {{ $b->period_end?->format('d M Y') }}
                                </td>
                                <td class="cf-small cf-muted">{{ $b->financialAccount?->name ?? 'All accounts' }}</td>
                                <td>
                                    @php $tone = match ($b->status) {
                                        'active' => 'cf-badge--ok',
                                        'closed' => 'cf-badge--danger',
                                        default => 'cf-badge--warn',
                                    }; @endphp
                                    <span class="cf-badge {{ $tone }}">{{ ucfirst($b->status) }}</span>
                                </td>
                                <td style="text-align:right">
                                    <a href="{{ route('finance.budgets.show', $b) }}" class="cf-btn cf-btn--secondary cf-btn--sm">Open</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($budgets->hasPages())
        <div style="padding:1rem 1.35rem">{{ $budgets->links() }}</div>
    @endif

    @push('scripts')
        <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
            (function () {
                var btn = document.querySelector('[data-toggle="budget-create"]');
                var panel = document.querySelector('[data-panel="budget-create"]');
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

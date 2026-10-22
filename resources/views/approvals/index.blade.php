<x-layout title="Approvals · ChurchFlow">
    @php
        $symbol = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€'][$church?->currency ?? 'NGN'] ?? '';
        $money = fn ($v) => $symbol . number_format((float) $v, 2);

        // A short, human label + the amount for whatever is being approved,
        // so an approver sees WHAT they are approving rather than a class name.
        $describe = function ($approval) use ($money) {
            $item = $approval->approvable;
            if (! $item) {
                return ['title' => 'Record unavailable', 'detail' => 'The underlying record no longer exists.', 'amount' => null];
            }

            return match (class_basename($item)) {
                'Transaction' => [
                    'title' => 'Expense — ' . ($item->category ? str_replace('_', ' ', ucfirst($item->category)) : 'Uncategorised'),
                    'detail' => ($item->description ?: 'No note provided.'),
                    'amount' => $money($item->amount),
                ],
                'SubventionSubmission' => [
                    'title' => 'Subvention — ' . ($item->organizationalUnit?->name ?? 'Branch'),
                    'detail' => 'Remittance for ' . ($item->period?->name ?? 'the period'),
                    'amount' => $item->latestCalculation ? $money($item->latestCalculation->remittance_amount) : null,
                ],
                default => [
                    'title' => class_basename($item) . ' #' . $item->getKey(),
                    'detail' => 'Pending approval.',
                    'amount' => null,
                ],
            };
        };
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>Approvals</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Everything waiting on a decision — expenses submitted by your team, and subventions sent for review.
                An approved expense reduces the account balance; a pending one does not.
            </p>
        </div>
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

    <div class="cf-card cf-card--flush" style="margin-bottom:1.2rem">
        <div class="cf-card__head">
            <h2>Awaiting your decision</h2>
            <span class="cf-tiny cf-muted">{{ $approvals->total() }} {{ \Illuminate\Support\Str::plural('item', $approvals->total()) }}</span>
        </div>

        @if($approvals->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">Nothing pending approval</p>
                <p class="cf-small cf-muted">When an expense or a subvention is submitted, it appears here for a decision.</p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Submitted by</th>
                            <th style="text-align:right">Amount</th>
                            <th style="text-align:right">Decision</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($approvals as $approval)
                            @php $d = $describe($approval); @endphp
                            <tr>
                                <td>
                                    <strong>{{ $d['title'] }}</strong>
                                    <div class="cf-tiny cf-muted">{{ \Illuminate\Support\Str::limit($d['detail'], 90) }}</div>
                                </td>
                                <td class="cf-small cf-muted">
                                    {{ $approval->submitter?->name ?? '—' }}
                                    @if($approval->submitted_at)
                                        <div class="cf-tiny cf-muted">{{ $approval->submitted_at->diffForHumans() }}</div>
                                    @endif
                                </td>
                                <td class="cf-mono" style="text-align:right;white-space:nowrap">{{ $d['amount'] ?? '—' }}</td>
                                <td style="text-align:right;white-space:nowrap">
                                    <form method="POST" action="{{ route('approvals.approve', $approval) }}" style="display:inline">
                                        @csrf
                                        <input type="hidden" name="comments" value="">
                                        <button type="submit" class="cf-btn cf-btn--primary cf-btn--sm">Approve</button>
                                    </form>

                                    <details class="cf-menu" style="display:inline-block;vertical-align:middle;margin-left:.35rem">
                                        <summary class="cf-btn cf-btn--secondary cf-btn--sm" style="list-style:none">Reject</summary>
                                        <div class="cf-menu__panel">
                                            <form method="POST" action="{{ route('approvals.reject', $approval) }}" class="cf-form">
                                                @csrf
                                                <label class="cf-label" for="reject-{{ $approval->id }}">Reason (required)</label>
                                                <textarea name="comments" id="reject-{{ $approval->id }}" class="cf-input" rows="2" required maxlength="2000"></textarea>
                                                <button type="submit" class="cf-btn cf-btn--danger cf-btn--sm cf-btn--block" style="margin-top:.5rem">Reject</button>
                                            </form>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($approvals->hasPages())
        <div style="padding:0 1.35rem 1.2rem">{{ $approvals->links() }}</div>
    @endif

    @if($recent->isNotEmpty())
        <div class="cf-card cf-card--flush">
            <div class="cf-card__head">
                <h2>Recently decided</h2>
                <span class="cf-tiny cf-muted">Last {{ $recent->count() }}</span>
            </div>
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr><th>Item</th><th>Decided by</th><th>Outcome</th><th style="text-align:right">When</th></tr>
                    </thead>
                    <tbody>
                        @foreach($recent as $approval)
                            @php $d = $describe($approval); @endphp
                            <tr>
                                <td>{{ $d['title'] }}</td>
                                <td class="cf-small cf-muted">{{ $approval->decider?->name ?? '—' }}</td>
                                <td>
                                    <span class="cf-badge {{ $approval->status === 'approved' ? 'cf-badge--ok' : 'cf-badge--danger' }}">
                                        {{ ucfirst($approval->status) }}
                                    </span>
                                    @if($approval->comments)
                                        <div class="cf-tiny cf-muted">{{ \Illuminate\Support\Str::limit($approval->comments, 60) }}</div>
                                    @endif
                                </td>
                                <td class="cf-small cf-muted" style="text-align:right;white-space:nowrap">{{ $approval->decided_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <style>
        .cf-menu { position: relative; display: inline-block; }
        .cf-menu > summary { cursor: pointer; }
        .cf-menu > summary::-webkit-details-marker { display: none; }
        .cf-menu__panel {
            position: absolute; right: 0; z-index: 5; margin-top: .35rem;
            background: var(--surface, #fff); border: 1px solid var(--line, #e5e7eb);
            border-radius: .6rem; padding: .8rem; width: 17rem;
            box-shadow: 0 18px 40px -18px rgba(15, 23, 42, .45); text-align: left;
        }
    </style>
</x-layout>

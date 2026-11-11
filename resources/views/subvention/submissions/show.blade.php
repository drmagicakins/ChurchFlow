<x-layout :title="($submission->organizationalUnit?->name ?? 'Submission').' · Subvention · ChurchFlow'">
    @php
        $symbol = '₦';
        $money = fn ($v) => $symbol.number_format((float) $v, 2);
        $calc = $submission->latestCalculation;

        // Calculations are versioned and never rewritten, so a row stored
        // before the shortfall direction was corrected still holds
        // (deductions − retention). The model reads the cut-off from the
        // database and presents the figure the right way round.
        $shortfall = $calc ? $calc->shortfallInCurrentDirection() : 0.0;

        $canEdit = auth()->user()->can('update', $submission);
        $canReview = auth()->user()->can('review', $submission);

        // The figures a submission records, in the order the RCCG reference
        // collects them. Anything the church stored that is not in this list is
        // still shown below under "Other figures", so a custom rule's base
        // field is never silently dropped from the record.
        $knownFigures = [
            'general_tithe' => 'General tithe',
            'minister_tithe' => 'Minister tithe',
            'offering' => 'Offerings',
            'special_offering' => 'Special / thanksgiving',
            'admin_base' => 'Administrative base',
            'salary' => 'Salary total',
        ];
        $figures = $submission->figures ?? [];
        $otherFigures = array_diff_key($figures, $knownFigures);

        $statusTone = match ($submission->status) {
            'approved' => 'cf-badge--ok',
            'rejected' => 'cf-badge--danger',
            'returned' => 'cf-badge--warn',
            'submitted', 'under_review' => 'cf-badge--brand',
            default => '',
        };
    @endphp

    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin:0"><a href="{{ route('subvention.submissions.index') }}">← All submissions</a></p>
            <h1 style="margin:.35rem 0 0">{{ $submission->organizationalUnit?->name ?? 'Branch' }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.35rem">
                {{ $submission->period?->name ?? '—' }}
                · rule set <strong>{{ $submission->ruleSet?->name ?? '—' }}</strong>
                <span class="cf-badge {{ $statusTone }}" style="margin-left:.5rem">{{ str_replace('_', ' ', ucfirst($submission->status)) }}</span>
            </p>
        </div>
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    @if($submission->status === 'approved')
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">Approved by the registrar</p>
                <p class="cf-small" style="margin:.3rem 0 0">
                    This submission has been reviewed and approved.
                    @if($submission->reviewed_at) · {{ $submission->reviewed_at?->format('d M Y, H:i') }} @endif
                </p>
            </div>
        </div>
    @endif

    @if($submission->status === 'returned' && $submission->return_reason)
        <div class="cf-alert cf-alert--warn" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="info" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">Returned for revision</p>
                <p class="cf-small" style="margin:.3rem 0 0">{{ $submission->return_reason }}</p>
            </div>
        </div>
    @endif

    <div class="cf-grid cf-grid--2">
        {{-- Figures collected --}}
        <div class="cf-card">
            <div class="cf-card__head">
                <h2>Figures for the period</h2>
                <span class="cf-tiny cf-muted">What the branch recorded</span>
            </div>
            <div class="cf-card__body">
                <table class="cf-table" style="width:100%">
                    <tbody>
                        @foreach($knownFigures as $field => $label)
                            <tr>
                                <th scope="row" style="text-align:left;font-weight:500">{{ $label }}</th>
                                <td class="cf-mono" style="text-align:right;white-space:nowrap">{{ $money($figures[$field] ?? 0) }}</td>
                            </tr>
                        @endforeach

                        @foreach($otherFigures as $field => $value)
                            <tr>
                                <th scope="row" style="text-align:left;font-weight:500">{{ ucfirst(str_replace('_', ' ', $field)) }}</th>
                                <td class="cf-mono" style="text-align:right;white-space:nowrap">{{ $money($value) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($canEdit)
                <div class="cf-card__body" style="border-top:1px solid var(--line, #e5e7eb)">
                    <x-form.card title="Update figures"
                        description="Editing is only possible while the submission is a draft or has been returned. Saving records new inputs; it does not recalculate until you submit for review."
                        :action="route('subvention.submissions.update', $submission)" method="PUT" submit="Save figures"
                        submit-icon="wallet" layout="inline">
                        @foreach($knownFigures as $field => $label)
                            <x-form.input :name="'figures['.$field.']'" :label="$label" type="number" step="0.01"
                                min="0" :value="(string) ($figures[$field] ?? 0)" />
                        @endforeach
                    </x-form.card>
                </div>
            @endif
        </div>

        {{-- Calculation breakdown --}}
        <div style="display:grid;gap:1.2rem">
            @if($calc)
                <div class="cf-card">
                    <div class="cf-card__head">
                        <h2>Calculation</h2>
                        <span class="cf-tiny cf-muted">Computed {{ $calc->calculated_at?->diffForHumans() }}</span>
                    </div>
                    <div class="cf-card__body">
                        <table class="cf-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th style="text-align:left">Rule</th>
                                    <th style="text-align:left">Type</th>
                                    <th style="text-align:right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(($calc->breakdown ?? []) as $row)
                                    <tr>
                                        <td>{{ $row['name'] ?? '—' }}</td>
                                        <td>
                                            @php $tone = ($row['classification'] ?? '') === 'retention' ? 'cf-badge--ok' : 'cf-badge--warn'; @endphp
                                            <span class="cf-badge {{ $tone }}">{{ ucfirst($row['classification'] ?? '—') }}</span>
                                        </td>
                                        <td class="cf-mono" style="text-align:right;white-space:nowrap">{{ $money($row['amount'] ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="2" style="text-align:left">Retention total</th>
                                    <td class="cf-mono" style="text-align:right;white-space:nowrap">{{ $money($calc->retention_total) }}</td>
                                </tr>
                                <tr>
                                    <th colspan="2" style="text-align:left">Deduction total</th>
                                    <td class="cf-mono" style="text-align:right;white-space:nowrap">{{ $money($calc->deduction_total) }}</td>
                                </tr>
                                <tr>
                                    <th colspan="2" style="text-align:left">Shortfall <span class="cf-tiny cf-muted">(retention − deductions)</span></th>
                                    <td class="cf-mono" style="text-align:right;white-space:nowrap">{{ $money($shortfall) }}</td>
                                </tr>
                                @if(bccomp((string) $calc->loan_deduction_applied, '0', 2) > 0)
                                    <tr>
                                        <th colspan="2" style="text-align:left">Loan deduction applied</th>
                                        <td class="cf-mono" style="text-align:right;white-space:nowrap">−{{ $money($calc->loan_deduction_applied) }}</td>
                                    </tr>
                                @endif
                                <tr style="font-weight:700">
                                    <th colspan="2" style="text-align:left">Remittance due to HQ</th>
                                    <td class="cf-mono" style="text-align:right;white-space:nowrap">{{ $money($calc->remittance_amount) }}</td>
                                </tr>
                            </tfoot>
                        </table>

                        @if($shortfall < 0)
                            <p class="cf-small cf-muted" style="margin:.8rem 0 0">
                                The branch's deductions exceeded its retention, so nothing is payable to HQ this period.
                            </p>
                        @elseif($shortfall == 0.0)
                            <p class="cf-small cf-muted" style="margin:.8rem 0 0">
                                Retention exactly covered the branch's deductions, so nothing is payable to HQ this period.
                            </p>
                        @endif
                    </div>
                </div>
            @else
                <div class="cf-card">
                    <div class="cf-card__head"><h2>Calculation</h2></div>
                    <div class="cf-card__body">
                        <p class="cf-small cf-muted" style="margin:0">
                            No calculation yet. One is produced automatically the first time this submission is
                            sent for review.
                        </p>
                    </div>
                </div>
            @endif

            <div class="cf-card">
                <div class="cf-card__head"><h2>Workflow</h2></div>
                <div class="cf-card__body" style="display:grid;gap:.9rem">
                    @php
                        $approval = $submission->approval;
                    @endphp

                    @if($submission->status === 'draft' && $canEdit)
                        <form method="POST" action="{{ route('subvention.submissions.submit', $submission) }}">
                            @csrf
                            <button type="submit" class="cf-btn cf-btn--primary">
                                <x-ui.icon name="check" class="h-4 w-4" /> Submit for review
                            </button>
                            <p class="cf-tiny cf-muted" style="margin:.5rem 0 0">Submitting calculates the figures and opens an approval. It cannot be edited after this until it is returned.</p>
                        </form>
                    @elseif($submission->status === 'returned' && $canEdit)
                        <form method="POST" action="{{ route('subvention.submissions.reopen', $submission) }}">
                            @csrf
                            <button type="submit" class="cf-btn cf-btn--primary">
                                <x-ui.icon name="wallet" class="h-4 w-4" /> Reopen as draft
                            </button>
                            <p class="cf-tiny cf-muted" style="margin:.5rem 0 0">Reopening lets you correct the figures, then submit again.</p>
                        </form>
                    @endif

                    @if($canReview && in_array($submission->status, ['submitted', 'under_review'], true))
                        @if($submission->status === 'submitted')
                            <form method="POST" action="{{ route('subvention.submissions.review', $submission) }}">
                                @csrf
                                <button type="submit" class="cf-btn cf-btn--secondary">Mark under review</button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('subvention.submissions.approve', $submission) }}" class="cf-form">
                            @csrf
                            <div class="cf-field">
                                <label class="cf-label" for="comments">Approval comment <span class="cf-tiny cf-muted">(optional)</span></label>
                                <input type="text" name="comments" id="comments" class="cf-input" maxlength="2000">
                            </div>
                            <button type="submit" class="cf-btn cf-btn--primary">Approve</button>
                        </form>

                        <form method="POST" action="{{ route('subvention.submissions.return', $submission) }}" class="cf-form">
                            @csrf
                            <x-form.textarea name="reason" label="Return for revision" required maxlength="2000" rows="2"
                                hint="Explain what needs correcting. The branch can edit and resubmit." />
                            <button type="submit" class="cf-btn cf-btn--secondary">Return to branch</button>
                        </form>

                        <form method="POST" action="{{ route('subvention.submissions.reject', $submission) }}" class="cf-form">
                            @csrf
                            <x-form.textarea name="reason" label="Reject" required maxlength="2000" rows="2"
                                hint="A rejection is final for this submission." />
                            <button type="submit" class="cf-btn cf-btn--secondary">Reject</button>
                        </form>
                    @endif

                    @if($approval)
                        <div style="border-top:1px solid var(--line, #e5e7eb);padding-top:.9rem">
                            <p class="cf-tiny cf-muted" style="margin:0">Approval record</p>
                            <p class="cf-small" style="margin:.3rem 0 0">
                                Status: <span class="cf-badge {{ $approval->status === 'approved' ? 'cf-badge--ok' : ($approval->status === 'rejected' ? 'cf-badge--danger' : 'cf-badge--warn') }}">{{ ucfirst($approval->status) }}</span>
                            </p>
                        </div>
                    @endif

                    @if($submission->submitter)
                        <p class="cf-tiny cf-muted" style="margin:0">
                            Submitted by {{ $submission->submitter->name }}
                            @if($submission->submitted_at) · {{ $submission->submitted_at?->diffForHumans() }} @endif
                        </p>
                    @endif

                    @if($submission->reviewer)
                        <p class="cf-tiny cf-muted" style="margin:0">
                            Last reviewed by {{ $submission->reviewer->name }}
                            @if($submission->reviewed_at) · {{ $submission->reviewed_at?->diffForHumans() }} @endif
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layout>

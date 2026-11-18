{{--
    A subvention decision, in the church's own email frame.

    The decision is stated as a BADGE and then in a sentence, because it is the
    one fact the recipient opened the message for. Branch, period, reason and
    amount are a panel underneath — the same facts the plain version carried,
    only scannable.

    The parish name is read null-safely: a parish can be removed after the
    decision, and a missing relation must not crash the queued mail.
--}}
@php
    $branchName = $submission->organizationalUnit?->name ?? 'your parish';
    $periodName = $submission->period?->name;
    $tone = match ($decision) {
        'approved' => ['#e8f7ee', '#166534'],
        'rejected' => ['#fdecec', '#991b1b'],
        'returned' => ['#fff7e6', '#92400e'],
        default => ['#eef2ff', '#3730a3'],
    };
@endphp

@extends('emails.layouts.church', [
    'subject' => 'Subvention ' . ucfirst($decision) . ': ' . $branchName,
    'preheader' => $branchName . ($periodName ? ' — ' . $periodName : '') . ' has been ' . $decision . '.',
    'church' => $church ?? $submission->church,
])

@section('hero')
    <div
        style="display:inline-block;padding:4px 10px;border-radius:999px;background:{{ $tone[0] }};color:{{ $tone[1] }};font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:12px;">
        Subvention {{ $decision }}
    </div>
    <h1 class="cf-email-title" style="margin:0;font-size:23px;line-height:1.3;color:#0f172a;font-weight:700;">
        Your submission has been {{ $decision }}.
    </h1>
@endsection

@section('content')
    <p style="margin:0;font-size:15px;line-height:1.7;color:#0f172a;">
        Your subvention submission for <strong>{{ $branchName }}</strong>
        @if ($periodName)
            ({{ $periodName }})
        @endif
        has been
        <strong>{{ $decision }}</strong>.
    </p>

    @include('emails.components.panel', [
        'rows' => array_filter([
            'Parish' => $branchName,
            'Period' => $periodName,
            'Decision' => ucfirst($decision),
            'Reason' => $decision === 'returned' ? $submission->return_reason : null,
            'Remittance due' =>
                $decision === 'approved' && $submission->latestCalculation
                    ? $submission->latestCalculation->remittance_amount
                    : null,
            'Decided' => $submission->reviewed_at?->format('l, j F Y'),
        ]),
    ])

    @if ($decision === 'returned')
        <p style="margin:0;font-size:15px;line-height:1.7;color:#0f172a;">
            Please correct the figures and submit again.
        </p>
    @endif
@endsection

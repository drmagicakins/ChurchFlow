{{--
    Event reminder, in the church's own email frame.

    The facts (when, where, party size) are a PANEL rather than a bulleted list:
    at a glance a reminder has to answer "when and where?", and a two-column
    panel does that on a phone better than prose. Everything the template used
    to receive is still received and still shown — the party-size line remains
    visitor-only, because a church member has no `$registration` row.

    $recipientName is set for BOTH kinds of recipient; $registration is only
    present for a visitor from the public form.
--}}
@extends('emails.layouts.church', [
    'subject' => 'Reminder: ' . $event->title,
    'preheader' => ($when ?: 'Coming up') . ($event->venue ? ' · ' . $event->venue : ''),
    'church' => $church ?? $event->church,
])

@section('hero')
    <div
        style="display:inline-block;padding:4px 10px;border-radius:999px;background:#eef2ff;color:#4f46e5;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:12px;">
        Event reminder
    </div>
    <h1 class="cf-email-title" style="margin:0;font-size:25px;line-height:1.3;color:#0f172a;font-weight:700;">
        {{ $event->title }}
    </h1>
@endsection

@section('content')
    <p style="margin:0 0 6px;font-size:15px;line-height:1.7;color:#0f172a;">
        Hello {{ $recipientName ?? ($registration->name ?? 'there') }},
    </p>

    <p style="margin:0;font-size:15px;line-height:1.7;color:#0f172a;">
        This is a reminder that you are registered for <strong>{{ $event->title }}</strong>.
    </p>

    @include('emails.components.panel', [
        'rows' => array_filter([
            'When' => $when,
            'Where' => $event->venue,
            'Your party' =>
                $registration && $registration->guests > 0
                    ? $registration->headcount() . ' people (including you)'
                    : null,
        ]),
    ])

    @if ($event->description)
        <p style="margin:0 0 14px;font-size:15px;line-height:1.7;color:#0f172a;">
            {{ $event->description }}
        </p>
    @endif

    <p style="margin:0;font-size:15px;line-height:1.7;color:#0f172a;">
        We look forward to seeing you.
    </p>
@endsection

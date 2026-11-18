{{--
    "Confirm your email address."

    Rendered in the church's own email frame when there IS a church (logo,
    signature, Reply-To), and with a plain platform header when the account is
    a signup that has no church yet — the two real cases. The frame is the same
    one announcements, reminders and subvention decisions use, so a confirmation
    looks like the rest of the product rather than like a third-party message.
--}}
@extends('emails.layouts.church', [
    'subject' => 'Confirm your email address',
    'preheader' => 'One click confirms your email address.',
    'church' => $church ?? null,
])

@section('hero')
    <div
        style="display:inline-block;padding:4px 10px;border-radius:999px;background:#eef2ff;color:#4f46e5;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:12px;">
        Confirm your email
    </div>
    <h1 class="cf-email-title" style="margin:0;font-size:25px;line-height:1.3;color:#0f172a;font-weight:700;">
        Confirm your email address
    </h1>
@endsection

@section('content')
    <p style="margin:0 0 14px;font-size:15px;line-height:1.7;color:#0f172a;">
        Hello {{ $name }},
    </p>

    <p style="margin:0 0 4px;font-size:15px;line-height:1.7;color:#0f172a;">
        Please confirm this email address so we can send you updates about your church —
        announcements, event reminders and billing notices will all go here.
    </p>

    @include('emails.components.button', [
        'url' => $url,
        'label' => 'Confirm email address',
        'note' => 'This link expires in ' . $expiresInMinutes . ' minutes and works once.',
    ])

    <p style="margin:18px 0 0;font-size:13px;line-height:1.7;color:#64748b;">
        If the button does not work, copy this address into your browser:
        <br>
        <span style="word-break:break-all;color:#0f172a;">{{ $url }}</span>
    </p>

    <p style="margin:14px 0 0;font-size:13px;line-height:1.7;color:#64748b;">
        If you did not create this account, no further action is required — simply ignore this email.
        Your address will not be confirmed.
    </p>
@endsection

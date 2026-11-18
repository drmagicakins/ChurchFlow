{{-- Plain-text alternative. Never shares the HTML view — see VerifyEmailMail. --}}
Hello {{ $name }},

Please confirm this email address so we can send you updates about
{{ $church?->name ?? config('marketing.product.name', 'ChurchFlow') }} — announcements,
event reminders and billing notices will all go here.

Confirm your email address:
{{ $url }}

This link expires in {{ $expiresInMinutes }} minutes and works once.

If you did not create this account, no further action is required — simply
ignore this email. Your address will not be confirmed.

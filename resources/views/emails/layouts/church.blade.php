{{--
    THE FRAME EVERY EMAIL A CHURCH SENDS IS RENDERED IN.

    Why a layout and not four styled templates: an announcement, an event
    reminder and a subvention decision are different MESSAGES but the same
    CORRESPONDENCE. Repeating the header, logo, signature and footer in each
    one is how they drift apart — one gets the logo, one keeps the platform
    sender, one is plain text — which is exactly the state this replaces.

    EMAIL-CLIENT REALITY, WHICH DECIDES THE MARKUP
    ----------------------------------------------
    Mail clients are not browsers. Outlook (Windows) renders with Word's
    engine: no flexbox, no grid, no CSS variables, no <style> in some builds,
    and unreliable `max-width` on a block. So this file is deliberately built
    the way a transactional email has to be:

      * TABLES for structure — a 600px centred table is the only width that
        behaves the same everywhere;
      * INLINE styles on every element that matters, because a <style> block
        can be stripped;
      * a <style> block as well, only for the small-screen behaviour that
        cannot be expressed inline (a media query) — everything it touches
        already has a sane inline default;
      * no external CSS, no web fonts, no JavaScript — a mail client will not
        load them and Outlook may refuse the message over them;
      * the logo is an <img> with the REAL dimensions and an `alt`, because
        images are blocked by default in most inboxes. With the image off, the
        church's NAME still identifies the sender — that is the test of whether
        an email "carries the church's identity" or just decorates.

    SECTIONED, LIKE EMAIL MARKETING — but composed from the DATA each message
    already has. The components below (calls to action, a details panel, the
    signature) are plain Blade partials so a future template gets the same look
    by using them, rather than by copying CSS.

    The colours come from the app's own design system (--brand in app.css,
    #4f46e5), hard-coded here because CSS variables do not exist in Outlook.
--}}
@php
    $church = $church ?? null;
    $logoUrl = $church?->logoUrl();
    $productName = config('marketing.product.name', 'ChurchFlow');

    // With no church yet (a signup that has not bought a plan — e.g. an email
    // confirmation), the message is FROM THE PLATFORM: the header shows the
    // product, not a church called "ChurchFlow". With a church, it shows the
    // church.
    $churchName = $church?->name ?? $productName;
    $headerSubtitle = $church ? $church->denomination : null;
    $signature = $church?->emailSignatureLines() ?? [];
    $brand = '#4f46e5';
    $ink = '#0f172a';
    $muted = '#64748b';
    $line = '#e2e8f0';
    $surface = '#f8fafc';
    $fontStack = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
@endphp
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <title>{{ $subject ?? $churchName }}</title>
    {{-- The ONLY thing in this block is small-screen behaviour. Every rule has an
         inline default above it, so a client that drops <style> still gets a
         readable, correctly sized email. --}}
    <style>
        @media only screen and (max-width: 620px) {
            .cf-email-shell {
                width: 100% !important;
            }

            .cf-email-pad {
                padding: 20px !important;
            }

            .cf-email-hero {
                padding: 26px 20px !important;
            }

            .cf-email-title {
                font-size: 22px !important;
            }
        }
    </style>
</head>

<body style="margin:0;padding:0;background:{{ $surface }};-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
    {{-- Preheader: the grey line an inbox shows next to the subject. Hidden in
         the body itself. Omitting it makes the client show the first line of
         markup instead, which is usually "View this email in your browser". --}}
    <div
        style="display:none;font-size:1px;color:{{ $surface }};line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
        {{ $preheader ?? '' }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
        style="background:{{ $surface }};padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" class="cf-email-shell" width="600" cellpadding="0" cellspacing="0"
                    border="0"
                    style="width:600px;max-width:600px;background:#ffffff;border:1px solid {{ $line }};border-radius:12px;overflow:hidden;font-family:{{ $fontStack }};">

                    {{-- ===================== HEADER: the church's identity ==== --}}
                    <tr>
                        <td class="cf-email-pad" style="padding:22px 32px;border-bottom:1px solid {{ $line }};">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td valign="middle" style="font-family:{{ $fontStack }};">
                                        @if ($logoUrl)
                                            <img src="{{ $logoUrl }}" alt="{{ $churchName }}" width="48"
                                                height="48"
                                                style="display:block;border:0;outline:none;text-decoration:none;max-width:48px;max-height:48px;border-radius:8px;">
                                        @else
                                            {{-- No logo yet: the monogram is the fallback, not a broken
                                                 image. Built from the church's own initials so it is
                                                 still recognisably THEM. --}}
                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td width="48" height="48" align="center" valign="middle"
                                                        style="width:48px;height:48px;background:{{ $brand }};border-radius:8px;color:#ffffff;font-family:{{ $fontStack }};font-size:18px;font-weight:700;line-height:48px;">
                                                        {{ \Illuminate\Support\Str::of($churchName)->explode(' ')->filter()->map(fn($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
                                                    </td>
                                                </tr>
                                            </table>
                                        @endif
                                    </td>
                                    <td valign="middle" align="right" style="font-family:{{ $fontStack }};">
                                        <div
                                            style="font-size:15px;font-weight:700;color:{{ $ink }};line-height:1.3;">
                                            {{ $churchName }}</div>
                                        @if ($headerSubtitle)
                                            <div
                                                style="font-size:12px;color:{{ $muted }};line-height:1.4;margin-top:2px;">
                                                {{ $headerSubtitle }}</div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ===================== HERO: what this message is ====== --}}
                    @hasSection('hero')
                        <tr>
                            <td class="cf-email-hero" style="padding:32px 32px 8px;font-family:{{ $fontStack }};">
                                @yield('hero')
                            </td>
                        </tr>
                    @endif

                    {{-- ===================== BODY ============================ --}}
                    <tr>
                        <td class="cf-email-pad"
                            style="padding:8px 32px 28px;font-family:{{ $fontStack }};font-size:15px;line-height:1.65;color:{{ $ink }};">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- ===================== FOOTER: signature =============== --}}
                    <tr>
                        <td class="cf-email-pad"
                            style="padding:20px 32px 24px;border-top:1px solid {{ $line }};background:{{ $surface }};font-family:{{ $fontStack }};">
                            @if ($signature !== [])
                                <div style="font-size:13px;line-height:1.6;color:{{ $muted }};">
                                    @foreach ($signature as $index => $line_)
                                        @if ($index === 0)
                                            <strong
                                                style="color:{{ $ink }};">{{ $line_ }}</strong><br>
                                        @else
                                            {{ $line_ }}@if (!$loop->last)
                                                <br>
                                            @endif
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <div style="font-size:13px;line-height:1.6;color:{{ $muted }};">
                                    <strong style="color:{{ $ink }};">{{ $churchName }}</strong>
                                </div>
                            @endif

                            <div style="font-size:11px;line-height:1.6;color:{{ $muted }};margin-top:14px;">
                                {{-- "Reply" is invited explicitly, which is the whole point of the
                                     Reply-To this message carries: the church owner and the parish
                                     pastor, not a no-reply platform address. --}}
                                You can reply to this email to reach {{ $churchName }} directly.
                                <br>
                                @if ($church)
                                    Sent with {{ $productName }}.
                                @else
                                    Sent by {{ $productName }}.
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>

                <div
                    style="font-family:{{ $fontStack }};font-size:11px;color:{{ $muted }};padding:14px 8px 0;max-width:600px;line-height:1.6;">
                    @if ($church)
                        You are receiving this because your address is on {{ $churchName }}'s records.
                    @else
                        You are receiving this because an account was created with this address on {{ $productName }}.
                    @endif
                </div>
            </td>
        </tr>
    </table>
</body>

</html>

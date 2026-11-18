{{--
    A call-to-action button that survives email clients.

    Usage:  @include('emails.components.button', ['url' => $link, 'label' => 'View event'])
    Optional: 'note' — a line under the button (e.g. "Link expires in 48 hours").

    An <a> styled as a block inside a table cell, NOT a <button>: Outlook does
    not render a styled <button>, and Gmail strips its styling. The colour is
    duplicated on the cell so a client that drops the anchor's background still
    shows a coloured block with legible text, rather than a bare link.
--}}
@php
    $brand = '#4f46e5';
    $muted = '#64748b';
    $fontStack = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
@endphp

@if (filled($url ?? null) && filled($label ?? null))
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 6px;">
        <tr>
            <td align="center" bgcolor="{{ $brand }}" style="background:{{ $brand }};border-radius:8px;">
                <a href="{{ $url }}"
                    style="display:inline-block;padding:12px 24px;font-family:{{ $fontStack }};font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">
                    {{ $label }}
                </a>
            </td>
        </tr>
    </table>

    @if (filled($note ?? null))
        <div style="font-family:{{ $fontStack }};font-size:12px;color:{{ $muted }};">
            {{ $note }}
        </div>
    @endif
@endif

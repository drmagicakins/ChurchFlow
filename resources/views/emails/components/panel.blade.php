{{--
    A "key / value" panel — the email-marketing way to show the facts of a
    message (when, where, how much) so they are scannable rather than buried in
    a paragraph.

    Usage:  @include('emails.components.panel', ['rows' => ['When' => '…', 'Where' => '…']])

    Tables, not a grid: see the comment in emails/layouts/church.blade.php —
    Outlook renders with Word's engine and has no grid or flexbox. An EMPTY list
    renders nothing at all, so a caller can pass whatever it has.
--}}
@php
    $rows = array_filter($rows ?? [], fn($value) => filled($value));
    $ink = '#0f172a';
    $muted = '#64748b';
    $line = '#e2e8f0';
    $surface = '#f8fafc';
    $fontStack = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
@endphp

@if ($rows !== [])
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
        style="width:100%;margin:18px 0;background:{{ $surface }};border:1px solid {{ $line }};border-radius:10px;font-family:{{ $fontStack }};">
        @foreach ($rows as $label => $value)
            <tr>
                <td valign="top"
                    style="padding:11px 16px;font-size:12px;letter-spacing:.03em;text-transform:uppercase;color:{{ $muted }};width:38%;border-bottom:{{ $loop->last ? '0' : '1px solid ' . $line }};">
                    {{ $label }}
                </td>
                <td valign="top"
                    style="padding:11px 16px;font-size:14px;color:{{ $ink }};font-weight:600;border-bottom:{{ $loop->last ? '0' : '1px solid ' . $line }};">
                    {!! nl2br(e($value)) !!}
                </td>
            </tr>
        @endforeach
    </table>
@endif

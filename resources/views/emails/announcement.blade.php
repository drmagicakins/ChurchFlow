{{--
    A church announcement, in the church's own email frame.

    THE BODY IS NORMALISED TEXT, NOT HTML. `announcements.body` is a textarea,
    so its newlines are what the author typed; rendering `{!! !!}` would also
    turn anything a well-meaning editor pasted into live markup inside an
    email. The body is escaped by Blade and its paragraph breaks are rebuilt
    here (blank line = new paragraph, single newline = <br>), which produces
    the readable "letters, not a wall of text" result without trusting input.

    The title is the hero; the body is the content; the church signature and
    reply address come from the layout. No recipient name is printed because
    the same body goes to the whole audience.
--}}
@extends('emails.layouts.church', [
    'subject' => $announcement->title,
    'preheader' => \Illuminate\Support\Str::limit(trim(strip_tags($announcement->body)), 120),
    'church' => $church ?? $announcement->church,
])

@section('hero')
    <div
        style="display:inline-block;padding:4px 10px;border-radius:999px;background:#eef2ff;color:#4f46e5;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:12px;">
        Announcement
    </div>
    <h1 class="cf-email-title" style="margin:0;font-size:25px;line-height:1.3;color:#0f172a;font-weight:700;">
        {{ $announcement->title }}
    </h1>
@endsection

@section('content')
    @foreach (preg_split('/\n\s*\n/', (string) $announcement->body) as $paragraph)
        @if (trim($paragraph) !== '')
            <p style="margin:0 0 14px;font-size:15px;line-height:1.7;color:#0f172a;">
                {!! nl2br(e(trim($paragraph))) !!}
            </p>
        @endif
    @endforeach
@endsection

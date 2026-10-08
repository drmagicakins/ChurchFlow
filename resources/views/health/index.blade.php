<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Platform status · ChurchFlow</title>
    <meta name="robots" content="noindex">
    @include('partials.theme')
    <style>
        /* This page is reachable by ANYONE, including someone who is not
           signed in and whose only context is "the app seems broken". It
           therefore cannot use the authenticated layout component — no sidebar
           the visitor has no right to see, no user menu. It stands alone.

           The component must NOT be named by its angle-bracket tag here: this
           is PHP/Blade, not HTML, so Blade compiles the tag name into a real
           `startComponent` call even inside a CSS comment — the component is
           never closed and the whole stylesheet is swallowed into it. Name it
           in words instead. */
        .hp-wrap {
            max-width: 62rem;
            margin: 0 auto;
            padding: 2.2rem 1.15rem 4rem;
        }

        .hp-banner {
            border-radius: 0.9rem;
            padding: 1.4rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .hp-banner--ok {
            background: var(--ok-bg, #ecfdf5);
            border: 1px solid var(--ok-line, #a7f3d0);
        }

        .hp-banner--down {
            background: var(--danger-bg, #fef2f2);
            border: 1px solid var(--danger-line, #fecaca);
        }

        .hp-banner--warn {
            background: var(--warn-bg, #fffbeb);
            border: 1px solid var(--warn-line, #fde68a);
        }

        .hp-pulse {
            width: 0.85rem;
            height: 0.85rem;
            border-radius: 50%;
            flex: none;
            margin-top: 0.35rem;
        }

        .hp-pulse--ok {
            background: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, .18);
        }

        .hp-pulse--down {
            background: #dc2626;
            box-shadow: 0 0 0 4px rgba(220, 38, 38, .18);
        }

        .hp-pulse--warn {
            background: #f59e0b;
            box-shadow: 0 0 0 4px rgba(245, 158, 11, .18);
        }

        .hp-banner h1 {
            margin: 0 0 0.25rem;
            font-size: 1.3rem;
        }

        .hp-banner p {
            margin: 0;
            font-size: 0.88rem;
            color: var(--muted);
        }

        .hp-section {
            margin-bottom: 2rem;
        }

        .hp-section>h2 {
            font-size: 1rem;
            margin: 0 0 0.2rem;
        }

        .hp-section__sub {
            font-size: 0.82rem;
            color: var(--muted);
            margin: 0 0 0.9rem;
        }

        .hp-grid {
            display: grid;
            gap: 0.6rem;
            grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
        }

        .hp-check {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            border: 1px solid var(--line);
            border-radius: 0.7rem;
            padding: 0.7rem 0.85rem;
            background: var(--surface);
        }

        .hp-check__dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            flex: none;
        }

        .hp-check__dot--ok {
            background: #10b981;
        }

        .hp-check__dot--bad {
            background: #dc2626;
        }

        .hp-check__name {
            font-size: 0.86rem;
            font-weight: 600;
        }

        .hp-check__meta {
            font-size: 0.74rem;
            color: var(--muted);
        }

        .hp-check__ms {
            margin-left: auto;
            font-size: 0.74rem;
            color: var(--muted);
            font-variant-numeric: tabular-nums;
        }

        .hp-incident {
            border: 1px solid var(--line);
            border-left-width: 3px;
            border-radius: 0.7rem;
            padding: 0.9rem 1rem;
            margin-bottom: 0.7rem;
            background: var(--surface);
        }

        .hp-incident--critical {
            border-left-color: #7f1d1d;
        }

        .hp-incident--error {
            border-left-color: #dc2626;
        }

        .hp-incident--warning {
            border-left-color: #f59e0b;
        }

        .hp-incident--info {
            border-left-color: #64748b;
        }

        .hp-incident--resolved {
            border-left-color: #10b981;
            opacity: 0.92;
        }

        .hp-incident__head {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 0.4rem;
        }

        .hp-incident__title {
            font-weight: 700;
            font-size: 0.9rem;
            margin: 0;
        }

        .hp-incident__msg {
            font-size: 0.84rem;
            margin: 0.3rem 0 0.55rem;
            line-height: 1.5;
        }

        .hp-incident__times {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem 1.1rem;
            font-size: 0.75rem;
            color: var(--muted);
            font-variant-numeric: tabular-nums;
        }

        .hp-incident__note {
            margin-top: 0.6rem;
            padding-top: 0.6rem;
            border-top: 1px dashed var(--line);
            font-size: 0.8rem;
            color: var(--ok-ink, #047857);
        }

        .hp-empty {
            border: 1px dashed var(--line);
            border-radius: 0.7rem;
            padding: 1.4rem;
            text-align: center;
            color: var(--muted);
            font-size: 0.86rem;
        }

        .hp-foot {
            margin-top: 2.5rem;
            padding-top: 1.2rem;
            border-top: 1px solid var(--line);
            font-size: 0.76rem;
            color: var(--muted);
        }

        .hp-foot a {
            color: var(--brand-600, #1d4ed8);
        }
    </style>
</head>

<body style="background:var(--canvas,#f8fafc)">

    <div class="hp-wrap">
        @php
            $criticalOpen = $incidents->where('severity', 'critical')->count();

            // Computed as if/else rather than a nested ternary. Do not write the
            // closing PHP tag inside a comment here: a raw one ENDS the @php
            // block early and leaves the rest of the statement as literal HTML,
            // which surfaces as "unexpected end of file" pointing at the END of
            // the file — a misleading error that cost far more time than the
            // rewrite. Plain statements compile unambiguously.
            if ($healthy) {
                $tone = $incidents->isEmpty() ? 'ok' : 'warn';
                $headline = $incidents->isEmpty() ? 'All systems operational' : 'Operating normally, with known issues';
            } else {
                $tone = 'down';
                $headline =
                    $criticalOpen > 0 ? 'We are experiencing an outage' : 'Some parts of the platform are degraded';
            }
        @endphp

        {{-- The banner answers the only two questions a visitor has: is it broken,
         and is it broken for me. Everything below is supporting detail. --}}
        <div class="hp-banner hp-banner--{{ $tone }}">
            <span class="hp-pulse hp-pulse--{{ $tone }}" aria-hidden="true"></span>
            <div style="flex:1;min-width:16rem">
                <h1>{{ $headline }}</h1>
                <p>
                    @if ($summary['last_checked_at'])
                        Automated checks last ran
                        {{ \Illuminate\Support\Carbon::parse($summary['last_checked_at'])->diffForHumans() }}.
                    @else
                        Automated checks have not run yet. The results below are live probes taken just now.
                    @endif
                    {{ $incidents->count() }} known {{ \Illuminate\Support\Str::plural('issue', $incidents->count()) }}.
                </p>
            </div>
        </div>

        {{-- Live probe results, taken on this page load. --}}
        <section class="hp-section">
            <h2>Current checks</h2>
            <p class="hp-section__sub">Probed just now, on this page load. These results are read-only and are not
                recorded as incidents — the scheduled check does that.</p>

            <div class="hp-grid">
                @foreach ($live['results'] as $key => $check)
                    <div class="hp-check">
                        <span class="hp-check__dot hp-check__dot--{{ $check['ok'] ? 'ok' : 'bad' }}"
                            aria-hidden="true"></span>
                        <div>
                            <div class="hp-check__name">{{ $check['label'] }}</div>
                            <div class="hp-check__meta">
                                {{ $check['ok'] ? 'Passing' : 'Failing' }}
                            </div>
                        </div>
                        <span class="hp-check__ms">{{ $check['latency_ms'] }} ms</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Open incidents: what is wrong right now, with the dates and times. --}}
        <section class="hp-section">
            <h2>Open issues</h2>
            <p class="hp-section__sub">
                Recorded by the scheduled health check, with the date and time each was first and last seen.
            </p>

            @if ($incidents->isEmpty())
                <div class="hp-empty">No open issues. Nothing has failed since the last check.</div>
            @else
                @foreach ($incidents as $incident)
                    <article class="hp-incident hp-incident--{{ $incident->severity }}">
                        <div class="hp-incident__head">
                            <p class="hp-incident__title">{{ str_replace('_', ' ', ucfirst($incident->check_name)) }}
                            </p>
                            <span
                                class="cf-badge {{ $incident->severity === 'critical' || $incident->severity === 'error' ? 'cf-badge--danger' : 'cf-badge--warn' }}">
                                {{ ucfirst($incident->severity) }}
                            </span>
                            <span class="cf-badge">Open</span>
                        </div>

                        {{-- A human-readable message only. The raw exception, file
                         paths and stack traces are logged server-side and are
                         deliberately never rendered on a public page. --}}
                        <p class="hp-incident__msg">{{ $incident->message }}</p>

                        <div class="hp-incident__times">
                            <span>First seen:
                                <strong>{{ $incident->first_seen_at->format('D, j M Y · H:i') }}</strong></span>
                            <span>Last seen:
                                <strong>{{ $incident->last_seen_at->format('D, j M Y · H:i') }}</strong></span>
                            <span>Detected <strong>{{ number_format($incident->occurrences) }}</strong>
                                {{ \Illuminate\Support\Str::plural('time', $incident->occurrences) }}</span>
                            <span>Open for
                                <strong>{{ $incident->first_seen_at->diffForHumans(now(), true) }}</strong></span>
                        </div>
                    </article>
                @endforeach
            @endif
        </section>

        {{-- Resolved incidents: the record of what happened and when it was fixed.
         Kept visible on purpose — "has this happened before?" is the question
         an intermittent problem makes people ask, and deleting resolved rows
         would make it unanswerable. --}}
        <section class="hp-section">
            <h2>Recently resolved</h2>
            <p class="hp-section__sub">Issues that were fixed, with the date and time they were resolved.</p>

            @if ($recentlyResolved->isEmpty())
                <div class="hp-empty">Nothing has been resolved yet.</div>
            @else
                @foreach ($recentlyResolved as $incident)
                    <article class="hp-incident hp-incident--resolved">
                        <div class="hp-incident__head">
                            <p class="hp-incident__title">{{ str_replace('_', ' ', ucfirst($incident->check_name)) }}
                            </p>
                            <span class="cf-badge cf-badge--ok">Resolved</span>
                        </div>

                        <p class="hp-incident__msg">{{ $incident->message }}</p>

                        <div class="hp-incident__times">
                            <span>First seen:
                                <strong>{{ $incident->first_seen_at->format('D, j M Y · H:i') }}</strong></span>
                            <span>Resolved:
                                <strong>{{ $incident->resolved_at->format('D, j M Y · H:i') }}</strong></span>
                            <span>Lasted
                                <strong>{{ $incident->first_seen_at->diffForHumans($incident->resolved_at, true) }}</strong></span>
                            <span>{{ number_format($incident->occurrences) }}
                                {{ \Illuminate\Support\Str::plural('detection', $incident->occurrences) }}</span>
                        </div>

                        @if ($incident->resolution_note)
                            <p class="hp-incident__note">{{ $incident->resolution_note }}</p>
                        @endif
                    </article>
                @endforeach
            @endif
        </section>

        <div class="hp-foot">
            <p>
                This page is public so it is reachable when the platform is not.
                Machine-readable output is at <a href="{{ route('health.json') }}">/health.json</a>.
                @auth
                    · <a href="{{ route('dashboard') }}">Back to your dashboard</a>
                @else
                    · <a href="{{ route('login') }}">Sign in</a>
                @endauth
            </p>
            <p style="margin:.4rem 0 0">
                Automated checks run every five minutes. If a check fails it is recorded here with the date and time,
                and the platform administrators are notified; when it passes again the issue is marked resolved above.
            </p>
        </div>
    </div>

</body>

</html>

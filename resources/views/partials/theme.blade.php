{{--
    Theme loader.

    Loads the compiled Vite bundle when it exists, and otherwise falls back to a
    small inline stylesheet carrying the *same* token values and the handful of
    component classes that matter most.

    Why this exists: without it, a missing/stale build means every page renders as
    unstyled HTML — the app "works" but looks broken, which is exactly the failure
    mode we hit before the CSS was wired up. With it, the worst case is a plainer
    page, never an unstyled one.

    Keep the token values below in sync with resources/css/app.css.
--}}
@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    {{-- Inter is loaded by the compiled bundle's @theme font stack; preconnect so
         the swap does not block first paint. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@else
    <style>
        :root {
            color-scheme: light;
            --brand: #4f46e5;
            --brand-dark: #4338ca;
            --brand-tint: #eef2ff;
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --surface: #ffffff;
            --canvas: #f8fafc;
            --danger: #dc2626;
            --shell-sidebar: 15rem;
            --shell-gutter: clamp(1rem, 3vw, 2rem);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--canvas);
            color: var(--ink);
            line-height: 1.55;
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        h1,
        h2,
        h3,
        h4 {
            margin: 0;
            line-height: 1.2;
            letter-spacing: -0.02em;
            font-weight: 650;
        }

        h1 {
            font-size: clamp(1.6rem, 1.2rem + 1.4vw, 2.1rem);
        }

        h2 {
            font-size: clamp(1.2rem, 1.05rem + 0.6vw, 1.45rem);
        }

        h3 {
            font-size: 1.05rem;
        }

        p {
            margin: 0;
        }

        a {
            color: var(--brand);
        }

        :focus-visible {
            outline: 2px solid var(--brand);
            outline-offset: 2px;
            border-radius: 4px;
        }

        .cf-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: var(--shell-sidebar) 1fr;
        }

        .cf-sidebar {
            background: var(--surface);
            border-right: 1px solid var(--line);
            padding: 1.1rem 0.85rem;
            display: flex;
            flex-direction: column;
            gap: .35rem;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .cf-sidebar__brand {
            display: flex;
            align-items: center;
            gap: .6rem;
            font-weight: 700;
            color: var(--ink);
            text-decoration: none;
            padding: .35rem .5rem .9rem;
        }

        .cf-sidebar__mark {
            width: 1.9rem;
            height: 1.9rem;
            border-radius: .55rem;
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: #fff;
            display: grid;
            place-items: center;
            font-size: .95rem;
            flex: none;
        }

        .cf-sidebar__group {
            margin-top: .85rem;
        }

        .cf-sidebar__label {
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .09em;
            color: var(--muted);
            font-weight: 700;
            padding: 0 .5rem .35rem;
        }

        .cf-navlink {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .45rem .5rem;
            border-radius: .5rem;
            color: #334155;
            text-decoration: none;
            font-size: .88rem;
            font-weight: 500;
        }

        .cf-navlink:hover {
            background: var(--canvas);
            color: var(--ink);
        }

        .cf-navlink.is-active {
            background: var(--brand-tint);
            color: var(--brand-dark);
            font-weight: 600;
        }

        .cf-main {
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .cf-topbar {
            position: sticky;
            top: 0;
            z-index: 20;
            background: var(--surface);
            border-bottom: 1px solid var(--line);
            padding: .7rem var(--shell-gutter);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .cf-content {
            padding: clamp(1.1rem, 2.5vw, 2rem) var(--shell-gutter) 3rem;
            width: 100%;
            max-width: 76rem;
        }

        .cf-page-head {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.4rem;
        }

        .cf-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: .875rem;
            padding: 1.4rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .06);
        }

        .cf-card--flush {
            padding: 0;
            overflow: hidden;
        }

        .cf-card__head {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .cf-card__body {
            padding: 1.4rem;
        }

        .cf-grid {
            display: grid;
            gap: 1rem;
        }

        .cf-grid--2 {
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 24rem), 1fr));
        }

        .cf-grid--3 {
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 15rem), 1fr));
        }

        .cf-grid--4 {
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 15rem), 1fr));
        }

        @media (min-width: 60rem) {
            .cf-grid--4 {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        .cf-stat {
            display: flex;
            flex-direction: column;
            gap: .3rem;
        }

        .cf-stat__label {
            font-size: .76rem;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--muted);
            font-weight: 650;
        }

        .cf-stat__value {
            font-size: 1.65rem;
            font-weight: 700;
            letter-spacing: -.03em;
            font-variant-numeric: tabular-nums;
        }

        .cf-stat__hint {
            font-size: .82rem;
            color: var(--muted);
        }

        .cf-muted {
            color: var(--muted);
        }

        .cf-small {
            font-size: .85rem;
        }

        .cf-tiny {
            font-size: .78rem;
        }

        .cf-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        }

        .cf-eyebrow {
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--brand-dark);
        }

        .cf-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            padding: .55rem 1rem;
            border-radius: .6rem;
            border: 1px solid transparent;
            font: inherit;
            font-size: .88rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
        }

        .cf-btn--primary {
            background: var(--brand);
            color: #fff;
        }

        .cf-btn--primary:hover {
            background: var(--brand-dark);
        }

        .cf-btn--secondary {
            background: var(--surface);
            color: var(--ink);
            border-color: var(--line);
        }

        .cf-btn--secondary:hover {
            background: var(--canvas);
        }

        .cf-btn--ghost {
            background: transparent;
            color: var(--muted);
        }

        .cf-btn--danger {
            background: var(--danger);
            color: #fff;
        }

        .cf-btn--sm {
            padding: .35rem .7rem;
            font-size: .8rem;
            border-radius: .5rem;
        }

        .cf-btn--block {
            width: 100%;
        }

        .cf-field {
            margin-bottom: 1.05rem;
        }

        .cf-label {
            display: block;
            font-size: .83rem;
            font-weight: 600;
            margin-bottom: .35rem;
            color: #334155;
        }

        .cf-input,
        .cf-select,
        .cf-textarea {
            width: 100%;
            padding: .6rem .75rem;
            border: 1px solid var(--line);
            border-radius: .6rem;
            background: var(--surface);
            color: var(--ink);
            font: inherit;
            font-size: .92rem;
        }

        .cf-input:focus,
        .cf-select:focus,
        .cf-textarea:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, .18);
        }

        .cf-textarea {
            min-height: 6.5rem;
            resize: vertical;
        }

        .cf-hint {
            font-size: .78rem;
            color: var(--muted);
            margin-top: .3rem;
        }

        .cf-error {
            font-size: .8rem;
            color: var(--danger);
            margin-top: .3rem;
            font-weight: 500;
        }

        .cf-check {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .88rem;
            color: #334155;
        }

        .cf-alert {
            display: flex;
            gap: .7rem;
            align-items: flex-start;
            padding: .8rem 1rem;
            border-radius: .7rem;
            border: 1px solid var(--line);
            background: var(--surface);
            font-size: .88rem;
            margin-bottom: 1rem;
        }

        .cf-alert--ok {
            background: #f0fdf4;
            border-color: #bbf7d0;
            color: #166534;
        }

        .cf-alert--warn {
            background: #fffbeb;
            border-color: #fde68a;
            color: #92400e;
        }

        .cf-alert--error {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }

        .cf-alert ul {
            margin: .25rem 0 0;
            padding-left: 1.1rem;
        }

        .cf-table-wrap {
            overflow-x: auto;
        }

        .cf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .88rem;
        }

        .cf-table th {
            text-align: left;
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--muted);
            font-weight: 700;
            padding: .7rem 1.1rem;
            border-bottom: 1px solid var(--line);
            background: var(--canvas);
            white-space: nowrap;
        }

        .cf-table td {
            padding: .75rem 1.1rem;
            border-bottom: 1px solid var(--line);
            vertical-align: middle;
        }

        .cf-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .cf-table tbody tr:hover {
            background: #fbfdff;
        }

        .cf-badge {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .15rem .55rem;
            border-radius: 999px;
            font-size: .73rem;
            font-weight: 650;
            background: var(--canvas);
            color: #475569;
            border: 1px solid var(--line);
            white-space: nowrap;
        }

        .cf-badge--brand {
            background: var(--brand-tint);
            color: var(--brand-dark);
            border-color: #c7d2fe;
        }

        .cf-badge--ok {
            background: #f0fdf4;
            color: #166534;
            border-color: #bbf7d0;
        }

        .cf-badge--warn {
            background: #fffbeb;
            color: #92400e;
            border-color: #fde68a;
        }

        .cf-badge--danger {
            background: #fef2f2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .cf-defs {
            margin: 0;
            display: grid;
            grid-template-columns: max-content 1fr;
            gap: .45rem 1.1rem;
            font-size: .88rem;
        }

        .cf-defs dt {
            color: var(--muted);
        }

        .cf-defs dd {
            margin: 0;
        }

        .cf-empty {
            text-align: center;
            padding: 2.6rem 1.4rem;
        }

        .cf-empty__title {
            font-weight: 650;
            margin-bottom: .25rem;
        }

        .cf-divider {
            height: 1px;
            background: var(--line);
            border: 0;
            margin: 1.4rem 0;
        }

        .cf-avatar {
            width: 2rem;
            height: 2rem;
            border-radius: 999px;
            background: var(--brand-tint);
            color: var(--brand-dark);
            display: grid;
            place-items: center;
            font-size: .8rem;
            font-weight: 700;
            flex: none;
        }

        .cf-auth {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 2rem 1rem 3rem;
            background: radial-gradient(60rem 30rem at 50% -10%, var(--brand-tint), transparent 70%), var(--canvas);
        }

        .cf-auth__panel {
            width: 100%;
            max-width: 26.5rem;
        }

        .cf-auth__brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .6rem;
            margin-bottom: 1.3rem;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--ink);
            text-decoration: none;
        }

        .cf-auth__footer {
            text-align: center;
            margin-top: 1.1rem;
            font-size: .88rem;
            color: var(--muted);
        }

        .cf-hero__title {
            font-size: clamp(2.1rem, 1.3rem + 3.4vw, 3.4rem);
            letter-spacing: -.035em;
            line-height: 1.06;
            font-weight: 700;
        }

        .cf-hero__lede {
            font-size: clamp(1rem, .95rem + .35vw, 1.15rem);
            color: #475569;
            max-width: 34rem;
        }

        .cf-cta-row {
            display: flex;
            flex-wrap: wrap;
            gap: .7rem;
            align-items: center;
        }

        .cf-hero__stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1.2rem;
            margin: 2.4rem 0 0;
        }

        .cf-section {
            max-width: 72rem;
            margin: 0 auto;
            padding: clamp(2.6rem, 5vw, 4.5rem) var(--shell-gutter);
        }

        .cf-section__head {
            max-width: 42rem;
            margin-bottom: 2.2rem;
        }

        .cf-checks {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: .6rem;
        }

        .cf-checks li {
            display: grid;
            grid-template-columns: 1.35rem minmax(0, 1fr);
            gap: .65rem;
            font-size: .9rem;
            color: #334155;
            align-items: start;
        }

        .cf-checks li::before {
            content: '\2713';
            width: 1.35rem;
            height: 1.35rem;
            border-radius: 999px;
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            display: grid;
            place-items: center;
            font-size: .72rem;
            font-weight: 700;
        }

        /* Real block, never `display: contents` — contents hands the inline
           children back to the grid, so <strong>/<em>/<code> become grid items
           in the 1.35rem tick column and break mid-word. See app.css. */
        .cf-checks li>span {
            display: block;
            min-width: 0;
        }

        .cf-footer {
            background: var(--surface);
            border-top: 1px solid var(--line);
        }

        .cf-footer__inner {
            max-width: 72rem;
            margin: 0 auto;
            padding: 2.4rem var(--shell-gutter);
            display: grid;
            gap: 1.6rem;
            grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
            font-size: .88rem;
        }

        .cf-footer__col h4 {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            margin-bottom: .6rem;
        }

        .cf-footer__col a {
            display: block;
            color: #475569;
            text-decoration: none;
            padding: .18rem 0;
        }

        .cf-footer__base {
            border-top: 1px solid var(--line);
            max-width: 72rem;
            margin: 0 auto;
            padding: 1rem var(--shell-gutter) 2rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: .75rem;
            font-size: .82rem;
            color: var(--muted);
        }

        @media (max-width: 61.99rem) {
            .cf-shell {
                grid-template-columns: 1fr;
            }

            .cf-sidebar {
                position: static;
                height: auto;
                flex-direction: row;
                align-items: center;
                gap: .3rem;
                overflow-x: auto;
                padding: .6rem .75rem;
                border-right: 0;
                border-bottom: 1px solid var(--line);
            }

            .cf-sidebar__brand {
                padding: 0 .5rem 0 0;
            }

            .cf-sidebar__label {
                display: none;
            }

            .cf-sidebar__group {
                margin-top: 0;
                display: flex;
                gap: .3rem;
            }

            .cf-navlink {
                white-space: nowrap;
            }
        }
    </style>
@endif

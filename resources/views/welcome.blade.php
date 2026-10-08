<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ChurchFlow — The operating platform for modern churches</title>
    <meta name="description"
        content="ChurchFlow runs people, finance, activities and pastoral care for churches, ministries and multi-branch denominations — in one secure, multi-tenant workspace.">

    @include('partials.theme')
</head>

<body>

    {{-- ------------------------------------------------------------------ top bar --}}
    <header class="cf-mkbar" id="mkbar">
        <div class="cf-mkbar__inner">
            <a href="{{ url('/') }}" class="cf-sidebar__brand" style="padding:0">
                <span class="cf-sidebar__mark" aria-hidden="true">CF</span>
                <span>ChurchFlow</span>
            </a>

            <nav class="cf-mkbar__nav" aria-label="Primary">
                <a class="cf-mkbar__link" href="#platform">Platform</a>
                <a class="cf-mkbar__link" href="#finance">Finance</a>
                <a class="cf-mkbar__link" href="#trust">Security</a>
                <a class="cf-mkbar__link" href="#roadmap">Roadmap</a>
            </nav>

            <div style="display:flex;align-items:center;gap:.55rem">
                @auth
                    <a href="{{ route('dashboard') }}" class="cf-btn cf-btn--primary cf-btn--sm">Go to dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="cf-btn cf-btn--ghost cf-btn--sm">Sign in</a>
                    <a href="{{ route('register') }}" class="cf-btn cf-btn--primary cf-btn--sm">Get started</a>
                @endauth
            </div>
        </div>
    </header>

    {{-- --------------------------------------------------------------------- hero --}}
    <section class="cf-hero">
        <div class="cf-hero__inner">
            <div>
                <span class="cf-badge cf-badge--brand" style="margin-bottom:1rem">
                    Six modules shipped &middot; built on Laravel 12
                </span>

                <h1 class="cf-hero__title">
                    Run your whole church<br>from one place.
                </h1>

                <p class="cf-hero__lede" style="margin-top:1.1rem">
                    ChurchFlow is the operating platform for churches, ministries and
                    multi-branch denominations — people, finances, activities, subvention
                    and pastoral care in a single multi-tenant workspace.
                </p>

                <div class="cf-cta-row" style="margin-top:1.8rem">
                    @auth
                        <a href="{{ route('dashboard') }}" class="cf-btn cf-btn--primary">Open your workspace</a>
                    @else
                        <a href="{{ route('register') }}" class="cf-btn cf-btn--primary">Start free — set up in minutes</a>
                        <a href="{{ route('login') }}" class="cf-btn cf-btn--secondary">Sign in</a>
                    @endauth
                </div>

                <p class="cf-tiny cf-muted" style="margin-top:.9rem">
                    No card required. Your data stays yours — export it whenever you like.
                </p>

                {{--
                  Fixed three columns, not the auto-fit .cf-grid--3 used elsewhere:
                  inside the hero column (~19rem) auto-fit can only ever resolve to two
                  tracks, so the third stat wraps to its own row and the row looks
                  unbalanced. These three are a deliberate, always-parallel trio.
                --}}
                <dl class="cf-hero__stats">
                    <div>
                        <dd class="cf-stat__value" style="font-size:1.5rem">6</dd>
                        <dt class="cf-stat__label">Modules live</dt>
                    </div>
                    <div>
                        <dd class="cf-stat__value" style="font-size:1.5rem">100%</dd>
                        <dt class="cf-stat__label">Tenant-isolated</dt>
                    </div>
                    <div>
                        <dd class="cf-stat__value" style="font-size:1.5rem">0</dd>
                        <dt class="cf-stat__label">Plaintext secrets</dt>
                    </div>
                </dl>
            </div>

            {{-- Product mock: a miniature of the real dashboard shell, built from the
             same tokens as the app so it can never drift from the product. --}}
            <div aria-hidden="true">
                <div class="cf-card" style="box-shadow:var(--shadow-lg);padding:0;overflow:hidden">
                    <div
                        style="display:flex;align-items:center;gap:.5rem;padding:.7rem 1rem;border-bottom:1px solid var(--line);background:var(--canvas)">
                        <span style="width:.62rem;height:.62rem;border-radius:999px;background:#f87171"></span>
                        <span style="width:.62rem;height:.62rem;border-radius:999px;background:#fbbf24"></span>
                        <span style="width:.62rem;height:.62rem;border-radius:999px;background:#4ade80"></span>
                        <span class="cf-tiny cf-muted cf-mono" style="margin-left:.5rem">churchflow.app/dashboard</span>
                    </div>

                    <div style="display:grid;grid-template-columns:9.5rem 1fr;min-height:17rem">
                        <div
                            style="border-right:1px solid var(--line);padding:.8rem .6rem;display:flex;flex-direction:column;gap:.3rem">
                            <div class="cf-tiny" style="font-weight:700;padding:.2rem .4rem .5rem">ChurchFlow</div>
                            <span class="cf-navlink is-active cf-tiny" style="padding:.3rem .45rem">Dashboard</span>
                            <span class="cf-navlink cf-tiny" style="padding:.3rem .45rem">Members</span>
                            <span class="cf-navlink cf-tiny" style="padding:.3rem .45rem">Accounts</span>
                            <span class="cf-navlink cf-tiny" style="padding:.3rem .45rem">Subventions</span>
                            <span class="cf-navlink cf-tiny" style="padding:.3rem .45rem">Pastoral</span>
                        </div>

                        <div style="padding:1rem;display:flex;flex-direction:column;gap:.85rem">
                            <div>
                                <div class="cf-tiny cf-muted">This month</div>
                                <div style="font-size:1.5rem;font-weight:700;letter-spacing:-.03em">&#8358;12,480,000
                                </div>
                            </div>

                            <div class="cf-grid cf-grid--3" style="gap:.5rem">
                                <div class="cf-card" style="padding:.6rem">
                                    <div class="cf-tiny cf-muted">Members</div>
                                    <div style="font-weight:700">1,284</div>
                                </div>
                                <div class="cf-card" style="padding:.6rem">
                                    <div class="cf-tiny cf-muted">Attendance</div>
                                    <div style="font-weight:700">92%</div>
                                </div>
                                <div class="cf-card" style="padding:.6rem">
                                    <div class="cf-tiny cf-muted">Pending</div>
                                    <div style="font-weight:700">3</div>
                                </div>
                            </div>

                            <div class="cf-card" style="padding:.6rem .75rem">
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem">
                                    <span class="cf-tiny">Expense &middot; Generator fuel</span>
                                    <span class="cf-badge cf-badge--warn">Awaiting approval</span>
                                </div>
                                <div class="cf-tiny cf-muted" style="margin-top:.2rem">&#8358;180,000 &middot; Finance
                                    Office</div>
                            </div>

                            <div class="cf-card" style="padding:.6rem .75rem">
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem">
                                    <span class="cf-tiny">Subvention &middot; Group 1</span>
                                    <span class="cf-badge cf-badge--ok">Approved</span>
                                </div>
                                <div class="cf-tiny cf-muted" style="margin-top:.2rem">Remittance &#8358;3,026,000
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ----------------------------------------------------------------- platform --}}
    <section class="cf-section" id="platform">
        <div class="cf-section__head">
            <span class="cf-eyebrow">The platform</span>
            <h2 style="margin-top:.5rem">Six modules, one workspace</h2>
            <p class="cf-muted" style="margin-top:.6rem">
                Each module is a complete product area — not a stub. Everything below is
                implemented, migrated and covered by the test suite.
            </p>
        </div>

        <div class="cf-grid cf-grid--3">
            <article class="cf-feature">
                <div class="cf-feature__icon" aria-hidden="true">&#128101;</div>
                <h3 class="cf-feature__title">Church &amp; People</h3>
                <p class="cf-feature__body">
                    A full member CRM with auto-generated membership numbers, families,
                    departments, groups, church-configurable custom fields, and CSV
                    import/export that reports bad rows instead of aborting the batch.
                </p>
            </article>

            <article class="cf-feature">
                <div class="cf-feature__icon" aria-hidden="true">&#128197;</div>
                <h3 class="cf-feature__title">Activities</h3>
                <p class="cf-feature__body">
                    Capacity-aware event registration that can't oversell the last seat,
                    roster-wide attendance in a single submit, announcements with audience
                    targeting, and a live calendar with no stale entries.
                </p>
            </article>

            <article class="cf-feature">
                <div class="cf-feature__icon" aria-hidden="true">&#128176;</div>
                <h3 class="cf-feature__title">Finance</h3>
                <p class="cf-feature__body">
                    Accounts, income, expenses, transfers, budgets and loans — all derived
                    from an immutable ledger rather than a mutable balance column that can
                    silently drift.
                </p>
            </article>

            <article class="cf-feature">
                <div class="cf-feature__icon" aria-hidden="true">&#9989;</div>
                <h3 class="cf-feature__title">Approvals</h3>
                <p class="cf-feature__body">
                    One shared approval engine behind expenses and subvention. Nothing
                    touches a balance until it's approved, and a decided approval can
                    never be decided twice.
                </p>
            </article>

            <article class="cf-feature">
                <div class="cf-feature__icon" aria-hidden="true">&#127963;</div>
                <h3 class="cf-feature__title">Subvention</h3>
                <p class="cf-feature__body">
                    A configurable rule engine that generalises hard-coded denominational
                    formulas into data. Versioned, replay-safe calculations — recalculating
                    never rewrites history or double-deducts a loan.
                </p>
            </article>

            <article class="cf-feature">
                <div class="cf-feature__icon" aria-hidden="true">&#128330;</div>
                <h3 class="cf-feature__title">Pastoral Care</h3>
                <p class="cf-feature__body">
                    Prayer requests, counselling and welfare cases, append-only case notes
                    and appointments — with deliberately narrow permissions, so pastoral
                    records are never visible to ordinary administrators by default.
                </p>
            </article>
        </div>
    </section>

    {{-- ------------------------------------------------------------------ finance --}}
    <section class="cf-section cf-section--tint" id="finance">
        <div class="cf-section__head">
            <span class="cf-eyebrow">Built for trust</span>
            <h2 style="margin-top:.5rem">Money you can reconcile</h2>
            <p class="cf-muted" style="margin-top:.6rem">
                Financial software earns its place by being auditable. These are
                architectural guarantees, not settings.
            </p>
        </div>

        <div class="cf-grid cf-grid--2">
            <div class="cf-card">
                <h3 style="margin-bottom:.9rem">Every figure is derived</h3>
                <ul class="cf-checks">
                    <li><span>Account balances are computed live from the ledger — never stored in a column that drifts.</span></li>
                    <li><span>Loan balances and months-paid come from an immutable payment ledger, clamped at zero on
                        overpayment.</span></li>
                    <li><span>Budgets compare planned against approved, non-void expenses only.</span></li>
                    <li><span>Subvention recalculations insert a new version; the previous one is untouched.</span></li>
                </ul>
            </div>

            <div class="cf-card">
                <h3 style="margin-bottom:.9rem">Nothing disappears quietly</h3>
                <ul class="cf-checks">
                    <li><span>Transactions are never deleted — a mistake is voided with a required reason, and the row stays.</span></li>
                    <li><span>An expense can't exist without a linked approval record.</span></li>
                    <li><span>Pastoral case notes are append-only; a correction is a new note.</span></li>
                    <li><span>Create, update and delete are written to a tenant-scoped audit log with secrets stripped.</span></li>
                </ul>
            </div>
        </div>
    </section>

    {{-- -------------------------------------------------------------------- trust --}}
    <section class="cf-section" id="trust">
        <div class="cf-section__head">
            <span class="cf-eyebrow">Multi-tenant by construction</span>
            <h2 style="margin-top:.5rem">Your church sees only your church</h2>
            <p class="cf-muted" style="margin-top:.6rem">
                Isolation is enforced by the framework's data layer, not by remembering to
                add a <code class="cf-mono">where</code> clause to every query.
            </p>
        </div>

        <div class="cf-grid cf-grid--2">
            <div>
                <ul class="cf-checks">
                    <li><span>Tenant scoping is applied automatically to every church-owned model.</span></li>
                    <li><span>It fails <strong>closed</strong>: with no church context bound, queries return nothing — never
                        everything.</span></li>
                    <li><span>Cross-tenant lookups by ID return <em>not found</em>, revealing nothing about whether a record
                        exists elsewhere.</span></li>
                    <li><span>Roles and permissions are scoped per church; a fixed platform catalogue defines what a
                        permission means.</span></li>
                </ul>
            </div>

            <div class="cf-card">
                <h3 style="margin-bottom:.9rem">Passwords and access</h3>
                <ul class="cf-checks">
                    <li><span>Bcrypt hashing throughout — no plaintext, no raw SQL, ever.</span></li>
                    <li><span>CSRF protection and login rate limiting on by default.</span></li>
                    <li><span>Cryptographic secret stripping in the audit log.</span></li>
                    <li><span>Pastoral visibility requires an explicit permission or being the assignee — nothing else grants
                        it.</span></li>
                </ul>
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------------------------ roadmap --}}
    <section class="cf-section cf-section--tint" id="roadmap">
        <div class="cf-section__head">
            <span class="cf-eyebrow">Where it's going</span>
            <h2 style="margin-top:.5rem">Built in the open, one module at a time</h2>
        </div>

        <div class="cf-grid cf-grid--2">
            <div class="cf-card">
                <h3 style="margin-bottom:1rem">Shipped</h3>
                <div class="cf-grid" style="gap:.55rem">
                    @foreach (['Foundation — auth, tenancy, RBAC, audit log', 'Church & People — members, families, departments', 'Activities — events, attendance, calendar', 'Finance — accounts, budgets, loans, approvals', 'Subvention — configurable rule engine', 'Pastoral Care — cases, prayer, appointments'] as $item)
                        <div style="display:flex;align-items:center;gap:.6rem">
                            <span class="cf-badge cf-badge--ok">Live</span>
                            <span class="cf-small">{{ $item }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="cf-card">
                <h3 style="margin-bottom:1rem">Next</h3>
                <div class="cf-grid" style="gap:.55rem">
                    @foreach (['Communication — email and SMS wallet', 'SaaS billing — payment-gated registration', 'Onboarding — guided product tours', 'Hardening — deployment and scale-out'] as $item)
                        <div style="display:flex;align-items:center;gap:.6rem">
                            <span class="cf-badge">Planned</span>
                            <span class="cf-small">{{ $item }}</span>
                        </div>
                    @endforeach
                </div>

                <hr class="cf-divider">

                <p class="cf-small cf-muted">
                    Migrating from an existing system? ChurchFlow includes a documented
                    migration path, including a read-only import of legacy denominational
                    data that never carries old credentials forward.
                </p>
            </div>
        </div>
    </section>

    {{-- ---------------------------------------------------------------------- cta --}}
    <section class="cf-section">
        <div class="cf-card" style="text-align:center;padding:clamp(2rem,5vw,3.4rem);box-shadow:var(--shadow-md)">
            <h2>Set up your church workspace</h2>
            <p class="cf-muted" style="max-width:34rem;margin:.7rem auto 0">
                Create an account and you're the owner — every module available immediately,
                with your own isolated data from the first request.
            </p>

            <div class="cf-cta-row" style="justify-content:center;margin-top:1.6rem">
                @auth
                    <a href="{{ route('dashboard') }}" class="cf-btn cf-btn--primary">Go to dashboard</a>
                @else
                    <a href="{{ route('register') }}" class="cf-btn cf-btn--primary">Create your account</a>
                    <a href="{{ route('login') }}" class="cf-btn cf-btn--secondary">I already have one</a>
                @endauth
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------------------------- footer --}}
    <footer class="cf-footer">
        <div class="cf-footer__inner">
            <div class="cf-footer__col">
                <a href="{{ url('/') }}" class="cf-sidebar__brand" style="padding:0 0 .5rem">
                    <span class="cf-sidebar__mark" aria-hidden="true">CF</span>
                    <span>ChurchFlow</span>
                </a>
                <p class="cf-small cf-muted" style="max-width:22rem">
                    The operating platform for modern churches, ministries and
                    multi-branch denominations.
                </p>
            </div>

            <div class="cf-footer__col">
                <h4>Platform</h4>
                <a href="#platform">Modules</a>
                <a href="#finance">Finance</a>
                <a href="#trust">Security</a>
                <a href="#roadmap">Roadmap</a>
            </div>

            <div class="cf-footer__col">
                <h4>Get started</h4>
                @auth
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <a href="{{ route('members.index') }}">Members</a>
                    <a href="{{ route('finance.accounts.index') }}">Accounts</a>
                @else
                    <a href="{{ route('register') }}">Create an account</a>
                    <a href="{{ route('login') }}">Sign in</a>
                @endauth
            </div>

            <div class="cf-footer__col">
                <h4>Built on</h4>
                <a href="https://laravel.com/docs" target="_blank" rel="noopener">Laravel 12</a>
                <a href="{{ url('/') }}">Self-hostable</a>
            </div>
        </div>

        <div class="cf-footer__base">
            <span>&copy; {{ date('Y') }} ChurchFlow. All rights reserved.</span>
            <span>Multi-tenant &middot; Audit-logged &middot; Data stays yours</span>
        </div>
    </footer>

    <script>
        // Add the hairline under the top bar once the page has scrolled, so the hero
        // can bleed into the header without a visible seam at rest.
        (function() {
            var bar = document.getElementById('mkbar');
            if (!bar) return;
            var onScroll = function() {
                bar.classList.toggle('is-stuck', window.scrollY > 8);
            };
            onScroll();
            window.addEventListener('scroll', onScroll, {
                passive: true
            });
        })();
    </script>

</body>

</html>

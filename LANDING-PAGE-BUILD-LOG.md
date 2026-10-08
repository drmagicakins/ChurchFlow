# ChurchFlow — Landing Page Build Log

Running record of work done on the public marketing site. Append a new entry per
session; never rewrite history here, because the value of this file is knowing
_why_ something is the way it is when a later change appears to contradict it.

---

## Session 2026-09-26 — Landing page rebuilt from `churchflow-landing-page.zip`

### Starting state

The zip had already been unpacked and partially ported: the design tokens had been
translated from Tailwind v3 into the project's v4 `@theme` block, and eleven
marketing components existed under `resources/views/components/marketing/`. What
was missing was everything that makes a landing page work as a page:

- **The landing page did not render at all.** It was a hard 500. More on this below.
- `HomeController` returned `view('pages.marketing.home')` from **all eleven**
  methods, so `/features`, `/pricing`, `/about`, `/demo`, `/contact`, `/support`,
  the three resource pages and both legal pages were the home page wearing a
  different URL. Every nav and footer link led back to the same content.
- The nav logo was `<img h-9 w-auto>` on the supplied PNG, which is a **full
  lockup** (mark + wordmark + tagline on a black plate). At nav size the wordmark
  rendered at roughly 4px and the black plate showed as a dark rectangle.
- Roughly two-thirds of the reference design was absent: no dashboard mockup
  beyond four stat tiles, no trust strip, no interactive product explorer, no FAQ,
  no page-level SEO, no sub-pages.
- Pricing carried `₦XX,XXX`, and testimonials carried invented quotes attributed
  to named, real-sounding churches (`RCCG, Lagos Province`).

### Bugs found and fixed

**1. The landing page 500'd — `layouts.marketing` did not resolve.**
`resources/views/layouts/marketing.blade.php` was referenced as
`<x-layouts.marketing>`, but `layouts` is not a registered component namespace and
the app's own shell is `<x-layout>` → `components/layout.blade.php`. Error was
`Unable to locate a class or view for component [layouts.marketing]`.
**Fix:** moved the file to `resources/views/components/marketing-layout.blade.php`
so `<x-marketing-layout>` resolves by convention. Chosen over registering a
`layouts` namespace because a namespace for one file is more machinery than the
problem needs.

**2. `/get-started` 500'd — second class in a PSR-4 file.**
`MarketingSignupController` was written as a second class inside
`HomeController.php`. PSR-4 maps one class per file, so the container could not
resolve it: `Target class [...] does not exist`.
**Fix:** split into `app/Http/Controllers/Marketing/MarketingSignupController.php`.

**3. Scroll-reveal left content invisible.**
Measured in the browser: **47 of 49** `[data-reveal]` elements sat at
`opacity: 0` on load. `IntersectionObserver` only fires on scroll, which misses
several real cases:

- a deep link (`/#pricing`) puts the visitor mid-page, and everything **above**
  their landing point has already been scrolled past and never intersects from
  the top — it stays invisible for the whole visit;
- browser scroll restoration after a refresh, same problem;
- printing / "Save as PDF" renders the full document height at once and the
  observer never runs, so the PDF is blank between sections.

**Fix (two independent guards):**

- `app.js`: a `sweep()` pass that reveals anything not still ahead of the
  viewport, run after `load`, again at 1.5s, and immediately if
  `scrollY > 0`. Plus `beforeprint` → reveal everything.
- `app.css`: an `@media print` block forcing revealed state, because a
  stylesheet rule is the only guard that survives the print pipeline re-rendering.

Verified: a deep link to `#pricing` now leaves **0** elements hidden above the
landing point.

**4. The feature explorer tabs were decorative.**
Clicking a module card scrolled to `#explorer` but left the _previous_ panel
selected — `data-explorer-target` was written into the markup but nothing ever
read it.
**Fix:** a delegated `window` click listener registered on `x-init` inside the
explorer component, which calls `select(card.dataset.explorerTarget)`. Delegation
rather than per-card Alpine state so exactly one component owns `active`.

Verified: clicking the Subvention card scrolls to `#explorer` **and** selects
Subvention; keyboard ArrowRight on the tablist moves Finance → Subvention;
clicking a tab leaves **exactly one** panel visible.

**5. Nav defects.**

- Resources dropdown opened on `:mouseenter` but only closed on `@mouseleave`, so
  a keyboard user could open it and never dismiss it. Now: click/Enter to open,
  Escape to close, outside-click to close, chevron reflects state.
- Mobile menu links navigated but never closed the drawer. Now every link calls
  `close()`.
- No active-page state — every link looked identical on every page. Now
  `request()->routeIs()` drives `.is-current` + `aria-current="page"`.
- Authenticated visitors were sent to the sign-up form. Now the CTA points at
  `/dashboard` and offers `Sign out`.
- Bar had no fixed height while `html { scroll-padding-top: 5.5rem }` assumed one,
  so in-page anchors landed under the bar. Now fixed at 4.5rem, matching.

### Brand identity decisions

The supplied `public/images/churchflow-logo.png` is a **full lockup** — mark,
wordmark and tagline composited onto a black background. Two consequences:

- it cannot be used at nav size (wordmark ≈ 4px, black plate on a white bar), and
- it cannot be recoloured with a filter. The previous footer used
  `brightness-0 invert`, which flattened the blue cross and green accent into
  flat white — discarding the only two brand colours in the asset.

**Resolution:** the mark is redrawn as inline SVG in
`components/ui/brand-mark.blade.php` (house with rounded gable, cross on the door,
two wave strokes reading as "flow", the lower one green), and the wordmark is set
as **live text** in `components/ui/brand-logo.blade.php` — `Church` in navy/white,
`Flow` always green. That two-tone split _is_ the wordmark.

Three colours were read off the logo and are now the whole palette: navy
`#0b2a5b`, blue `#1677ff`, green `#18b981`. A fourth hue was deliberately not
introduced.

`<x-ui.icon>` was added as a shared 24×24 stroke icon set — previously five
sections each inlined their own SVG and four of those paths were **the same
generic glyph**, so Members, Finance, Subvention, Events, Reports and "And More"
all showed an identical hamburger.

### Content moved to config

All copy, prices, statistics and testimonials now live in
`config/marketing.php`, per brief §37. Two deliberate choices:

- **Every plan price is `null`.** The brief forbids shipping invented pricing. A
  null renders as "Price on request" — honest — whereas any number invented here
  would be read as a real price by whoever builds the billing engine next.
- **Testimonials are labelled placeholders** (`testimonials_verified => false`),
  with a visible notice on the section. The previous version attributed invented
  quotes to named real-sounding churches, which is a fabricated endorsement of a
  specific organisation. Set the flag to `true` once real permissioned quotes are
  in and the notice disappears on its own.
- **Contact details and social links are `null`.** The footer renders no social
  icons rather than linking to `"#"`, and the contact page says plainly that
  channels are not configured instead of rendering a dead `mailto:`.

### Built this session

**Home page** — hero with a full dashboard mockup (chrome bar, 10-item sidebar,
four stat tiles, paired income/expense bar chart with axis and legend, upcoming
events, activity feed) plus a floating phone preview; trust strip; challenge;
solution grid; 5-step onboarding; module grid; interactive feature explorer; 4-plan
pricing with a dark billing-terms panel; testimonials; FAQ; CTA band; 4-column
footer.

**Sub-pages, each with unique title/description and its own content** —
`/features` (per-module anchored detail blocks, 14-row comparison matrix),
`/pricing`, `/about`, `/demo` (vertical-tab guided tour with narration),
`/contact`, `/support`, `/resources/blog` (honest empty state — no fabricated
posts), `/resources/help-center`, `/resources/guides`, `/legal/privacy`,
`/legal/terms`.

Both legal pages carry a **visible "draft, not legally reviewed" notice** driven
by `config('marketing.legal_reviewed')`. Publishing an unreviewed policy as
finished is real exposure, so the default shows unverified as unverified.

### Onboarding order (load-bearing)

`config('marketing.steps')` is five steps, not four, and is explicitly
**payment-first**: choose plan → pay → **we verify it** → church created → set up.
The brief (§12) forbids presenting ChurchFlow as free-to-start, and treating a
client-side "payment successful" screen as proof is the exact behaviour to avoid.
`MarketingSignupController` deliberately creates nothing — no user, no tenant, no
subscription — because a landing-page route is the easiest place to accidentally
provision a tenant before payment clears.

### Verification performed

Everything below was checked in a real headless browser, not inferred:

| Check                         | Result                                                                              |
| ----------------------------- | ----------------------------------------------------------------------------------- |
| All 12 marketing pages        | HTTP 200, unique `<title>`, exactly one `<h1>`, 0 console errors, 0 failed requests |
| `/get-started` plan whitelist | `?plan=growth` forwards; `../../etc` and `bogus` are dropped                        |
| Explorer tab click            | Exactly one panel visible; 4 rows render                                            |
| Explorer keyboard             | ArrowRight advances Finance → Subvention                                            |
| Module card → explorer        | Scrolls **and** selects the target panel                                            |
| Mobile menu                   | Opens; navigating to Pricing closes it                                              |
| Mobile (390×844)              | No horizontal overflow; 0 elements hidden                                           |
| Deep link to `#pricing`       | 0 elements stuck at `opacity: 0` above the landing point                            |
| Guest nav                     | `Login` + `Get Started` → `/register`                                               |
| Authenticated nav             | `Dashboard` + `Sign out`; no signup form                                            |
| Landing → app                 | Sign in → `/dashboard`, 9 cards + 20 nav links with real tenant data                |
| App → landing                 | Nav "Dashboard" returns to `/dashboard`                                             |
| Production build              | Succeeds                                                                            |

**Note for the next session:** verifying the authenticated nav required resetting
the test account password (`owner@church.test`) to `password` via a throwaway
script, which has since been deleted. If that account's password was not
originally `password`, it has been changed.

### Known gaps / deliberate omissions

- **The billing engine does not exist.** No plans table, no Paystack/Flutterwave
  integration, no SMS wallet. `routes/marketing.php` keeps the intended
  post-registration route shape commented out. `MarketingSignupController::start()`
  is the single seam to change when it lands.
- **Prices are null** until billing config exists — see above.
- **No blog posts, no video demo, no contact form, no ticket form.**
  Each of these would require content or a backend that does not exist, and
  shipping a form that silently discards input (or a player with nothing in it)
  is worse than not offering the feature. Each is written to accept real data
  without restructuring: `$posts` in the blog view, the `contact` block in config.
- **Legal pages are unreviewed.** See `legal_reviewed`.
- **The raster logo is still in `public/images/`** and is used as the
  favicon/OG image. It is no longer used in the nav, footer or auth pages. Replacing
  it with a proper transparent-background asset remains worthwhile, particularly
  for the OG image, which currently has a black plate.

### Files touched

New: `config/marketing.php`, `components/ui/brand-mark.blade.php`,
`components/ui/brand-logo.blade.php`, `components/ui/icon.blade.php`,
`components/marketing-layout.blade.php` (moved from `layouts/marketing.blade.php`),
`components/marketing/{page-header,trust-strip,faq}.blade.php`,
`app/Http/Controllers/Marketing/MarketingSignupController.php`,
`pages/marketing/{features,pricing,about,demo,contact,support}.blade.php`,
`pages/marketing/partials/billing-note.blade.php`,
`pages/marketing/resources/{blog,help-center,guides}.blade.php`,
`pages/marketing/legal/{privacy,terms}.blade.php`.

Rewritten: `components/marketing/*` (all eleven), `pages/marketing/home.blade.php`,
`resources/js/app.js`, `app/Http/Controllers/Marketing/HomeController.php`,
`routes/marketing.php`.

Appended: `resources/css/app.css` — a `mk-*` marketing design system, motion and
a11y layers. **No existing rule was modified**; the app shell's `.cf-*` classes
are untouched, and the `mk-` prefix prevents a repeat of the `.cf-card` collision
the port already had to work around.

Edited: `auth/login.blade.php`, `auth/register.blade.php` — the `CF` initials
placeholder was replaced with the real brand lockup so the sign-in page looks like
the landing page the visitor arrived from.

---

## Session 2026-10-02 — Finishing the `churchflow-dashboard-changes.zip` integration

### Starting state

`_setup/DASHBOARD-CHANGES.md` (the zip's own changelog) lists five passes. Four of
them had landed; the fifth had not, and nobody had noticed because the parts that
did land were the visible ones.

| Pass | Contents                                                                                                                                         | Status on arrival  |
| ---- | ------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------ |
| 1    | `DashboardMetricsService`, `dashboard/kpi` + `dashboard/trend`, `DashboardController`, `dashboard.blade.php`, sidebar/topbar re-skin, `cfd-` CSS | Applied            |
| 2    | `GlobalSearchService`, `SearchController`, `search/index`, 2 routes, topbar search overlay                                                       | Applied            |
| 3    | Mobile-width fixes to the KPI grid and topbar                                                                                                    | Applied            |
| 4    | **Guest auth shell, `auth/field` partial, rebuilt `login`/`register`, `AuthPagesTest`**                                                          | **Missing**        |
| 5    | Documentation of the known gaps                                                                                                                  | Read, not acted on |

Pass 4's four files were absent: `components/layouts/auth.blade.php`,
`components/auth/field.blade.php`, and `tests/Feature/AuthPagesTest.php`, with
`login.blade.php` / `register.blade.php` still holding their stub content. The
zip's own note about this ("login is currently broken independent of anything
here") was accurate, and the earlier landing-page session had since re-pointed
those two views at `<x-marketing-layout>` — which made them _render_, but as
marketing pages: the site nav, the marketing footer, and a "Get Started" CTA,
with no `cf-auth` shell and no `AuthPagesTest` locking the contract.

### What the stub actually was

The zip describes the stubs as `<x-layout><h1>Sign In</h1></x-layout>`. Worth
recording _why_ that is not merely ugly: `<x-layout>` is the **authenticated**
shell. It calls `auth()->user()`, renders the sidebar and the permission-filtered
nav, and reads `$user->roles` for the topbar avatar. Wrapping `/login` in it means
the sign-in page tries to render a signed-in user who by definition does not
exist. A guest page must not share a shell with an authenticated one.

### Built this session

**`components/layouts/auth.blade.php`** — the guest shell. Brand lockup, centred
panel, `noindex`, no sidebar, no topbar, no `auth()` calls. It reuses the
`.cf-auth*` classes that already existed in `app.css` and `partials/theme.blade.php`
with no view using them, so this finishes a page that was already scaffolded for
rather than introducing a design. It lives under `components/` so `<x-layouts.auth>`
resolves by convention — the same rule that fixed `<x-marketing-layout>` after the
`layouts.marketing` namespace bug. (The zip's changelog said
`resources/views/components/layouts/auth.blade.php`; that is a `components/` path,
not `resources/views/layouts/`, and it is the one that resolves.)

**`components/auth/field.blade.php`** — one labelled input, shared by both pages.
The old markup repeated the same five lines per field and had already drifted:
login labelled its inputs with `.cf-stat__label`, a class named for the dashboard's
stat tiles, borrowed because it happened to look right. Errors now render inline
per field as well as in the summary, because the login form's single error is a
wrong password attached to the `email` key — a summary alone makes it look like the
email is at fault.

**`auth/login.blade.php`, `auth/register.blade.php`** — rebuilt on the guest shell.
Both keep their controller and routes untouched; only the views were broken.

**`tests/Feature/AuthPagesTest.php`** — 14 tests. The load-bearing one is not
"does /login return 200" (the broken stub returned 200) but
`assertStringNotContainsString('cf-sidebar', $html)`: a guest page that renders the
app chrome is the failure mode, and it is invisible to a status-code assertion.

### The bug this session found: logout was impossible before payment

Found by driving the real UI, not by reading code. Signing out from a freshly
registered account left the user **still signed in**.

The trace:

1. `POST /logout` → 302, but landing on `/plans` rather than `/` (which is what
   `LoginController::destroy()` returns).
2. The nav still read `Dashboard Sign out`.
3. `/dashboard` → `/plans`, **not** `/login`.

Cause: `/logout` sat inside the middleware group carrying
`IdentifyTenant`. That middleware runs **before** the controller and does:

```php
if (! $user->church_id) {
    return redirect()->route('plans.index')->with('status', '…');
}
```

For a user who has registered but not yet been attached to a church, that redirect
fires first, so `LoginController::destroy()` never executes and the session is
never invalidated. Signing out was impossible from exactly the state — mid-signup,
pre-payment — where a visitor is most likely to want to abandon the account.

`IdentifyTenant`'s behaviour is _correct_ for application pages: a church must
exist before you can use it. The mistake was placing an authentication-lifecycle
route inside a group whose whole job is tenant resolution. **Fix:** moved `/logout`
into the `auth`-only group, beside the checkout routes that are there for the same
reason (their users have no church yet either). It needs nothing but `auth` —
ending a session must never depend on tenant resolution or subscription state.
`EnsureSubscriptionAllowsAccess` still exempts `logout` by name, which stays true
and is still needed for the group it is now outside of.

Verified in the browser both ways:

| User            | Before fix                                                                 | After fix                                                        |
| --------------- | -------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| Church-attached | logs out → `/`, nav `Login Get Started`, `/dashboard` → `/login`           | unchanged                                                        |
| Church-less     | lands on `/plans`, nav still `Dashboard Sign out`, `/dashboard` → `/plans` | logs out → `/`, nav `Login Get Started`, `/dashboard` → `/login` |

Three regression tests were added and **confirmed to fail against the old route
registration** before being kept: `test_a_churchless_user_can_sign_out`,
`test_a_churchless_user_is_actually_logged_out_afterwards`, and
`test_logout_does_not_run_tenant_resolution` (asserts `IdentifyTenant` is absent
from the route's middleware). The assertions are deliberately about the session and
the middleware stack, not the redirect target — the broken build also redirected to
`/plans`, so the redirect alone cannot distinguish the two.

### Verification performed

Everything below was checked in a real headless browser against a running server.

| Check                                                                                         | Result                                                                              |
| --------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| All 15 public pages (`/`, 6 marketing, 3 resources, 2 legal, `/plans`, `/login`, `/register`) | HTTP 200, unique `<title>`, exactly one `<h1>`, 0 console errors, 0 failed requests |
| `/login` as guest                                                                             | `cf-auth` shell, brand lockup, no `cf-sidebar`, no `cf-topbar`                      |
| `/register` as guest                                                                          | `cf-auth` shell, no app chrome, confirm-password field present                      |
| `/dashboard` as guest                                                                         | redirects to `/login`                                                               |
| Register                                                                                      | creates an account, no church, lands on `/plans`                                    |
| `/dashboard` as church-less user                                                              | redirects to `/plans` (correct — payment first)                                     |
| Sign out as church-less user                                                                  | logs out → `/`, `/dashboard` then → `/login`                                        |
| Sign out as church-attached user                                                              | logs out → `/`, nav flips to guest                                                  |
| Wrong password                                                                                | stays on `/login`, shows "These credentials do not match our records."              |
| Feature suite                                                                                 | **146 passed** (425 assertions), 0 failures                                         |
| Unit suite                                                                                    | 6 passed                                                                            |
| Production build                                                                              | succeeds                                                                            |

Test count moved 132 → 146 (+11 `AuthPagesTest`, +3 logout regressions).

### Files touched

New: `resources/views/components/layouts/auth.blade.php`,
`resources/views/components/auth/field.blade.php`,
`tests/Feature/AuthPagesTest.php`.

Edited: `resources/views/auth/login.blade.php`,
`resources/views/auth/register.blade.php` (rebuilt on the guest shell),
`routes/web.php` (moved `/logout` out of the `IdentifyTenant` group).

Deleted: throwaway QA probes under `_setup/` used to trace the logout bug
(`qa-probe.mjs`, `qa-logout.php`, `qa-users.php`). `_setup/qa-auth-flow.mjs` and
`_setup/qa-sweep.mjs` are kept — they are the reusable end-to-end checks.

### Note for the next session

- The QA scripts read the demo owner as `pastor.john@example.test` / `password`
  (church*id=2, from `DemoDataSeeder`). They were **not** re-run after a password
  reset — the credentials were only \_checked*, not changed, this session. The
  landing-page session's note about `owner@church.test` being reset still stands,
  and that account no longer exists in the local database.
- The zip's Pass 5 gap list is still accurate: some module index pages
  (e.g. `events/index.blade.php`) are placeholders with no create form, so a few
  dashboard Quick Actions link honestly to a page with nothing to do yet.

---

## Session 2026-10-02 (later) — Re-running the end-to-end QA sweep against the integrated build

### Starting state

The previous entry closed the `churchflow-dashboard-changes.zip` integration and
left two reusable QA scripts in `_setup/`, plus a note flagging that they had
**not been re-run** since the logout route fix landed. The sweep in
`_setup/qa-sweep.mjs` is the one that matters for this session: it walks all 15
public pages and asserts the four things a broken page cannot hide — HTTP 200, a
unique `<title>`, exactly one `<h1>`, and a clean console/network report.

The concern being tested was real and specific: the logout fix **moved `/logout`
out of the `IdentifyTenant` middleware group** and into the `auth`-only group in
`routes/web.php`. Re-registering a route changes the route collection, and the
sweep visits `/login` and `/register` — the two pages whose group that route now
shares. A regression here would not necessarily be a 500; it could just as easily
be a stray redirect or a missing asset that only shows up as a console error. So
the sweep was re-run in full rather than assumed still-green.

### What was run

Both scripts are driven by the browser-automation harness
(`browser.mjs --script`), which owns the browser, console capture and network
capture; each script only drives and asserts. `qa-sweep.mjs` was already in its
final form (it had been written and saved at 20:09, after the entry above was
composed) — no edit to either script was needed, so none was made.

### Verification performed

Everything below was checked in a real headless browser against a running server
(`php artisan serve`, PHP 8.4.12 / Laravel 12.69.2).

| Check                                                                                                   | Result                                                                                                                               |
| ------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| `qa-sweep.mjs` — all 15 pages (`/`, 6 marketing, 3 resources, 2 legal, `/plans`, `/login`, `/register`) | 15 pages, **0** non-200, **0** pages without exactly one `<h1>`, **0** duplicate titles, **0** console errors, **0** failed requests |
| `qa-auth-flow.mjs` — guest `/login`                                                                     | `cf-auth` shell present, brand lockup present, no `cf-sidebar`, no `cf-topbar`, exactly one email input                              |
| `qa-auth-flow.mjs` — guest `/register`                                                                  | `cf-auth` shell present, no sidebar, confirm-password field present                                                                  |
| `qa-auth-flow.mjs` — guest `/dashboard`                                                                 | redirected to `/login`                                                                                                               |
| `qa-auth-flow.mjs` — register                                                                           | lands on `/plans`, nav reads `Dashboard Sign out`                                                                                    |
| `qa-auth-flow.mjs` — church-less `/dashboard`                                                           | redirected to `/plans` (correct — payment first)                                                                                     |
| `qa-auth-flow.mjs` — sign out as church-less user                                                       | lands on `/`, and `/dashboard` then → `/login` (**the fix from the previous session still holds**)                                   |
| `qa-auth-flow.mjs` — wrong password                                                                     | stays on `/login`, shows "These credentials do not match our records."                                                               |
| Feature + unit suite                                                                                    | **152 passed** (434 assertions), 0 failures                                                                                          |
| Production build                                                                                        | succeeds (`vite build`, 59 modules)                                                                                                  |

The auth-flow result is the load-bearing one for this session: the previous
entry recorded the logout fix as verified, but that verification predated any
re-run. Re-driving the exact state that used to break — a freshly registered,
church-less user clicking Sign out — confirms it end-to-end rather than by
reading the route file.

### Files touched

None. This session **ran** the integration's existing QA scripts and recorded the
result; no application code, view, route, test or script was modified. The value
is the re-run itself, because the previous entry's note explicitly left it
outstanding.

### Note for the next session

- The demo owner credential is `pastor.john@example.test` / `password`
  (church_id=2, from `DemoDataSeeder`). It was **not** touched this session.
  `qa-auth-flow.mjs` registers a throwaway `qa.final.<timestamp>@example.test`
  each run, so those accumulate in the local database; they are church-less and
  harmless, but the local DB is not pristine.
- Test count moved 146 → 152 (434 assertions) since the previous entry. The
  difference is **not** from this session — no tests were added or removed here.
- The zip's Pass 5 gap list is still accurate and unchanged: some module index
  pages (e.g. `events/index.blade.php`) remain placeholders with no create form,
  so a few dashboard Quick Actions link honestly to a page with nothing to do yet.
- `php` and `node` are **not on the system PATH** on this machine. The working
  binaries are `C:\php\php.exe` (8.4.12) and
  `C:\Program Files\nodejs\node.exe`. `npm run build` fails with a bare
  `'"node"' is not recognized` unless Node is prepended to `$env:Path` first —
  that is a PATH problem, not a build problem.

---

## Session 2026-10-03 — Rebuilding assets and confirming the naira glyph fix

### Starting state

The previous session left one thing outstanding and the build log said so
explicitly: `npm run build` needs Node prepended to `$env:Path` first. Nothing
had been rebuilt since the `cf-money` font change, so `public/build/assets/`
still held the pre-change CSS (`app-CLqEzrxG.css`, written 2026-10-02T21:58).

The other half of this session is the `cf-money` change itself. It was made on
the strength of a **measured** claim — that Inter and ui-sans-serif draw U+20A6
(₦) at essentially the same advance width as a capital "N" (18.67 vs 19.00 at
the KPI's 800-weight / 24.8px), so "₦0" reads as "N0". That claim was recorded
in a CSS comment (`.cf-money` in `app.css`, and again in
`components/dashboard/kpi.blade.php`) but never re-confirmed after the class
landed in a built bundle. A comment that asserts a measurement is a liability if
nobody re-checks it.

### What was run

1. **Rebuilt assets.** Node prepended to PATH per the previous session's note;
   `npm run build` → `vite v7.3.6`, **63 modules transformed**, clean exit. The
   CSS chunk hash moved `app-CLqEzrxG.css` → `app-B0p-X0qF.css` and the file
   grew to 96.09 kB, so the bundle on disk is genuinely the new one rather than
   a no-op rebuild.

2. **`_setup/qa-money-verify.mjs`** against the running server, driven by the
   browser-automation harness. The script signs in as the seeded demo owner
   (`pastor.john@example.test`), loads `/dashboard`, finds the first
   `.cfd-kpi__value` containing ₦, then reports the code point, the resolved
   font stack, and the ₦ vs "N" advance widths measured **in the family that
   actually resolved for that node**.

### Verification performed

| Check                              | Result                                                                |
| ---------------------------------- | --------------------------------------------------------------------- |
| `npm run build`                    | succeeds, 63 modules, CSS hash changed (new bundle on disk)           |
| Page load                          | HTTP 200, 0 console errors, 0 failed requests                         |
| First code point of the KPI amount | **U+20A6** — the real naira sign, not a lookalike "N"                 |
| Resolved `font-family`             | `"Segoe UI Symbol", "Noto Sans Symbols 2", "DejaVu Sans", sans-serif` |
| Resolved `font-weight`             | 800                                                                   |
| `cf-money` class applied           | true                                                                  |
| ₦ vs "N" advance width             | **20.77 vs 18.56** → distinct, so ₦ cannot read as an N               |

The decisive figure is the last one. The original problem was ₦ and "N"
measuring 18.67 vs 19.00 — within 2% of each other, which is why the glyph read
as a plain letter. After the class change the gap is **20.77 vs 18.56**, a
≈12% difference in the opposite direction (₦ now _wider_ than N), which is what
a distinct currency symbol should do. The claim in the CSS comment therefore
still holds against the bundled, painted result — not just against the source.

### Files touched

None modified in `resources/` or `app/`. This session **rebuilt** the assets and
**ran** an existing QA script. The only file changed is this log.

### Note for the next session

- The demo owner credential is still `pastor.john@example.test` / `password`,
  and it was read, not changed, this session.
- `public/build/` now holds the current bundle (`app-B0p-X0qF.css`,
  `app-E_IX6aYY.js`). Re-running `npm run build` after any `app.css` or `app.js`
  edit is still required — the manifest hash is what the blade `@vite` directive
  resolves, so a stale build silently serves old CSS.
- The fonts the `cf-money` stack relies on are **system fonts**
  (`Segoe UI Symbol` first, Windows-only). The stack degrades to `sans-serif`
  on a host without any of the three named families, and that fallback is
  exactly the rendering this change was made to avoid. Worth a bundled webfont
  if the app is ever deployed on Linux hosts; not changed here because the
  measured result above is correct on this machine.

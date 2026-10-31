# ChurchFlow — Project Audit: Findings & Improvement Proposals

**Status:** Read-only audit. **No application code, schema, dependencies, UI or database state was changed.**
**Baseline:** `main` @ `d6a1f47`. Working tree: clean except one untracked folder, `ChurchFlow_Audit_Pack/` (preserved untouched).
**Runtime / tests:** PHP 8.4.14 · Laravel 12.69.2 · Node 20.19.6 · **381 tests / 1230 assertions passing** (2026-10-24).
**Companion docs:** `FEATURE_INVENTORY.md` (what exists) · `PRODUCT_ROADMAP.md` (sequencing) · `CUSTOM_ADDONS_ARCHITECTURE.md` (entitlements design).

---

## Severity scale

| Level  | Meaning                                                                             |
| ------ | ----------------------------------------------------------------------------------- |
| **P0** | Blocks taking money or shipping to real churches. Data-safety or security exposure. |
| **P1** | Customer-visible broken/absent journey, or a control that cannot do its job.        |
| **P2** | Quality, accessibility, scale, observability. Fix before growth, not before launch. |
| **P3** | Cosmetic, documentation, hygiene.                                                   |

---

## 1. P0 — Commercial and security blockers

### P0-1 · Payment gateways are unimplemented stubs

**Status: Confirmed missing.** **Impact: no revenue is possible.**

**Evidence**

```
app/Domains/Subscriptions/Gateways/PaystackGateway.php:19,24,29   throw new RuntimeException(...)
app/Domains/Subscriptions/Gateways/FlutterwaveGateway.php:14,19,24 throw new RuntimeException(...)
```

Every method of both gateways throws. The whole billing chain — checkout, `subscribeNow`, renewals,
dunning, SMS credit purchase — is only exercised through the `Null` gateway, which is why all 381 tests
pass while no real card could ever be charged.

**Why this is P0 and not "a TODO":** the README's own claim ("real Paystack/Flutterwave network code still
throws") is accurate, and every test asserts _behaviour around_ a gateway, never _against_ one. The test
suite is currently structurally incapable of catching a gateway that is missing.

**Reproduction**

```powershell
php artisan tinker --execute="app(App\Domains\Subscriptions\Contracts\PaymentGatewayInterface::class)->charge('x',100,'NGN');"
# RuntimeException: PaystackGateway is not configured for network access in this environment.
```

**Proposed design (no code yet — approval required)**

| Concern                  | Design                                                                                                                                                                  |
| ------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Signature verification   | Per-provider adapter verifies its native scheme (`x-paystack-signature` HMAC-SHA512; Flutterwave `verif-hash`). Reject before reading the body's meaning.               |
| Server-side verification | Never trust the redirect. `verify` call to the provider by reference, then activate.                                                                                    |
| Idempotency              | Reuse the existing three layers (`payment_webhook_events` unique on provider+event, row-locked checkout, `invoices.provider_reference` UNIQUE). Do not invent a fourth. |
| Timeouts / retries       | `Http::timeout(15)->retry(3, 200)`, no retry on 4xx. Charge attempts recorded with provider response code.                                                              |
| Reconciliation           | Daily job re-queries any `pending` checkout older than 30 min against the provider.                                                                                     |
| Secrets                  | `PAYSTACK_SECRET_KEY` / `FLUTTERWAVE_SECRET_KEY` via `.env`; endpoint stays fail-closed (503) when unset — already the behaviour.                                       |
| Tests                    | Contract test against a fake HTTP client (recorded fixtures) **plus** a manual sandbox checklist that must be signed off before "billing done".                         |

**Acceptance criteria**

1. A sandbox test-mode payment completes checkout → church activated → invoice written, with the gateway's
   transaction reference stored verbatim.
2. Replaying the webhook twice produces one invoice and one activation.
3. A bad signature returns 401 and writes no rows.
4. A declined payment leaves the checkout retryable and creates no church.
5. `subscribeNow` and the renewal cycle produce a paid period against sandbox.

**Regression tests to add:** `PaymentGatewayContractTest` (fake client), `WebhookSignatureTest` (per provider),
`SandboxCheckoutJourneyTest` (skipped in CI, tagged `sandbox`).

---

### P0-2 · Storage and feature entitlements are silently unlimited

**Status: Confirmed missing.** **Impact: a Starter church gets Denomination-grade limits.**

**Evidence**

```php
// app/Domains/Subscriptions/Services/PlanLimitService.php:47-52
return match ($resource) {
    'members'  => $plan->max_members,
    'branches' => $plan->max_branches,
    'admins'   => $plan->max_admins,
    default    => null,          // <- every other resource: unlimited
};
```

`assertCanAdd()` returns early when `$limit === null` (`:18-20`). So `storage_mb`, and any feature the
plan JSON names, has **no enforced ceiling anywhere**; nothing calls `assertCanAdd()` with those keys.

**Additional risk:** `PlanFeatureService` exists but is not referenced from any route, job, export or
file-upload path. Feature gating today is UI-only where it exists at all — and the audit brief is explicit
that **UI hiding is not authorization**.

**Correction (2026-10-24, after direct verification):** the paragraph above was wrong about feature gating.
`PlanFeatureService` **is** enforced server-side in two places: `ApiController::assertApiAccess()` returns
402 for `api_access` on all four API endpoints, and `ReportController::export()` returns 403 for
`advanced_reports`. The remaining real gaps are (a) **storage quota is unenforced** — `storage_mb` is a
column nothing reads — and (b) `advanced_reports` gates _export_ but not _viewing_ the report body, which
`reports/show.blade.php:24` states is deliberate ("the figures below are a live preview; exporting and
printing unlock on upgrade"). `custom_domain` is unused because no custom-domain feature exists to gate.

**Proposed design** — see `CUSTOM_ADDONS_ARCHITECTURE.md` §"Entitlement resolution". In short:
one `EntitlementService` resolving plan limits + add-on grants + tenant overrides, one enforcement point per
capability class (controller action, job, export, file upload), and `assertCanAdd()` extended to resolve
storage/feature keys from the same plan JSON rather than a hardcoded `match`.

**Acceptance criteria**

1. Uploading beyond `storage_mb` fails with a 422 naming the current/limit figures, on every upload path.
2. A gated feature is refused **server-side** (403/422) when the plan lacks it, even if a user posts the
   route directly.
3. Existing members/branches/admins limits behave exactly as today (no regression).
4. A test proves a `null` limit is only possible for a genuinely unlimited plan, never for an unknown key.

---

### P0-3 · Setup wizard never captures the church name

**Status: Confirmed missing.** **Impact: every church is created with a placeholder name; the first
required onboarding step can be "completed" without doing anything.**

**Evidence**

```php
// app/Http/Controllers/SetupWizardController.php:26
$data = $request->validate(['step' => ['required', 'string']]);
```

`SetupWizardService::steps()` (`:24-31`) marks `church_profile` as `required => true`, and
`markStepComplete()` (`:62-82`) records completion with no payload. The wizard therefore measures a step
that writes nothing.

**Proposed design:** a dedicated `PATCH /setup/profile` (keep `POST /setup/complete` unchanged for the other
steps) that validates `name` (2–120 chars), writes to the tenant's `Church` inside a transaction, and only
then calls `markStepComplete('church_profile')`. Existing churches get a skip-with-warning path rather than
a forced rename. **Do not** make the name editable through a route that skips `settings.manage`.

**Acceptance criteria:** a church activated from checkout shows its chosen name on dashboard, nav, invoices
and emails; blank/whitespace is rejected; the completion event fires once; `SetupWizardTest` gains a case
asserting the name persists.

---

### P0-4 · No automated dependency or secret scanning

**Status: Confirmed missing.** **Impact: a known-CVE dependency ships silently; a leaked key is never caught.**

**Evidence:** `.github/workflows/ci.yml` runs composer install, migrate, `php artisan test --parallel`, and a
Pint step ending in `|| true`. No `composer audit`, no secret scan, no `npm audit`.

**Proposed change (smallest possible, no app code):**

```yaml
- name: Audit PHP dependencies
  run: composer audit --locked --no-interaction
- name: Audit JS dependencies
  run: npm audit --audit-level=high --omit=dev
```

Plus a pre-commit/CI grep for `APP_KEY=`, `PAYSTACK_SECRET`, long-lived bearer tokens (pattern only — never
print values). `.env` is already gitignored and untracked (verified), so the current exposure is low.

**Acceptance criteria:** CI fails on a high-severity advisory; a deliberately committed fake key pattern
fails the scan.

---

### P0-5 · Lint step cannot fail the build

**Status: Partially implemented (ineffective).** **Impact: style/format regressions are never caught.**

**Evidence:** `ci.yml:63-64` → `vendor/bin/pint --test || true`. The `|| true` makes the step always green.

**Proposed change:** drop `|| true`, and scope the first run to changed files (`pint --test --dirty`) to avoid
a 216-file reformat on day one. A full sweep is a separate, approved commit.

**Acceptance criteria:** introducing a formatting error fails CI.

---

## 2. P1 — Broken or absent customer journeys

### P1-1 · Dunning timing rides on `updated_at`

**Status: Partially implemented.** **Evidence**

```php
// app/Domains/Subscriptions/Services/RenewalAndDunningService.php:114-128
->where('status', 'past_due')->where('updated_at', '<=', now()->subDays(self::GRACE_PERIOD_DAYS))
->where('status', 'grace_period')->where('updated_at', '<=', now()->subDays(self::EXPIRE_AFTER_DAYS))
```

The class's own comment (`:28-33`) flags the risk. Any unrelated write to the row — a plan-change note, a
cancellation flag, an admin touch — resets the grace clock and **extends a delinquent church's access**.

**Proposed design:** additive migration `past_due_at`, `grace_period_started_at`, set inside
`SubscriptionService` status transitions (the single writer today). Dunning filters on those columns; fall
back to `updated_at` for legacy rows so no existing subscription jumps state. Backfill command run once,
idempotent.

**Acceptance criteria:** an unrelated field update on a `past_due` subscription does not extend grace; a
test reproduces exactly that scenario; legacy rows transition identically to before.

### P1-2 · No password reset

**Status: Confirmed missing.** No `password.reset` route, controller or view exists; `resources/views/auth/`
contains register and login only. A church admin who forgets their password has **no self-service
recovery** — the only workaround is the owner resetting a team member, which does not cover the owner.

**Proposed design:** Laravel's standard broker (`Password::sendResetLink`, `Password::reset`) — do **not**
hand-roll tokens. Points to get right here: rate-limit the request endpoint, use a generic success message
(no account enumeration), keep the `users` tenant-scoping intact, and ensure a pre-payment (church-less)
user can still recover.

**Acceptance criteria:** reset email → token → new password → login; unknown email gives the same message;
expired token rejected; brute-force limited.

### P1-3 · Invoice PDFs absent

**Status: Confirmed missing.** Zero `pdf` references under `app/`. Invoices are listable (`billing.invoices`)
but not downloadable, which is a real blocker for churches who need a document for their books.

**Proposed design:** render an invoice from the **stored snapshot** (tax breakdown copied at checkout —
already never recalculated, which is the correct property to preserve), to PDF via a headless renderer.
Do not regenerate numbers from live config.

**Acceptance criteria:** a historical invoice renders the same figures after a platform price/tax change;
download is tenant-scoped and authorized (`billing.manage`).

### P1-4 · Dead-end public contact/support

**Status: Confirmed missing (deliberate and documented).** `pages/marketing/contact.blade.php:10-13` explains
the omission — a form with no recipient is worse than none. That reasoning is right, but the _page_ is still
a dead end for a prospect.

**Proposed design:** either (a) implement a real contact flow — validated form → queued notification to a
configured support inbox → stored `contact_messages` row, with spam protection and an honest "we reply within
X" — or (b) keep the honest state and add a clear route to `/register`. Recommendation: (a), because
"Talk to us" is the CTA for the Enterprise tier, which is otherwise unreachable.

**Acceptance criteria:** submission is stored, acknowledged, and emailed; nothing is silently discarded;
configured-inbox-missing produces a visible "not configured" state, not a fake success.

### P1-5 · Stale documentation counts and handoff

**Status: Stale documentation.**

- `README.md:8` says **371 tests**; actual is **381**.
- `README.md:589` contains a literal `\n` in prose.
- Root `SESSION-HANDOFF.md:4` says "Nothing has been committed" — historical; the parish-pastor work is in
  `d6a1f47`.
- `README` names `SendSmsCampaignJob` / `SendAnnouncementEmailsJob`; `app/Jobs/` is **empty** — the work lives
  in listeners. Not a bug, but a reader will look in the wrong place.

**Proposed change:** documentation-only edit; no behaviour touched. Proposed in the roadmap as Phase 0.

### P1-6 · No committed browser E2E suite

**Status: Confirmed missing.** Phases 12–14 record that several real bugs (CSP-vs-Alpine, confirm-dialog
loader, `$filters` array) were **only** findable in a browser. Those checks were run ad hoc; the repo keeps
`_*.mjs` scratch scripts and screenshots at root rather than a suite.

**Proposed design:** one Playwright project with three journeys that pay for themselves immediately:
signup-to-trial, checkout-to-active (sandbox, tagged), and the authenticated shell with zero CSP violations.
Assertion that matters: `window.Alpine !== undefined` and **no console errors** — the exact class of failure
PHP tests cannot see.

**Acceptance criteria:** suite runs in CI against a seeded church; a reintroduced CSP regression fails it.

---

## 3. P2 — Quality, accessibility, scale, operations

| #     | Finding                                                                                   | Status                     | Evidence                                                           | Proposal                                                                                                               |
| ----- | ----------------------------------------------------------------------------------------- | -------------------------- | ------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------- |
| P2-1  | Storage/feature entitlements (see P0-2 for the blocking half) not wired into jobs/exports | Confirmed missing          | no `assertCanAdd` call outside controllers                         | Enforce in the same choke points as plan limits                                                                        |
| P2-2  | Backups are local-disk only                                                               | Partially implemented      | `app/Console/Commands/BackupDatabase.php` docblock is explicit     | Off-site (S3, different region), encrypt, and a **tested restore** cadence; RPO/RTO written down                       |
| P2-3  | Docker described as reference-only                                                        | Partially implemented      | `DEPLOYMENT.md:100-108`                                            | Separate a real production manifest; keep the reference file untouched                                                 |
| P2-4  | No observability: no correlation IDs, queue-lag or dead-letter visibility                 | Confirmed missing          | no request-id middleware; `failed_jobs` unread                     | Structured logs with request id; dashboard query for failed jobs; alert on queue depth                                 |
| P2-5  | Rate limiting is selective                                                                | Partially implemented      | login, webhook, `/health`, search only                             | Add a baseline authenticated throttle once real traffic exists (do not guess a number now)                             |
| P2-6  | No `sitemap.xml`                                                                          | Confirmed missing          | `public/` has `robots.txt` only                                    | Generate from `routes/marketing.php`; reference it from `robots.txt`; exclude auth/platform paths                      |
| P2-7  | `robots.txt` lacks `Sitemap:` line                                                        | Partial                    | `public/robots.txt`                                                | One-line addition after P2-6                                                                                           |
| P2-8  | No automated a11y checks                                                                  | Needs runtime verification | semantic `aria-*` present in `components/layout.blade.php:129-186` | Add axe-core run inside the E2E suite; fix findings; never remove existing focus/label behaviour                       |
| P2-9  | N+1 risk in list views unmeasured                                                         | Needs runtime verification | `DEPLOYMENT.md:93-98` says this needs traffic data                 | Laravel Debugbar locally or `DB::listen` in a profiling branch; measure before caching                                 |
| P2-10 | No data export / deletion workflow                                                        | Confirmed missing          | no route                                                           | Tenant-scoped export (CSV/JSON) + documented retention; never hard-delete financial/pastoral records                   |
| P2-11 | No 2FA                                                                                    | Confirmed missing          | `SECURITY.md` known gap                                            | Optional TOTP for owners/admins; must not weaken tenant isolation                                                      |
| P2-12 | `composer audit` absent (see P0-4)                                                        | Confirmed missing          | `ci.yml`                                                           | covered by P0-4                                                                                                        |
| P2-13 | Root directory littered with QA artefacts                                                 | Confirmed (hygiene)        | ~40 `_*.mjs`, `_*.png`, `_*.log`, `_suite*.txt` at root            | **Do not delete without approval.** Inventory, then move to a gitignored `_qa/` folder in a dedicated, approved commit |
| P2-14 | README describes missing classes                                                          | Stale documentation        | `app/Jobs/` empty                                                  | Correct the names and note where the work actually lives                                                               |

---

## 4. P3 — Cosmetic / documentation

| #    | Finding                                     | Proposal                                                                 |
| ---- | ------------------------------------------- | ------------------------------------------------------------------------ |
| P3-1 | `composer.json` `"name": "laravel/laravel"` | Cosmetic only; renaming is a separate, approved change                   |
| P3-2 | `README.md:589` literal `\n`                | Fix when the README is next edited                                       |
| P3-3 | Test count drift in README                  | Update to the figure CI prints                                           |
| P3-4 | No `docs/DEVELOPMENT_WORKFLOW.md`           | Create it (this audit's workflow) rather than leaving the rules implicit |
| P3-5 | Legal pages are drafts                      | Keep the draft notice visible until reviewed                             |

---

## 5. UI/UX — proposed improvements

Proposals only. **No markup, CSS or component may change without a scoped task and approval.**

**Guiding rule:** the app already has a coherent system (`cf-*` tokens, `x-form.*`, `x-grid.data-table`,
`<x-empty-state>`). Every proposal below extends it; none replaces it.

| Area                      | Current                                                   | Proposed                                                          | Why                                                                           |
| ------------------------- | --------------------------------------------------------- | ----------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| Billing hub               | Lists invoices, no download                               | Add "Download PDF" per row (P1-3)                                 | Completes an existing table without new layout                                |
| Trial → paid              | Banner + Subscribe now on `/billing`                      | Add the exact charge and next billing date to the confirm step    | Removes the "what will I be charged?" hesitation at the only conversion point |
| Setup checklist           | `church_profile` marks complete with no input             | Inline name field on the step (P0-3)                              | The step currently has no visible action                                      |
| Contact page              | Honest "not configured" state                             | Real form with success/error/loading states (P1-4)                | Enterprise CTA currently ends in nothing                                      |
| Empty states              | Mixed: `<x-empty-state>` in places, inline text elsewhere | Sweep all list views onto the shared component                    | Consistency; already recommended in README Phase 9                            |
| Filtered-empty vs no-data | Data grid distinguishes them; other lists do not          | Apply the same distinction everywhere                             | Telling someone "no records" when they filtered is a wasted trip              |
| Permission-denied         | 403 page                                                  | Explain _which_ permission is missing and who can grant it        | A pastor hitting 403 on Settings has no path forward today                    |
| Subscription expired      | 403 message from middleware                               | Route to `/billing` in the body, not just text                    | The middleware already allows billing routes — the message should use that    |
| Mobile nav                | `cf-navscrim`, aria-expanded present                      | Verify focus trap and Esc-to-close in browser                     | Markup suggests it works; unproven                                            |
| Charts                    | Chart.js via bundle                                       | Confirm figures against the ledger in a test, not just visually   | A chart that is pretty and wrong is worse than none                           |
| Loading states            | Global "Working…" overlay                                 | Confirm it never appears for `data-confirm` forms (Phase 14 rule) | Guard the regression already fixed                                            |

**Accessibility specifics to propose (not implement yet):** visible focus rings on all `cf-btn` variants;
`aria-live` on flash messages; labelled search inputs; skip-to-content link; contrast check on the navy
`#0b2a5b` marketing palette; reduced-motion respect for the tour engine and drawer.

---

## 6. Architecture — proposed improvements

| Theme              | Proposal                                                             | Constraint                                                                                   |
| ------------------ | -------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| Entitlements       | One resolver, many enforcement points                                | Must not duplicate `PlanLimitService` logic; extend it (see `CUSTOM_ADDONS_ARCHITECTURE.md`) |
| Billing timestamps | Explicit transition columns (P1-1)                                   | Additive migration only; legacy fallback retained                                            |
| Gateway layer      | Net-new adapters behind the existing `PaymentGatewayInterface`       | Interface and webhook shape must not change                                                  |
| Jobs               | Decide whether `app/Jobs/` is intended or listeners are the standard | README and code currently disagree; pick one and document it                                 |
| Tenancy            | Keep the global-scope model exactly as-is                            | It is the strongest asset in this codebase; do not refactor                                  |
| API                | `routes/api.php` has one route; token middleware exists unused       | Either build the v1 surface deliberately or document it as reserved                          |
| Background work    | Heartbeat exists; dead-letter handling does not                      | Add failed-job monitoring before scaling tenants                                             |

---

## 7. Security — posture summary and proposals

**Verified strengths (do not touch):**

- Global tenant scope fails **closed**; 14 pivot-style models scope through their parent with the same rule.
- Defense-in-depth policies: 23 classes, every one re-checks `church_id` even though the scope should have
  made cross-tenant fetch impossible.
- Pastoral data deliberately does **not** fall back to general admin permissions — negative test present.
- Webhooks fail closed (503 unconfigured / 401 bad secret); three-layer idempotency.
- CSP nonce applied to inline scripts **and** the Vite bundle; `'unsafe-inline'` only in local/testing.
- File uploads: MIME sniffed from content, UUID filenames, private disk, signed URLs, re-checked ownership.
- No raw interpolated SQL; Blade escaping standard.

**Proposals (ordered by risk):**

| Risk                                  | Proposal                                                              | Status             |
| ------------------------------------- | --------------------------------------------------------------------- | ------------------ |
| No dependency/secret scan             | CI steps in P0-4                                                      | Confirmed missing  |
| No 2FA for owners                     | Optional TOTP; owner/admin only                                       | Confirmed missing  |
| Dunning can extend access             | P1-1                                                                  | Confirmed          |
| No account enumeration guard on reset | Include in P1-2                                                       | Design             |
| No data-lifecycle controls            | Export/retention/access logging for pastoral records                  | Confirmed missing  |
| Rate limiting is selective            | Baseline authenticated throttle after traffic data exists             | Partially          |
| No WAF/DDoS guidance                  | Document hosting-level ownership                                      | Documentation only |
| QA artefacts at repo root             | Possible accidental future commit of screenshots containing real data | Hygiene, P2-13     |

**Secrets check performed:** `.env` is **untracked** and ignored (`.gitignore:3`). `vendor/` and
`node_modules/` are untracked. **No secret value was printed in this audit.**

---

## 8. SEO — proposal summary

**Already correct (verified):** marketing layout emits per-page `<title>`, description, canonical without
query string, Open Graph, Twitter card, and `SoftwareApplication` JSON-LD whose prices are deliberately
omitted. `/health` is `noindex`. All 12 marketing views use the layout.

**Gaps and proposals:**

| Gap                                     | Evidence                                             | Proposal                                                                           |
| --------------------------------------- | ---------------------------------------------------- | ---------------------------------------------------------------------------------- |
| No sitemap                              | `public/` has `robots.txt` only                      | Generate from marketing routes; reference in `robots.txt`                          |
| `robots.txt` minimal                    | `User-agent: * / Disallow:`                          | Add `Sitemap:` line; keep auth/platform paths disallowed explicitly                |
| Authenticated pages lack `noindex` meta | `components/layout.blade.php:121` has `<title>` only | Acceptable behind auth; state the decision in docs rather than leaving it implicit |
| Legal drafts indexed                    | Draft notice present                                 | Keep `noindex` until reviewed, then remove                                         |
| No blog/guide content                   | Placeholder views                                    | Do not ship thin pages that compete with the landing page                          |

---

## 9. QA — proposal summary

| Gap                                                 | Proposal                                                                         |
| --------------------------------------------------- | -------------------------------------------------------------------------------- |
| Tests pass with a gateway that cannot charge (P0-1) | Contract tests with a fake HTTP client; sandbox journey tagged and manual-signed |
| No E2E suite (P1-6)                                 | Playwright: signup→trial, checkout→active, shell console-clean                   |
| No a11y checks (P2-8)                               | axe-core inside E2E                                                              |
| Lint never fails (P0-5)                             | Remove `\|\| true`, scope to dirty files                                         |
| No dependency scan (P0-4)                           | `composer audit` + `npm audit`                                                   |
| No tenant-isolation test for new code paths         | Require an isolation test in the definition of done for every new model          |
| Stale counts in README                              | Update after each phase                                                          |

**A warning to record:** the Phase 13/14 notes show several bugs that **only** a real browser found
(CSP vs Alpine, the loader race, the `$filters` array default). A green PHP suite is not evidence that a page
works — it is evidence that the server rendered. Any QA plan that stops at `php artisan test` will repeat
those bugs.

---

## 10. Recommended next work package

**Phase 1 — Billing correctness (P0-1, P0-2, P1-1).** Not because it is the most interesting, but because
nothing else can be commercially validated until a church can pay.

Acceptance criteria for the package:

1. One real gateway adapter verifies signatures and completes a sandbox payment end-to-end.
2. Replay, bad signature and decline are all proven by test.
3. Entitlements resolve from one service and are enforced server-side on at least one gated capability.
4. Dunning no longer depends on `updated_at`.
5. Full suite still green; no existing route, policy or test removed.

**Explicitly out of scope for that package:** UI redesign, marketing copy, add-on catalogue, sitemap, E2E
suite. Those are separate, approved tasks.

---

## 11. What this audit did and did not do

| Action                                           | Result                                                                                                                                                |
| ------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| Files created                                    | `docs/FEATURE_INVENTORY.md`, `docs/PROJECT_AUDIT.md`, `docs/PRODUCT_ROADMAP.md`, `docs/CUSTOM_ADDONS_ARCHITECTURE.md`, `docs/DEVELOPMENT_WORKFLOW.md` |
| Application code changed                         | **No**                                                                                                                                                |
| Routes / controllers / services / models changed | **No**                                                                                                                                                |
| Migrations or database writes                    | **No**                                                                                                                                                |
| Dependencies installed/updated                   | **No**                                                                                                                                                |
| UI / CSS / JS changed                            | **No**                                                                                                                                                |
| Git reset / clean / commit / push                | **No**                                                                                                                                                |
| Tests run                                        | **Yes** — `php artisan test`: 381 passed, 1230 assertions, 0 failures                                                                                 |
| Browser E2E run                                  | **No** — requires a running server and seeded tenants; needs approval                                                                                 |
| Payment sandbox tested                           | **No** — no real credentials; must not be simulated                                                                                                   |
| Dependency/secret scan run                       | **No** — would be the P0-4 change itself                                                                                                              |

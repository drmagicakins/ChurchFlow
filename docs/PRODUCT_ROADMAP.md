# ChurchFlow — Proposed Product Completion Roadmap

**Baseline:** `main` @ `d6a1f47` · 381 tests passing · 2026-10-24.
**Source of findings:** `PROJECT_AUDIT.md`. **Inventory:** `FEATURE_INVENTORY.md`.
This is a **sequenced plan, not an instruction to build everything now.** Each phase requires approval before
implementation, and each has exit criteria that must be met with evidence — not with a document that says so.

---

## Sequencing principle

The order is dictated by one question: **what prevents a real church from paying us and using the product
this week?** That produces the order below. It is deliberately _not_ "the most interesting features first".

```
Phase 0  Baseline & documentation truth      (no code)
Phase 1  Billing correctness                 (P0-1, P0-2, P1-1)   <- revenue
Phase 2  Account recovery & onboarding truth (P1-2, P0-3)        <- can they actually use it?
Phase 3  CI as a real gate                   (P0-4, P0-5, P1-6)   <- stop regressions
Phase 4  Documents & support surfaces        (P1-3, P1-4)        <- customer expectations
Phase 5  Operations & scale readiness        (P2-2..P2-5)        <- before growth
Phase 6  Accessibility, SEO & UI consistency (P2-6..P2-9)        <- before marketing spend
Phase 7  Entitlements & add-on platform      (CUSTOM_ADDONS_ARCHITECTURE.md)
Phase 8  Demand-led feature expansion        (only after demand evidence)
```

---

## Phase 0 — Baseline and documentation truth

**Goal:** make the repository's own claims match reality, so no later phase is planned against a false picture.

**Work**

- Correct README test count (371 → 381), fix the literal `\n`, correct the job-class names now that
  `app/Jobs/` is empty.
- Mark root `SESSION-HANDOFF.md` as historical; replace with the project-wide handoff.
- Inventory the root QA artefacts (`_*.mjs`, `_*.png`, `_*.log`) **without deleting them**.

**Out of scope:** any code, migration, dependency or asset change.

**Exit criteria:** every documented number is reproducible by a command in the doc; no code diff.

**Risk if skipped:** the next engineer plans against a product that does not exist.

---

## Phase 1 — Billing correctness _(the only phase that unblocks revenue)_

**Goal:** a church can pay, be activated, be charged again next month, and be dunned correctly.

| Item                                                                                     | Type      | Depends on          |
| ---------------------------------------------------------------------------------------- | --------- | ------------------- |
| P0-1 Real gateway adapter + signature verification + server-side verify + reconciliation | Feature   | sandbox credentials |
| P0-2 Central entitlement resolution; storage/feature limits enforced server-side         | Feature   | schema review       |
| P1-1 Explicit `past_due_at` / `grace_period_started_at` columns + backfill               | Migration | —                   |

**Order inside the phase:** P1-1 first (smallest, no external dependency), then P0-2 (no external dependency),
then P0-1 (needs sandbox keys). Doing P0-1 first means waiting on credentials for everything else.

**Design constraints**

- Keep `subscriptions.status` as the single source of truth and `churches.status` as the cache written only by
  `SubscriptionService`. Do not introduce a second status field.
- Keep `invoices` as an immutable snapshot — never recompute tax later.
- Reuse the existing three-layer idempotency. Do not add a fourth mechanism.

**Exit criteria**

1. Sandbox payment: checkout → verify → church activated → invoice with provider reference.
2. Replay and bad-signature proven by test.
3. Decline leaves checkout retryable, creates nothing.
4. Renewal charge succeeds against sandbox and writes exactly one invoice.
5. `assertCanAdd()` refuses storage/feature overage on every relevant path, including jobs and exports.
6. Unrelated edits no longer extend grace period (test reproduces the bug first, then passes).
7. Full suite green; no existing test deleted or weakened.

**Risk:** the gateway work touches money. It must be reviewed by a second person and tested in sandbox before
any live key is configured.

---

## Phase 2 — Account recovery and onboarding truth

**Goal:** a customer who forgets their password can recover it, and the first onboarding step does something.

| Item                     | Notes                                                    |
| ------------------------ | -------------------------------------------------------- |
| P1-2 Password reset      | Use Laravel's broker; rate-limit; no account enumeration |
| P0-3 Church name capture | New endpoint; existing churches get a skip path          |

**Exit criteria:** reset journey works end-to-end including for a pre-payment (church-less) user; a church
activated from checkout displays its chosen name on dashboard, nav and invoice.

**Risk if skipped:** a locked-out owner has no path back in, and every tenant carries a placeholder name into
invoices and emails.

---

## Phase 3 — CI as a real gate

**Goal:** the pipeline can actually fail.

| Item                                                      | Notes                                                               |
| --------------------------------------------------------- | ------------------------------------------------------------------- |
| P0-4 `composer audit` + `npm audit` + secret-pattern scan | Small YAML change                                                   |
| P0-5 Remove `\|\| true` from Pint                         | Scope to `--dirty` initially                                        |
| P1-6 Playwright suite (3 journeys)                        | Signup→trial, checkout→active (tagged sandbox), shell console-clean |

**Exit criteria:** a formatting error fails CI; a high-severity advisory fails CI; a reintroduced CSP
regression fails the E2E suite.

**Why after phases 1–2:** the E2E suite is far more valuable once there is a real payment journey to guard.

---

## Phase 4 — Documents and support surfaces

| Item                                  | Notes                                               |
| ------------------------------------- | --------------------------------------------------- |
| P1-3 Invoice PDF from stored snapshot | Never recalculate figures                           |
| P1-4 Real contact/support form        | Stored + queued notification + honest failure state |

**Exit criteria:** historical invoice renders identically after a price change; contact submission is stored,
emailed and acknowledged; unconfigured inbox shows a visible state rather than a fake success.

---

## Phase 5 — Operations and scale readiness

| Item                                                              | Notes                                           |
| ----------------------------------------------------------------- | ----------------------------------------------- |
| P2-2 Off-site backup + **tested restore**                         | Restore test is the deliverable, not the backup |
| P2-3 Production deployment manifest                               | Leave the reference Dockerfile untouched        |
| P2-4 Observability: correlation IDs, queue-lag, failed-job alerts | —                                               |
| P2-5 Baseline authenticated rate limit                            | Only after real traffic data exists             |

**Exit criteria:** a restore from off-site backup into a scratch database is performed and recorded with a
timestamp; queue lag is visible on a dashboard; a killed worker produces an alert.

---

## Phase 6 — Accessibility, SEO and UI consistency

| Item                                            | Notes                                           |
| ----------------------------------------------- | ----------------------------------------------- |
| P2-6/P2-7 Sitemap + `robots.txt` reference      | Exclude auth/platform paths                     |
| P2-8 axe-core in E2E; fix findings              | Must not remove existing focus/label behaviour  |
| P2-9 Measure N+1 and query plans before caching | Measure first, cache second                     |
| UI/UX consistency sweep (audit §5)              | Extends the existing `cf-*` system; no redesign |

**Exit criteria:** zero serious axe violations on the 10 highest-traffic pages; sitemap validates; a documented
before/after for any query optimisation, with the measurement method stated.

---

## Phase 7 — Entitlements and add-on platform

Full design in `CUSTOM_ADDONS_ARCHITECTURE.md`. This phase is deliberately **after** billing correctness: an
add-on platform built on a gateway that cannot charge is a second way to fail.

**Exit criteria:** one catalogue item is purchasable, granted only after verified payment, enforced
server-side, revoked safely without data loss, and fully audited.

---

## Phase 8 — Demand-led expansion

Candidate modules from the audit pack: online giving, event ticketing/QR, document vault, volunteer rota,
room booking, safeguarding, inventory, accounting integrations, white-label, SSO, API.

**Gate:** no module enters this phase without evidence of demand (customer requests, interviews or letters of
intent) and an explicit cost-to-serve estimate. Building before validating is how a roadmap becomes a
catalogue of unused features.

---

## Release gate — what "ready to sell" means here

Not "all phases done". A release is allowed when:

1. A real payment can be taken, verified, refunded (manually if necessary) and reconciled.
2. Every plan limit is enforced server-side.
3. Dunning timings are correct and unaffected by unrelated writes.
4. Tenant isolation tests pass and new models carry their own isolation test.
5. CI fails on format, dependency and E2E regressions.
6. A restore from backup has been performed, not merely scheduled.
7. No public page promises something the backend cannot do.

Anything short of that is a beta, and should be labelled as one.

# ChurchFlow — What Has Been Done, and What Remains

**Written:** 2026-10-31
**Branch:** `main` (HEAD `d6a1f47`) — **all work below is uncommitted**
**Verified this pass:** `php artisan test` → **482 passed (1656 assertions), 0 failed**
**Migrations:** `migrate:status` → **57 migrations, 57 `Ran`, 0 `Pending`**

This document is an analysis of the current working tree. Every claim marked ✅ was checked by running a
command this pass; claims that were not checked are marked ⚠️ or are explicitly described as unverified.
It complements `SESSION-HANDOFF.md` (the chronological record) by answering one question: **what is left?**

---

## 1. What has been completed

### 1.1 The suite is green and the schema is current

| Check                        | Result                                       |
| ---------------------------- | -------------------------------------------- |
| `php artisan test`           | ✅ 482 passed (1656 assertions), 0 failed    |
| `php artisan migrate:status` | ✅ 57/57 `Ran`, 0 `Pending`                  |
| Probe files left behind      | ✅ none (`ZzTemp*` / `ZzProbe*` all deleted) |

The suite grew from **371 tests** at the time of the parish-pastor work to **482** now. Every section in
`SESSION-HANDOFF.md` records the red-proof (fix removed → test fails → fix restored → test passes) for the
guards it added, except where the record explicitly says a guard was _not_ proven red.

### 1.2 Delivered feature work (in the working tree)

| Area                                   | What is done                                                                                                                                   |
| -------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| **Parish pastor permissions**          | Owner ticks per-pastor capabilities (stored as a private per-user role); parish/role changes are owner-only                                    |
| **Department visibility + uniqueness** | `WHOLE_CHURCH = 0` sentinel, `visibleTo()` on route binding, real duplicate-name rule (migration applied)                                      |
| **Parish identity on screens**         | Dashboard names the parish, departments/groups scoped to a pastor's parish, `groups.organizational_unit_id` column                             |
| **Billing correctness**                | Dunning timestamps (`past_due_at`, `grace_period_started_at`) + `StorageLimitService` quota enforcement                                        |
| **Onboarding**                         | `SetupWizardController::completeProfile()` + `routes/setup-profile.php`                                                                        |
| **Starter plan access (§1f–§1i)**      | Owner can always upgrade; loan data-gap errors render the page; signup can no longer create a powerless owner; permission catalogue self-heals |
| **Parish archive (§1j)**               | Owner-only ZIP of per-module Excel workbooks, scoped to a parish or the whole church, read-only                                                |
| **Expired events (§1k)**               | Expired events offer Delete (soft), and take no registrations — owner, pastor or the public link                                               |
| **Budget report fix (§1l)**            | A budget period now includes its final day (was silently dropping the last day's spending)                                                     |
| **Expired public page (§1m)**          | The public link now shows a branded 410 page with the real message, not Laravel's `// No request body` debug screen                            |

### 1.3 Defects found by running the app, not by reading it

These are worth separating from feature work, because they were invisible on paper:

1. **Signup created owners with zero permissions** (§1h) — the owner role was cloned _best-effort_ from a
   system role that could be missing. Two real dev churches were affected and were repaired.
2. **Permission catalogue held 7 rows as `group='x', label='x'`** (§1i) — the editor rendered them under a
   heading literally called "X". The seeder used `firstOrCreate`, so re-running it could never heal them.
3. **Parish archive queried a column four tables do not have** (§1j) — a real export failed with
   `no such column: transactions.organizational_unit_id`.
4. **Budget report excluded its own final day** (§1l) — probed: `period_end` stored `23:59:59`, cast to
   `00:00:00`, so today's spending was never counted.
5. **Expired public page rendered a debug screen** (§1m) — `abort(410, $message)` renders through Laravel's
   exception view; with `APP_DEBUG` on, the visitor saw a stack trace and `// No request body`.

---

## 2. What remains — blocking (must be done before this can ship)

### B1. Nothing is committed ⚠️ **highest priority**

The entire body of work in §1 lives in a dirty working tree: **29 untracked files** plus a long list of
modified ones. Nothing has been pushed. A single bad `git reset` or a lost machine loses all of it.

`SESSION-HANDOFF.md` §5 proposes seven logical commits. **No commit should happen before B2 is resolved.**

### B2. `routes/_bak_web.php` still exists ✅ confirmed present

An untracked backup of the route file (risk **R1**). It is not autoloaded, but a `git add .` will commit it.
**Delete it manually** — the assistant has previously been blocked from removing it.

### B3. `P0-1` — no payment can actually be taken ✅ confirmed

Every method on `PaystackGateway` is a `throw`. Checkout, renewals and webhooks are exercised only against
`Http::fake()`. Until a real sandbox payment completes:

- the subscription lifecycle has **never** been proven end-to-end against a provider;
- `chargeRecurring()` cannot be implemented safely — it needs a schema decision for
  `provider_subscription_reference` first;
- `PAYSTACK_SECRET_KEY` is empty in `.env.example` and there is no sandbox key configured.

---

## 3. What remains — correctness and security

### S1. CI does not fail on lint, and has no dependency or secret scan ✅ confirmed

`.github/workflows/ci.yml` ends with:

```yaml
- name: Lint (Pint, if present)
  run: vendor/bin/pint --test || true
```

The `|| true` means **a lint failure cannot fail the build**. There is also no `composer audit` step and no
secret scan.

### S2. `composer audit` cannot reach Packagist from this machine ✅ confirmed

Running it answered: _"The following exception was raised: SSL certificate problem: unable to get local
certificate issuer bundle"_ — and it then reported from a **stale local cache**. Any dependency-vulnerability
answer you get locally is therefore unreliable. This is an environment problem, not an app bug, but it means
"no known vulnerabilities" has not been verified here.

### S3. No password reset ✅ confirmed

`route:list` shows **no** password-reset routes. A user who forgets their password has no self-service route
back in; only an owner-set password (via Settings → Team) exists.

### S4. Mail is `log` in every environment ✅ confirmed

`MAIL_MAILER=log` in both `.env` and `.env.example`. Every notification, reminder and receipt is written to
`laravel.log` and delivered nowhere. This is deliberate for local development but **must change before real
users exist** — and it is why account creation sets a password inline rather than emailing an invite.

### S5. `APP_DEBUG=true` on the local environment ✅ confirmed

This is what turned a 410 into a stack trace in §1m. Keep it off in any environment a real visitor can reach;
the §1m fix removes the _specific_ occurrence, but any other `abort(4xx, $message)` still renders through the
exception view.

### S6. Queues run on the `database` connection ✅ confirmed

`QUEUE_CONNECTION=database`. Queued work (SMS batches, reminders) only runs while a worker is alive; nothing
supervises one on this machine (risk **R5**).

---

## 4. What remains — product gaps

| #   | Gap                                         | Evidence / note                                                                                                                                                                                                                                                                                    |
| --- | ------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| P1  | **Invoice PDFs**                            | No invoice/PDF class exists anywhere in `app/` or `resources/` ✅ confirmed                                                                                                                                                                                                                        |
| P2  | **End-to-end browser suite**                | All coverage is HTTP-level feature tests; no browser/E2E driver                                                                                                                                                                                                                                    |
| P3  | **Real payment flow**                       | Same as B3 — the largest single product gap                                                                                                                                                                                                                                                        |
| P4  | **Email deliverability**                    | Same as S4                                                                                                                                                                                                                                                                                         |
| P5  | **`custom_domain` is gated but never used** | `config/billing.php` lists it, but `grep api_access\|custom_domain\|advanced_reports` over `app/` returns **no `custom_domain` hit** — unlike `advanced_reports` (`ReportController`) and `api_access` (`Api\V1\ApiController`), which are both enforced. A pricing promise with no implementation |

---

## 5. Known risks still open (carried from `SESSION-HANDOFF.md` §4)

| #       | Risk                                                                            | Status                                             |
| ------- | ------------------------------------------------------------------------------- | -------------------------------------------------- |
| **R1**  | `routes/_bak_web.php` untracked and undeletable by the assistant                | ⚠️ OPEN — delete manually (B2)                     |
| **R3**  | Legacy rows have NULL dunning timestamps, falling back to `updated_at`          | Accepted by design; a backfill command is possible |
| **R4**  | Laravel Herd was stopped to release a file lock                                 | Restart it yourself                                |
| **R5**  | Dev server is detached and unsupervised                                         | Restart with your usual command                    |
| **R6**  | `onboarding/setup.blade.php` 500s if the `setup-profile.php` require is removed | Guarded by a test                                  |
| **R7**  | Storage quota counts `uploaded_files.size_bytes`, not disk usage                | Intentional                                        |
| **R12** | A parish pastor no longer sees whole-church departments in the list             | Intended by the 2026-10-26 request                 |
| **R13** | Four legacy groups are whole-church, so a pastor sees none of them until filed  | Data decision, not a bug                           |
| **R14** | `department_member` pivot rows point at soft-deleted duplicate departments      | Reattach in the UI if it matters                   |
| **R10** | `Department::columnHasBranchForeignKey()` caches the schema answer per process  | Restart workers after a migration                  |

**R2, R9, R11, R15, R16 are CLOSED** — the first three by the 2026-10-27 migration verification (§1g), R15
and R16 on 2026-10-31 (§1l).

---

## 6. Recommended order of work

1. **Delete `routes/_bak_web.php`** (B2) — one manual action, removes a real foot-gun.
2. **Commit the work in the seven logical commits** already proposed in `SESSION-HANDOFF.md` §5 (B1).
   Every one of them is currently green against the full 482-test suite.
3. **`P0-1` payment gateway** (B3) — get a Paystack sandbox key, implement against `Http::fake()` in tests,
   and complete **one** real sandbox payment before calling it done.
4. **CI hardening** (S1) — drop `|| true` from Pint, add `composer audit` and a secret scan.
5. **Password reset** (S3) — the first feature a real user will ask for.
6. **Mailer** (S4) — configure a real transport before onboarding a pilot church.
7. Invoice PDFs (P1) and an E2E suite (P2), in that order.

---

## 7. Honest assessment

The application is **functionally broad and currently green** — staff management, members, families,
branches, departments, groups, events (including a public registration form), attendance, finance with
approvals and budgets, loans, tasks, pastoral care, prayer requests, announcements, SMS, subventions,
subscriptions and billing screens all exist and are covered by tests.

What it is **not** yet:

- **Not releasable** — no payment can be taken and nothing is committed.
- **Not security-audited** — the dependency audit could not reach the network, and CI will not fail on lint.
- **Not independently verified in a browser** — the coverage is HTTP-level; a handful of earlier passes did
  drive a real browser, and those found three bugs that the tests had missed (recorded in
  `SESSION-HANDOFF.md` §5 of the historical section).

The most important sentence in this document: **every claim of "fixed" in the handoff is backed by a red-proof
or is explicitly marked as not**, and the full suite passes — but **none of it is committed**, and no money can
move through the app.

---

## 8. How to reproduce the verification quoted here

```powershell
git status --short                     # 29 untracked files, modified files — nothing committed
php artisan test                       # 482 passed (1656 assertions), 0 failed
php artisan migrate:status             # 57 Ran, 0 Pending
php artisan route:list | Select-String 'password'   # no password-reset routes
Test-Path routes\_bak_web.php          # True  (R1 still open)
composer audit --format=summary        # reports from a stale cache; Packagist unreachable (SSL)
Select-String -Path .github\workflows\ci.yml -Pattern '\|\| true'   # Pint failures are swallowed
Select-String -Path .env -Pattern '^MAIL_MAILER|^APP_DEBUG|^QUEUE_CONNECTION'
```

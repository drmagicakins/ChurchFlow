# Security Posture

A consolidated account of §43's requirements against what this codebase
actually does, phase by phase — written so a reviewer doesn't have to
re-derive it from ten phases of README entries. "Done" here means "built
and covered by a test referenced below", not "designed."

## Authentication & session security

- Passwords hashed via Laravel's default (bcrypt), never compared or
  stored in plaintext — the direct fix for the legacy app's `varchar(20)`
  password columns (Phase 0 analysis).
- Login is rate-limited (`LoginController`, 5 attempts per email+IP, then a
  60-second lockout).
- Registration email uniqueness is scoped correctly (`church_id IS NULL`
  for the pre-payment case) rather than a naive global check that would
  wrongly reject a legitimate signup (Phase 8).
- Sessions regenerate on login and on logout (`LoginController::store`/`destroy`).

## Tenant isolation — the single most load-bearing guarantee in this app

- Enforced structurally via `TenantScope` + the `BelongsToTenant` trait
  (Phase 1), not per-query discipline — a model using the trait fails
  *closed* (zero rows) with no tenant bound, never open.
- Every model one level removed from `church_id` (pivot-shaped tables:
  `EventRegistration`, `AttendanceRecord`, `LoanPayment`,
  `SubventionRule`/`SubventionCalculation`, `SmsCampaignRecipient`,
  `PastoralCaseNote`, `BudgetItem`, `MemberCustomFieldValue`) scopes itself
  through its parent with the identical fail-closed pattern — fourteen
  models, one consistent rule, each with its own isolation test.
- `tests/Feature/TenantIsolationTest.php` is treated as load-bearing enough
  that CI (`.github/workflows/ci.yml`) fails the build separately and
  loudly if that file is ever missing or empty — not just "if its
  assertions fail."
- Platform-admin routes get the opposite treatment deliberately:
  `PlatformAdminOnly` middleware sets `tenant.disabled`, which every scope
  above checks and steps aside for — cross-tenant visibility is an
  explicit, narrow exception with its own access-control tests (Phase 8/9),
  not an accidental side door.

## Authorization

- A Policy per tenant-scoped model, every one of them re-checking
  `church_id` ownership even though `TenantScope` should already have made
  a cross-tenant object impossible to fetch — defense in depth against a
  future relation/refactor bypassing the scope (`BaseTenantPolicy`'s own
  docblock, Phase 1).
- Pastoral care (Phase 6) is the one place policies deliberately do NOT
  fall back to a general admin permission — `pastoral.manage` or being the
  specific assignee, nothing else, tested with a negative case
  (`PastoralCareTest::test_an_ordinary_admin_without_pastoral_permission_...`).

## Financial & pastoral record integrity

- Financial transactions are voided, never deleted or silently edited —
  requires a reason, stays auditable (Phase 4, §48).
- Pastoral case notes are append-only — no update/delete route exists at
  all, verified by a test that inspects the registered routes rather than
  just checking application behavior (Phase 6).
- Loan balances and subvention calculations are always derived from an
  immutable ledger/history, never a mutated running total — the direct,
  tested fix for the legacy app's `loans_svp.total_repay` bug (Phases 4-5).

## Payments

- Webhook endpoint fails closed: no configured secret → every request
  503s; wrong secret → 401 (Phase 8).
- Three-layer idempotency on anything that moves money or credits: a
  logged webhook delivery (unique on provider + event id), a row-locked
  checkout-status check inside the activation actions, and a database
  UNIQUE constraint on `invoices.provider_reference` as the final backstop
  (Phase 8).
- No card or bank-account number is ever stored by this application —
  payment gateways are redirect/webhook-based (`PaymentGatewayInterface`);
  `chargeRecurring()` charges against a gateway-held subscription
  reference, never a number this app holds.

## File uploads (Phase 10 — previously a gap, now closed)

- MIME type is **sniffed from file content**, not trusted from the client's
  `Content-Type` header or filename extension (`FileUploadService`,
  directly implementing §43's "never trust uploaded file extensions").
- On-disk filename is a random UUID, never the client-supplied name —
  prevents path collision/guessing and directory enumeration.
- Stored on a `private` disk outside the public webroot; access requires a
  signed, time-limited URL (`UploadedFile::signedUrl()`), and
  `FileDownloadController` re-checks tenant ownership on every request even
  though the signature already proves the link wasn't tampered with — a
  leaked link doesn't bypass authorization, it only proves the link was
  legitimately generated for someone who could see the file at generation
  time.
- A hard size cap (5MB) and a narrow MIME allow-list (four image types) —
  this pipeline is sized for profile/cover photos, not a general document
  vault; widening it to other file types should re-examine both limits
  deliberately, not inherit them by accident.

## Transport & headers (Phase 10)

- `SecurityHeaders` middleware: `X-Frame-Options: DENY`,
  `X-Content-Type-Options: nosniff`, a conservative `Content-Security-Policy`
  (same-origin only, no inline scripts), `Strict-Transport-Security` when
  the request is already HTTPS, and a `Permissions-Policy` denying
  camera/microphone/geolocation outright since nothing in this app needs
  them.
- nginx config denies serving any dotfile (`.env`, `.git/...`) even if one
  somehow ends up under the webroot.

## Input validation & SQL/XSS

- Every write endpoint validates via a Form Request or inline `validate()`
  call — none of them accepts and persists arbitrary input.
- 100% Eloquent/query-builder parameter binding; zero raw string-interpolated
  SQL anywhere in the codebase — the direct, structural fix for the legacy
  app's SQL-injectable login and CSV-import paths (Phase 0 analysis).
- Blade's `{{ }}` escaping is the default throughout every view; nowhere
  uses `{!! !!}` on user-supplied content.

## Secrets

- `.env.example` ships with every variable named and none filled in
  (§43). `config/services.payments.snippet.php` and
  `config/filesystems.private-disk.snippet.php` are deliberately *snippets
  to merge*, not full config files to drop in overwriting — so a copy
  mistake can't silently discard unrelated config a real project already
  has.

## Known gaps — stated plainly, not glossed over

- **CSRF** relies entirely on Laravel's default (`VerifyCsrfToken` on
  session-based routes); not re-verified here, since it was never touched
  or disabled anywhere in this codebase.
- **No 2FA** has been built (§8 lists it as optional) — would layer onto
  `LoginController` without touching tenant isolation or authorization.
- **No dependency/vulnerability scanning** in CI yet (`composer audit`
  would be a one-line addition to `.github/workflows/ci.yml`).
- **No WAF/DDoS-layer guidance** — assumed to be the hosting
  platform/CDN's responsibility, not application code's.
- **Rate limiting is applied selectively** (login, the payment webhook,
  `/health`) rather than globally by default — a general per-user/per-IP
  throttle on the whole authenticated route group is a reasonable
  addition once real traffic patterns are known, rather than guessed at
  now.

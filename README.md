# ChurchFlow — Phase 1–12 (Foundation → SaaS Billing → Onboarding → Production Hardening → 14-Day Trial → Pricing & Forms)

Product name: **ChurchFlow** (`APP_NAME`, the landing page's nav/footer and
`<title>`). Earlier phase notes below sometimes say "Church SaaS"
generically where they describe the *system* rather than the brand — a
naming artifact of when those phases were written, not a second product.

**Current state: 246 tests / 746 assertions passing.** Phases 10 and 11 are
integrated and verified; Phase 12 (stated pricing across the four tiers, and
styled forms across both dashboards) is built on top of them.

Sections below, in order:
- Phase 1–9 feature notes (unchanged, kept as the historical record)
- **Phase 10** — production hardening (`DEPLOYMENT.md`, `SECURITY.md`)
- **Phase 11** — 14-day trial + subscribe/upgrade/downgrade
- **Phase 12** — stated pricing + the styled form system
- **The integrations** — what the Phase 1–10 drop-in got wrong, and what
  only a real browser caught

## Setup

```bash
composer create-project laravel/laravel church-saas
cd church-saas
composer require laravel/sanctum   # for later API work; harmless now
```

Copy the contents of this package into the new project (folders match
Laravel's layout — `app/`, `database/`, `routes/`, `resources/`, `tests/` —
so just overlay/merge them):

```bash
cp -r app/* /path/to/church-saas/app/
cp -r database/* /path/to/church-saas/database/
cp -r resources/* /path/to/church-saas/resources/
cp -r tests/* /path/to/church-saas/tests/
cat routes/web.php >> /path/to/church-saas/routes/web.php   # merge, don't overwrite
```

Then:

```bash
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
php artisan test tests/Feature
```

Phase 8 (billing) needs a few extra setup steps:

```bash
php artisan db:seed --class=PlanSeeder        # PLACEHOLDER plans/prices — replace before launch
# merge config/services.payments.snippet.php's 'payments' key into config/services.php
# copy .env.example values you need into .env (PAYMENT_WEBHOOK_SECRET, SMS_PRICE_PER_UNIT)
php artisan install:api                       # only if routes/api.php isn't already registered;
                                              # the webhook route lives at POST /api/v1/webhooks/payments
# merge routes/console.php's Schedule::command(...) line into your project's
# routes/console.php, then run the scheduler (cron -> `php artisan schedule:run`
# every minute, or `php artisan schedule:work` in development) for renewals/
# dunning to actually happen. Run it once by hand to see it work immediately:
php artisan subscriptions:process-billing-cycle
```

Phase 9 seeders (optional but recommended — without them there's nothing
for the tour/setup-wizard UI to render):

```bash
php artisan db:seed --class=TourSeeder
php artisan db:seed --class=FeatureFlagSeeder
```

Optional, to see the legacy RCCG rates expressed as configurable rules
(needs at least one church already in the database — register one first):

```bash
php artisan db:seed --class=RccgLegacyRuleSetSeeder
```

Note: `web.php` merged in above references view files under
`resources/views/{members,families,departments,groups,auth,platform-admin}`
and a `<x-layout>` component — all included here, but they're placeholder
markup, not the real UI from §35–§38 of the architecture doc. Swap them for
the actual Livewire/Tailwind design system whenever that's built; nothing
in the controllers or routes depends on how the view is implemented.

Phase 7 introduces two queued jobs (`SendSmsCampaignJob`,
`SendAnnouncementEmailsJob`) and a queued listener
(`NotifySubmitterOfSubventionDecision`). With the default `sync` queue
driver they run inline and need nothing extra; for a real queue connection,
run `php artisan queue:work` alongside the app. The test suite never
depends on a running worker — it either calls the underlying service
directly (`SmsSendingService::send()`) or calls a job/listener's `handle()`
method synchronously.

## What's in this phase

### Phase 1 — Foundation
- **Tenancy**: `churches` table + a global `TenantScope` applied via a
  `BelongsToTenant` trait, so every tenant model is automatically scoped to
  `church_id` with no per-query `where()` to remember. `IdentifyTenant`
  middleware resolves the current church from the authenticated user and
  binds it into the container as `'tenant'`.
- **Users & RBAC**: unified `users` table (one table, not three like the
  legacy app), `roles`/`permissions`/pivot tables, all tenant-scoped except
  a `is_platform_admin` flag that is deliberately **not** tenant-scoped —
  platform staff are never implicitly a church user (see architecture doc
  §53).
- **Organizational hierarchy**: `unit_types` (per-tenant configurable
  labels: Province/Diocese/Region/etc.) + self-referencing
  `organizational_units` tree.
- **Audit log**: `audit_logs` table + an `Auditable` trait/observer that
  records create/update/delete on any model that uses it, storing
  before/after values as JSON.
- **Auth**: minimal registration/login controllers using Laravel's built-in
  hashing (bcrypt) — no plaintext passwords, no raw SQL, no separate
  admin/super_admin/registration tables.

### Phase 2 — Church & People (§10, §11)
- **Full member CRM**: `members` table expanded with membership number
  (auto-generated, per-church, e.g. `MB-2026-0007`), branch assignment,
  photo, gender, DOB, contact info, emergency contact, membership status,
  join/baptism dates, worker flag, notes. `MemberController` gives
  search/filter (status, branch, department)/sort/pagination/bulk status
  update; every bulk action re-checks the policy per row rather than
  trusting the ID list wholesale.
- **Families** (§11): `families` + `family_member` pivot with a
  relationship label (head/spouse/child/dependent/other).
- **Departments & Groups**: many-to-many with members, departments also
  optionally tied to an organizational unit (branch-specific department).
- **Custom fields** (§10): church-configurable `member_custom_field_definitions`
  + per-member values — the one place a value table has no `church_id` of
  its own, so it scopes itself through its parent `member` instead (see the
  comment in `MemberCustomFieldValue`).
- **CSV import/export**: `ImportMembersFromCsv` reads by **header name**
  (not position, unlike the legacy `uploadcsv.php`), validates every row
  independently, and never lets one bad row abort or corrupt the rest of
  the batch. `ExportMembersToCsv` streams in chunks of 500 rather than
  loading the whole membership into memory.

### Phase 3 — Activities (§12–§14)
- **Events** (§13): capacity-aware registration — `Event::hasCapacityFor()` is
  checked inside a `lockForUpdate()` transaction in `EventController::register`,
  so two people registering for the last open slot at the same moment can't
  both get confirmed; the loser is waitlisted, not rejected.
- **Attendance** (§12): `attendance_sessions` (tied to a branch, event,
  department, or group) + `attendance_records` (present/absent/excused/late).
  `AttendanceController::recordBulk` takes a whole roster's statuses in one
  request — the real "take attendance" screen's submit action, not one
  request per member. `AttendanceAnalyticsService` gives trend-over-time,
  per-member history, and per-member attendance rate.
- **Calendar** (§14): deliberately **not its own table** — `CalendarService`
  aggregates events and open tasks live by date range, so a rescheduled
  event or a completed task can never leave a stale calendar entry behind.
  Extend it the same way once department activities/birthdays are modeled.
- **Announcements** (§19 preview) & **Tasks**: announcements carry an
  audience type (church/branch/department/group) and
  `Announcement::isVisibleToMember()` for filtering a member's feed; tasks
  are simple assignable to-dos, optionally tied to an event.
- Three more model classes here (`EventRegistration`, `AttendanceRecord`,
  and reused from Phase 2, `MemberCustomFieldValue`) have **no `church_id`
  column of their own** and instead scope themselves through their parent
  (`event`/`attendanceSession`/`member`) — the same pattern introduced in
  Phase 2, now applied consistently everywhere a pivot-like table sits one
  level removed from `church_id`.

### Phase 4 — Finance (§15, §18, §24, §48)
- **The shared Approval engine** (`App\Domains\Approvals\Services\ApprovalWorkflow`)
  makes its first real appearance here, exactly as planned in the Laravel
  architecture doc: one polymorphic `approvals` table + a single-step
  submit→approve/reject workflow that any model can plug into by carrying
  a `church_id` and an `approval_status` column. Subvention (Phase 5) reuses
  this engine rather than building its own.
- **Transactions, not a mutable balance column**: `FinancialAccount::balance()`
  is computed live from the transaction ledger every time, never stored and
  updated in place — the deliberate fix for the legacy app's
  `loans_svp.total_repay`, which was overwritten by a report page and could
  silently drift from reality.
  - **Income/donations** post immediately (`approval_status = not_required`).
  - **Expenses** are created `pending` and immediately submitted into the
    Approval workflow in the same DB transaction — an expense can never
    exist without a linked Approval row. The balance excludes it until
    approved.
  - **Transfers** are two linked rows sharing a `transfer_group_id`, never
    one row that touches two account balances.
  - **Voiding** (§48) requires a reason, never deletes the row, and is
    itself audited — the opposite of a silent edit.
- **Loans** (§18): `LoanService` derives outstanding balance and months-paid
  from the immutable `loan_payments` ledger every time (never a stored,
  mutable running total), clamps outstanding balance at zero on overpayment,
  and auto-closes a loan once it's fully paid. A loan's borrower is either a
  member or a branch (`organizational_unit_id`) — the legacy app only
  supported the latter.
- **Budgets** (§15/§24): `BudgetService::varianceReport()` compares planned
  vs. actual (approved, non-void expenses only) per category.
- Following the pattern from Phases 2–3: `EventRegistration`/`AttendanceRecord`-style
  scoping now applies to `LoanPayment` and `BudgetItem`, neither of which
  has its own `church_id` — both scope through their parent (`loan`/`budget`).

### Phase 5 — Subvention (§16–§17, reusing Phase 4's Approval engine)
- **The configurable rule engine** (`SubventionCalculationEngine`) is the
  direct generalization of the legacy `calculate-subvention.php` formula:
  `retention = Σ(rules tagged retention)`, `deduction = Σ(rules tagged
  deduction)`, `shortfall = deduction − retention`, `remittance =
  max(shortfall − loan_deduction, 0)`. Every number that used to be a PHP
  variable (`general_rate`, `minister_rate`, `admin_rate`, `admin_max`) is
  now a `SubventionRule` row (percentage / fixed / capped_percentage,
  looked up against a free-form `figures` JSON on the submission) —
  `RccgLegacyRuleSetSeeder` shows the legacy Group 1/2/3 rates expressed
  this way, with zero RCCG-specific code in the engine itself.
- **Two deliberate corrections vs. the legacy formula**, both covered by
  tests: remittance is clamped at zero (legacy could go negative), and the
  loan deduction applied is clamped at the shortfall itself rather than
  always subtracting the full monthly deduction (which is what caused the
  negative result in the first place).
- **Calculations are versioned, never overwritten** — recalculating a
  submission always inserts a new `SubventionCalculation` row; the previous
  one is untouched. This directly fixes the legacy bug where re-running the
  calculation page re-deducted the same loan payment a second time.
- **The §17 workflow (Draft → Submitted → Under Review → Returned →
  Approved → Rejected) is built ON TOP of Phase 4's shared `ApprovalWorkflow`**,
  not a reimplementation of it: `SubventionWorkflowService::submit()`
  calculates, then calls the same `ApprovalWorkflow::submit()` Finance uses.
  `returnForRevision()` is the one genuinely new piece of workflow — a soft,
  resubmittable outcome that closes the *current* Approval as rejected
  (a real, terminal decision on that record) while putting the submission
  itself back to `draft` so a fresh `submit()` later creates a brand-new
  Approval. Nothing about a closed Approval is ever reopened or edited.
- **The only real money movement in this entire module** happens in
  `SubventionWorkflowService::approve()`, and only once: a `LoanPayment` is
  created from `loan_deduction_applied` at the moment of approval — never
  during a calculation preview, which can be re-run freely before that
  point. `ApprovalWorkflow::approve()` already refuses to decide an
  already-decided approval, so double-approval (and therefore a duplicate
  loan payment) is structurally prevented one layer down, not by a special
  check here.

### Phase 6 — Pastoral Care (§21)
- **Prayer requests, pastoral cases (counseling/welfare/hospital visits/
  new-member follow-up), append-only case notes, and appointments** — the
  models are ordinary; the access control is not.
- **Strict, narrow permissions, exactly as §21 demands**: every pastoral
  policy in this phase deliberately does **not** fall back to
  `members.view`, `settings.manage`, or any other general-admin permission
  the way policies in earlier phases do. Visibility is `pastoral.manage`
  **or** being the specific assignee — nothing else grants access. An
  ordinary church administrator with full member/finance access still sees
  nothing here unless a case is assigned to them.
- **`visibleTo($user)` query scopes**, not just policy checks — so an index
  listing filters at the query level (a pastor without `pastoral.manage`
  never even receives other pastors' cases in a paginated list), while the
  policy is the second, per-record layer of defense-in-depth if a record
  is reached directly.
- **Case notes are append-only** — no update or delete route exists for
  `PastoralCaseNote` anywhere in the controllers; a correction is always a
  new note, applying the same principle Phase 4 established for financial
  transactions (§48) to pastoral records, because a pastoral history
  silently changing after the fact is its own kind of harm.
- A plain appointment (no linked pastoral case — a routine meeting) is
  visible to anyone who can see the calendar; an appointment tied to a
  pastoral case inherits that case's strict visibility.

### Phase 7 — Communication (§19-20, §21-29)
- **In-app notification center**: `NotificationService` + `app_notifications`,
  for staff (`User`) accounts only — read/unread, action links.
- **Email is free/included, and behaves that way in the code**: announcements
  (Phase 3) now actually send — `SendAnnouncementEmailsJob` blasts every
  targeted member with an email address, respecting the same audience
  targeting `Announcement::isVisibleToMember()` already enforced. No wallet,
  no per-recipient cost, no confirm-and-reserve step — it just runs as a
  queued job so a large church's send doesn't time out the request (§24).
- **SMS is a separately billed, wallet-based system, and is architecturally
  incapable of double-spending**: `SmsWalletService::debit()` locks the
  wallet row and checks the balance inside one transaction, so two
  campaigns confirmed at the same instant can't both spend the same
  credits (§26) — this is the same discipline Phase 4 applied to financial
  transactions, now applied to a second kind of ledger.
- **`SmsCampaignService::estimate()` vs. `confirmAndQueue()`**: estimating
  is free and read-only (§21/§22's "before you send" preview — recipients,
  segments per message, total units, balance after send); confirming is
  what actually reserves the exact units and snapshots the recipient list,
  in one transaction, so the number a church admin approved is exactly
  what gets charged — not a number that could drift if membership changed
  in between.
- **`SmsSegmentCalculator`** implements real GSM-7/UCS-2 segmentation math
  (§21) rather than a flat "1 unit per message" placeholder — tested at
  the exact 160/153/70/67-character boundaries where segment count jumps.
- **`SmsProviderInterface`** (§23) with a network-free `NullSmsProvider` as
  the default binding (used in this scaffold, dev, and tests) plus
  `TermiiProvider`/`TwilioProvider` stubs shaped for real integration —
  nothing outside the `Communication\Providers` namespace ever refers to a
  specific provider by name.
- **Per-recipient refunds on failure** (§27): `SmsSendingService` sends the
  whole batch regardless of individual failures, and the moment one
  recipient's send fails, that recipient's reserved units go back to the
  wallet with the failure reason on record — a partial failure never
  blocks the rest of the batch, and never silently keeps units for a
  message that was never delivered.
- **Domains stay decoupled via events, not direct calls**: Subvention
  (Phase 5) has no idea Communication exists. `SubventionWorkflowService`
  fires `SubventionSubmissionDecided`; `NotifySubmitterOfSubventionDecision`
  (registered in `AppServiceProvider`) is what turns that into an in-app
  notification and a queued email. The same pattern is exactly what the
  architecture doc's Events/Listeners section describes and is the model
  to extend for every future cross-domain notification.
- Buying SMS credits with real money is explicitly **not** built here —
  `SmsWalletController::credit()` is a manual/administrative top-up path
  only; the payment-gated purchase flow is Phase 8's job, and will call
  the exact same `SmsWalletService::credit(..., type: 'purchase', ...)`
  from a verified webhook.

### Phase 8 — SaaS billing (payment-gated signup, plans, SMS credit purchase)
- **Payment first, church second — enforced in code, not just in the UI.**
  `RegisterController` (rewritten; Phase 1's version created a church
  directly and said it was temporary) now creates a *User only*.
  `church_id` stays null. The one and only place a `Church` is created from
  a public signup is `ActivateChurchFromCheckout`, and it only runs from a
  verified payment. A registered-but-unpaid user hitting any tenant route is
  redirected to plan selection by `IdentifyTenant` (API callers get a 403).
- **One checkout model, two revenue streams**: `checkouts.purpose` is
  `subscription` or `sms_credits`, sharing one gateway abstraction and one
  webhook handler, while `invoices.type` keeps the two revenue streams
  distinguishable for reporting (§30-31).
- **Server-side verification and idempotency, layered three deep** (§6, §33):
  (1) every webhook delivery is logged uniquely on (provider,
  provider_event_id) *before* anything else happens; (2) the activation
  actions no-op on an already-`completed` checkout, under a row lock — this
  is what stops the browser "verify on return" path and the real webhook
  (which arrive with *different* event ids for the *same* payment) from both
  activating; (3) `invoices.provider_reference` is UNIQUE at the database
  level. Tests cover a duplicate delivery, two different events racing for
  one checkout, and SMS credits not being added twice.
- **The webhook endpoint fails closed**: no configured secret → 503 for
  every request; wrong secret → 401. It sits in `routes/api.php`
  (stateless, no CSRF) at `/api/v1/webhooks/payments`. Real gateways each
  need a thin adapter that verifies *their* signature scheme and
  translates into the normalized event shape — the Paystack/Flutterwave
  classes are stubs that throw rather than silently "succeed".
- **Failed payment creates nothing** and leaves the same checkout
  retryable (§7); an abandoned checkout simply stays `pending` (§8).
- **Plan limits are enforced on the models' `creating` hooks** (members,
  branches, admins), not in controllers — so the CSV import, the form, and
  any future code path are all covered by one choke point. Refusal message
  matches §14's example ("Current: 500 / 500 members … Upgrade your plan").
- **`subscriptions.status` is the source of truth; `churches.status` is a
  cache** written only by `SubscriptionService` (same pattern as
  `Transaction.approval_status`). `EnsureSubscriptionAllowsAccess` lets
  active/past_due/grace_period through and, for expired/cancelled/
  suspended, allows only `billing.*` routes — restriction only, nothing is
  ever deleted (§34-35; a test proves 3 members survive expiry).
- **Bug found and fixed while building this**: the "Church Owner" role a new
  church got was an *empty* role (Phase 1's seeder promised to clone the
  system default's permissions but nothing did). Activation now clones them,
  so the owner can actually open the billing page.
- **SMS pricing is config, not code** (`config/billing.php`,
  `SMS_PRICE_PER_UNIT`); unset means credit sales are refused (503) rather
  than sold at an invented price. `PlanSeeder`'s numbers are explicitly
  placeholders — the brief said not to decide pricing.

**Gaps that were closed in a follow-up pass** (originally listed as known
gaps — see git history / prior conversation for the "before" state):

- **Recurring billing now runs on a schedule.** `RenewalAndDunningService`
  + `php artisan subscriptions:process-billing-cycle` (scheduled daily in
  `routes/console.php`) attempts the renewal charge via
  `PaymentGatewayInterface::chargeRecurring()`, and drives the full
  active → past_due → grace_period → expired chain, plus finalizing a
  requested cancellation once the current period actually ends. Every
  transition still goes through `SubscriptionService`, so `churches.status`
  stays the single synced cache it always was.
- **Plan changes are now priced, not silent.** `ProrationCalculator` does
  day-based proration (credit for unused time on the old plan, charge for
  remaining time on the new plan); `BillingController::changePlan` records
  the net result as its own Invoice — a negative amount is a credit — before
  switching the plan, and `previewPlanChange` shows the breakdown before the
  person commits.
- **Invoices carry real tax fields now.** `TaxCalculator` applies one
  platform-configurable flat rate; the breakdown is computed once at
  *checkout* time and copied verbatim into the Invoice at activation time —
  deliberately never recalculated later, so a platform tax-rate change
  can't retroactively change what a past invoice says was charged.
- **The platform-admin area has a real billing screen.** Revenue split by
  type (subscription vs. SMS, §31), subscriptions grouped by status, a
  per-church drill-down, and suspend/reactivate actions — all plain Eloquent
  queries that work *because* `PlatformAdminOnly` sets `tenant.disabled`,
  which every tenant-scoped model (and every custom scope built through
  Phases 2–8) already checks. Nothing here calls `withoutGlobalScopes()`
  directly, which means a bug that left the middleware off would fail
  closed (empty results), not leak data.
- **SMS pricing and the tax rate are now runtime-editable** via
  `platform_settings` (a simple key/value store,
  `PlatformSettingsService`), with the old `.env` values as the fallback
  default for a setting nobody has touched yet — no deploy required to
  change a price.
- **A real bug was found and fixed in the process**: the "Church Owner"
  role a new church received was empty — Phase 1's seeder created a
  system-default role with every permission attached, and the intent
  (stated in that seeder's own comment) was for new churches to clone it,
  but nothing ever did. `ActivateChurchFromCheckout` now actually clones
  it, and a test (`...must actually hold the cloned owner permissions`)
  guards the regression.

**Still explicitly not built** (unchanged from before): a church-name setup
wizard (the church is still created with a placeholder name — Phase 9's
job), storage/feature-flag limits (stored on `plans` but not enforced
anywhere yet), invoice PDFs, and real Paystack/Flutterwave network code
(`PaystackGateway`/`FlutterwaveGateway` still throw — only their shape,
including the new `chargeRecurring` method, is real). One acknowledged
simplification: the dunning timer uses `updated_at` as a proxy for "time
in this status" rather than a dedicated timestamp column — noted in
`RenewalAndDunningService`'s own comment as worth revisiting if that ever
causes a real timing bug (e.g. an unrelated update resetting the clock).

### Phase 9 — Advanced UX: setup wizard, tours, feature flags, analytics (§11, §29-34, §55-57)
- **Setup checklist, not a blocking wizard** (§33-34): `SetupWizardService`'s
  step catalog lives in *code* (`steps()`), not a column — adding a new
  step later never needs a migration; existing churches just show it as
  "not yet done". Only the church-profile step is required; everything
  else is explicitly skippable (§11) and tracked separately
  (`setup_step_skipped` vs. `setup_step_completed`), and
  `onboarding_completed` fires exactly once, on whichever step happens to
  complete the checklist — covered by a test that re-marks an
  already-complete step and confirms it doesn't fire twice.
- **One tour engine, reused for onboarding AND feature discovery** (§29-31):
  `TourEngineService` has no special-casing between the first-login welcome
  tour and a "✨ New Feature" prompt — both are just a `Tour` row, matching
  the brief's own insistence that the engine "must not be hard-coded only
  for onboarding." `TourSeeder` seeds exactly the `welcome` tour from §29's
  worked example, plus a `billing-basics` tour matching §39's billing
  walkthrough, plus a `feature-budgeting` tour matching §30's example —
  three real tours, not placeholders.
- **Version-aware re-triggering, the one genuinely tricky piece of a tour
  engine** (§31): bumping `tours.version` makes a tour a user already
  completed eligible to show again — for everyone *except* whoever
  explicitly chose "don't show again" (`dont_show_again` survives a version
  bump; a plain skip doesn't). Four tests isolate exactly these cases.
- **Tour progress is personal** (`user_tour_progress` keyed on `user_id`,
  not tenant-scoped) — two staff members at the same church see and
  dismiss tours independently.
- **Feature flags with per-church overrides** (§56): `FeatureFlagService`
  resolves global → per-church override, in that order; an override can
  push a church either ahead of or behind the global rollout. An unknown
  flag key fails closed (returns `false`) rather than throwing — a typo in
  a `@if($flags->isEnabled(...))` check should quietly hide a feature, not
  500 the page.
- **Onboarding analytics stay narrow on purpose** (§55): `AnalyticsEvent`
  only ever stores an event name, church/user ids, and small structured
  properties (which tour, which step) — never free text. `track()` never
  throws, so a broken analytics call can't break the real action it's
  attached to. The one client-reportable path
  (`POST /analytics/track`) only accepts an explicit allow-list of event
  names (`feature_discovered`, `feature_used`) — not an arbitrary
  event/payload from the browser, which would turn it into an open logging
  sink.
- **Cross-domain wiring stays event-based**, continuing the pattern from
  Phase 7/8: Billing has no idea Onboarding exists. `ActivateChurchFromCheckout`
  fires `ChurchActivated` (already existed, unused until now);
  `TrackOnboardingStarted` (registered in `AppServiceProvider`) is what
  turns that into the first `onboarding_started` analytics event.
- **§34 empty states**: a reusable `<x-empty-state>` Blade component
  (message + one clear next action) was introduced and applied to the
  events list as a worked example; the other list views already had
  inline teaching text from earlier phases — worth sweeping to the shared
  component for consistency when the real design system (§35) lands.

### The tests that matter most in this phase
- `tests/Feature/TenantIsolationTest.php` — Church A cannot see, fetch-by-ID,
  or reach through an HTTP route into Church B's members; an unbound tenant
  context returns nothing rather than everything.
- `tests/Feature/FamilyDepartmentGroupTest.php` — same isolation guarantee
  extended to families/departments/groups/custom-field values, plus a test
  that documents *why* family-member attachment is checked in the
  controller (the pivot table has no `church_id` of its own to enforce it
  at the database level).
- `tests/Feature/MemberManagementTest.php` — membership-number generation,
  search, permission enforcement, and CSV import correctness (valid rows
  imported, invalid rows reported without aborting the batch, imported rows
  scoped to the importing church only).
- `tests/Feature/EventsAndAttendanceTest.php` — capacity math (including the
  "never reports full" unlimited case), cross-tenant isolation for
  events/registrations/sessions/records, and the attendance-rate calculation.
- `tests/Feature/AnnouncementsCalendarTasksTest.php` — announcement audience
  targeting (church-wide vs. department-only), the calendar service's
  date-ordered merge (and its exclusion of completed tasks), and tenant
  isolation for announcements/tasks.
- `tests/Feature/TransactionServiceTest.php` — the core financial invariant:
  income posts immediately, an expense doesn't touch the balance until its
  linked Approval is decided, a rejected expense never counts, transfers
  move the same amount out of one account and into another atomically, and
  voiding a transaction zeroes its effect on the balance while leaving the
  row intact.
- `tests/Feature/LoanAndBudgetServiceTest.php` — outstanding balance and
  months-paid derived correctly from the payment ledger, a loan auto-closing
  on full payment without ever reporting a negative balance, a closed loan
  rejecting further payments, and budget variance counting only approved,
  non-void expenses.
- `tests/Feature/SubventionCalculationEngineTest.php` — the generalized
  formula reproduces the legacy shape (including a "surplus branch owes
  nothing" case), a capped rule clamps correctly, a loan deduction never
  exceeds the shortfall, and recalculating a submission inserts a new row
  rather than mutating the previous calculation.
- `tests/Feature/SubventionWorkflowServiceTest.php` — submitting calculates
  and creates a pending Approval; approving with an active loan creates
  **exactly one** LoanPayment (and a second approval attempt is rejected
  outright, never creating a duplicate); returning a submission closes its
  Approval as rejected but resubmission creates a genuinely new one; and
  the usual cross-tenant isolation sweep for rule sets, submissions, and
  calculations.
- `tests/Feature/PastoralCareTest.php` — the test that matters most here is
  the negative case: an ordinary administrator with no pastoral permission
  cannot view a pastoral case or prayer request they're not assigned to,
  full stop, regardless of what other admin permissions they hold. Also
  covers a pastor seeing only their own assigned caseload without
  `pastoral.manage`, notes accumulating on a case without any edit path
  existing, case-closure logging a final note, and appointment visibility
  correctly forking on whether a pastoral case is attached.
- `tests/Unit/SmsSegmentCalculatorTest.php` — segment counts at the exact
  GSM-7 (160/153) and UCS-2 (70/67) character boundaries.
- `tests/Feature/SmsWalletServiceTest.php` — crediting/debiting correctness,
  a rejected debit changing nothing at all (no partial application, no
  stray ledger row), and refunds recording their reason.
- `tests/Feature/SmsCampaignTest.php` — estimates only count members with a
  phone number and respect branch/department/group targeting; confirming
  reserves the *exact* estimated units and snapshots recipients; insufficient
  credits refuses confirmation and reserves nothing; sending marks
  successes `sent` with the campaign `completed`; and — the one most worth
  reading — a failed recipient is refunded while the rest of the batch
  still sends, landing the campaign as `partially_failed` with the wallet
  balance exactly reconciled (reserved − sent + refunded).
- `tests/Feature/CommunicationTest.php` — the notification center's
  send/read/unread-count cycle, tenant isolation for notifications, and the
  Subvention→Communication event/listener wire-up actually notifying the
  right user in-app and by email (plus not crashing when a submission has
  no submitter to notify).

- `tests/Feature/PaymentGatedSignupTest.php` — registering never creates a
  church; an unpaid user is bounced to plan selection; a verified payment
  creates the church, owner (with real permissions), subscription, invoice
  and HQ unit exactly once; duplicate and racing webhooks activate exactly
  once; a failed payment creates nothing and stays retryable; nobody can
  verify someone else's checkout; SMS credits are added once even if the
  webhook repeats; subscription vs. SMS invoices stay distinguishable.
- `tests/Feature/BillingEnforcementTest.php` — webhook endpoint fails closed
  / rejects a bad secret / treats a duplicate as a 200; member, CSV-import,
  admin and branch limits; status changes keep `churches.status` in sync;
  expiry never deletes data; an expired church is locked out of the app but
  can still reach billing; billing pages need `billing.manage`; SMS credit
  sales are refused without configured pricing; cross-tenant isolation for
  subscriptions and invoices.
- `tests/Unit/ProrationAndTaxTest.php` — an upgrade mid-cycle charges the
  price difference for the days remaining; a downgrade nets to a credit
  (negative), not a charge; zero and non-zero platform tax rates compute
  correctly.
- `tests/Feature/RenewalAndDunningTest.php` — a successful renewal charge
  extends the period and creates exactly one invoice; a failed charge marks
  `past_due` and creates *no* invoice; a subscription not yet due is left
  alone; the past_due → grace_period → expired timing thresholds; a
  cancellation-requested subscription is cancelled once its period ends
  (and never also attempts a renewal charge) but is left alone before then.
- `tests/Feature/PlatformAdminBillingTest.php` — a normal church user is
  forbidden from the platform-admin billing screen; a platform admin sees
  revenue and churches across every tenant in one view; suspend/reactivate
  actually change status; platform settings actually change what
  `PlatformSettingsService` returns.

- `tests/Feature/SetupWizardTest.php` — percent-complete math,
  `onboarding_completed` firing exactly once (and not again on a
  re-completion), rejecting an unknown step key, skip vs. complete staying
  distinct, and tenant isolation for setup progress.
- `tests/Feature/TourEngineTest.php` — the full state machine: a new tour
  shows, stepping through `next()` finishes on the last step, a completed
  tour doesn't show again, a version bump makes it eligible again, "don't
  show again" survives that bump while a plain skip doesn't, an inactive
  tour never shows, restarting resets to step zero, and progress never
  leaks between two users.
- `tests/Unit/FeatureFlagServiceTest.php` — unknown key fails closed; the
  global value applies with no override; a per-church override wins in
  *both* directions (opted in early while global is off, opted out while
  global is on); clearing an override falls back to the global value.
- `tests/Feature/AnalyticsTest.php` — an event records the right church/user
  context; `ChurchActivated` actually produces an `onboarding_started`
  event; the client-tracking endpoint accepts an allow-listed event name
  and rejects anything else with a 422.

## Phase 10 — Production hardening (§23, §43-45, §49-50)

Phase 10 arrived as a drop-in package and is now integrated. Setup is
documented in `DEPLOYMENT.md` and `SECURITY.md`; the code is:\n
- **Secure file uploads.** `FileUploadService` sniffs MIME type from file
  *content* (never the client's `Content-Type` or the filename extension —
  the exact thing §43 warns against trusting), stores under a random on-disk
  UUID (never the client's filename), on a `private` disk outside the public
  webroot, behind a signed, time-limited URL. `FileDownloadController`
  re-checks tenant ownership on every request even though the signature
  already proves the link is legitimate; in practice isolation is enforced a
  layer earlier still, because `UploadedFile` is tenant-scoped like
  everything else — a cross-tenant request 404s at route-model-binding time
  before the controller's own check runs. First real use: member photo
  upload (`MemberPhotoController`), replacing Phase 2's `photo_path` column
  (which was schema only, never wired to anything) with a proper
  `photo_upload_id` FK and a `Member::photoUrl()` that mints a fresh signed
  URL on every call, since a signed URL expires and must never be the thing
  stored permanently.
- **`SecurityHeaders` middleware**: `X-Frame-Options: DENY`,
  `X-Content-Type-Options: nosniff`, a same-origin `Content-Security-Policy`,
  HSTS on HTTPS requests, and a `Permissions-Policy` denying
  camera/mic/geolocation outright.
- **A health check that actually checks something**: `GET /health` probes the
  database, cache and queue backends independently (Laravel's default `/up`
  only proves the PHP process is alive) and returns 503 if any dependency is
  down, with per-dependency latency/error detail. Verified live — see
  "Verification" below.
- **Docker + CI**: one `Dockerfile` reused by three services (web process,
  queue worker, scheduler) running different commands against the same image,
  so they cannot drift out of sync with each other's code.
  `.github/workflows/ci.yml` runs the full suite against real MySQL on every
  push/PR, and treats `TenantIsolationTest.php` as load-bearing enough to
  fail the build separately and loudly if that file is ever missing or empty.
- **`backup:database`** (scheduled daily) that is explicit in its own
  docblock about not being a real production backup strategy — making the
  limitation loud rather than letting a `routes/console.php` entry imply more
  safety than it provides.
- **`SECURITY.md`** / **`DEPLOYMENT.md`** — the security story phase by
  phase, the processes a production deployment actually needs (the queue
  worker is not optional), and an honest list of what still needs real
  traffic data.

### The landing page (§27-39)

`GET /` is a real route (`LandingController` →
`resources/views/landing/index.blade.php`). Pricing is genuinely dynamic —
the page queries `Plan::where('is_active', true)`, the same source the
checkout flow reads, so an admin price change appears with no template edit.
Every CTA routes into the real flow: "Get Started" → `register`,
"Sign in" → `login`, and each plan button is aware of a signed-in,
not-yet-activated user, sending them straight to `checkout.review` for that
exact plan instead of back through registration. The interactive
feature-explorer is deliberately **static sample data** — it is marketing
content a logged-out visitor sees, and wiring it to real church records
would mean exposing database content with no authentication in front of it.

---

## Phase 11 — 14-day trial + subscribe / upgrade / downgrade

**The decision, stated once:** every new church gets **14 days free**, and
subscribes after that. Signup takes no money.

### Why a trial is a real subscription row, not a special case

It would have been easier to give a trial church no subscription at all and
teach every other class to special-case "trialing". That is exactly the kind
of branch that rots: plan limits (`PlanLimitService`), the access gate
(`EnsureSubscriptionAllowsAccess`), the billing screen and the renewal cycle
would each need their own `if (trialing)`, and each could disagree with the
others.

Instead a trial **is** a subscription whose `status` is `trialing`.
`subscriptions.status` remains the single source of truth,
`churches.status` remains the one denormalized cache written only by
`SubscriptionService` (now via a public `syncStatus()` so new lifecycle code
can live outside that class without duplicating the write), and every
existing query keeps working unchanged. The only genuinely new state is
`trial_ends_at`, and the only new rule is what happens when it passes.

### What changes

- **`TrialService`** (`startTrial`, `subscribeNow`, `convert`,
  `expireExhaustedTrials`, `daysRemaining`) is the whole trial story, and
  `TRIAL_DAYS = 14` is defined in exactly one place.
- **`ActivateChurchFromCheckout` starts a trial, not a paid subscription.**
  It previously called `createInitial()` and wrote a `paid` invoice dated
the moment of signup. It now calls `startTrial()` and writes **no invoice at
  all** — an invoice for a payment that never happened is a false financial
  record, which is precisely what §48 exists to prevent. `completed` on a
  checkout now means *"the trial started"*, not *"the first month was paid
  for"*.
- **`Subscribe now`** charges immediately through the same gateway
  abstraction the renewal cycle uses, and starts a full paid period from
  today. Making someone wait out their remaining trial days before they are
  allowed to pay is worse than taking their money when they offer it — the
  leftover days are simply kept, not burned.
- **A trial that ends without payment becomes `past_due`, not `expired`.**
  This is the deliberate one. Expiring the moment the trial ends gives a
  church that simply forgot to pay a hard cut-off with no warning, while a
  church whose card was declined gets a full grace period — two different
  outcomes for the same "we owe money" state, which is unexplainable to a
  customer. So the trial ends into `past_due` and the existing dunning
  timings take it from there for everyone. `renewal cycle` gained a
  `trials_lapsed` counter and runs this pass first, so a trial that lapsed
  today is caught by the same run.
- **`EnsureSubscriptionAllowsAccess` treats `trial` as full access.** A
  trialing church is a working tenant, not a demo — gating it would defeat
  the point of a trial.

### Upgrade / downgrade, and the one place the trial is different

A trialing church that chooses a different plan gets `pending_plan_id`
recorded, and **the plan it is trialing on does not move**. Switching the
plan mid-trial would move every limit (members, branches, admins) instantly,
letting someone trial on the top plan and drop to the cheapest on day 13 —
and because plan limits read from `plan_id`, a "downgrade" could instantly
lock a church out of its own data. Intent is recorded; the switch happens at
conversion, which is what the person is actually promising to pay for. The
billing page states this plainly rather than leaving it to be discovered.

For a **paid** subscription, upgrade and downgrade are the same screen with
a different sign on the price difference. Both go through
`previewPlanChange()` first, which shows the prorated credit and charge and
the net result before anything is committed. A negative net is recorded as a
credit line, never an immediate refund.

### Where it lives in the UI

- **`/dashboard`** leads with a subscription panel: during a trial it states
  the deadline in *days* ("3 days left", not a date) with a `Subscribe now`
  button; once subscribed it becomes a quiet entry point to
  upgrade/downgrade. It is shown only to a user who can act on it — a
  countdown to something you cannot fix is just noise.
- **`/billing`** is the hub: trial banner with the subscribe CTA, current
  plan and status, cancel/reactivate, the SMS wallet, and recent invoices.
- **`/billing/plans`** is the single upgrade/downgrade table — current plan
  marked, "Selected" for a pending choice, and `Upgrade`/`Downgrade`/
  `Switch` labels derived from the price difference. One screen rather than
  one per direction, because they are the same action and separate screens
  are how they drift apart.
- **`/billing/change-plan/preview`** shows the proration maths for a paid
  change, and for a trial says *"no charge today"* instead — showing a
  £0.00 breakdown during a trial would read as "this change is free", which
  is a different and misleading statement.
- The nav gained an **Account → Billing & plan** entry.

### The tests that matter most in this phase

- `tests/Feature/TrialAndPlanChangeTest.php` — a new church starts `trialing`
  with **no invoice**; a trialing church has full access; a lapsed trial
  becomes `past_due` and *not* `expired`, then flows through the ordinary
  dunning chain; subscribing charges once and writes exactly one invoice;
  converting an already-active subscription is refused; choosing a plan
  during a trial records intent **without** switching the plan and without
  writing an invoice; the pending plan is applied at conversion and consumed;
  a *paid* plan change still prorates and records its own invoice; the plan
  page renders; a user without `billing.manage` is forbidden from the plan
  pages; the daily cycle reports lapsed trials; days-remaining never goes
  negative.
- `tests/Feature/SignupToTrialJourneyTest.php` — the whole journey over real
  HTTP in order: register → plan selection → checkout → verify → trialing
  tenant → dashboard shows the trial → plans page → change plan (intent only)
  → subscribe → active paid tenant with one invoice → paid downgrade
  previews. Each rule is proven in isolation by the test above; this proves
  the order they happen in, which is where integration bugs actually live.
- `tests/Feature/PaymentGatedSignupTest.php` — updated: the activation test
  now asserts `trial`/`trialing` and **zero invoices**, and the revenue-split
  test converts the trial first so it still compares two real revenue
  streams rather than accidentally asserting that a trial produced one.

---

## The integration itself — what the Phase 1–10 drop-in got wrong

Worth recording, because it was not a clean overlay. The Phase 1–10 package
is the same source tree this project already came from, so overlaying it
**regressed** files that had since been fixed here. Those were restored from
the pre-integration checkpoint rather than accepted. Specifically:

1. **`resources/views/components/layout.blade.php`** — the drop-in replaced
   the real design-system layout (nav groups, permission-filtered links,
   `@stack('scripts')`) with a 9-line stub titled "Church SaaS". Restored;
   the Phase 11 nav entry was then added to *that*.
2. **`routes/web.php`** — differed by a mangled docblock and a duplicated
   `use` statement; the checkout routes had also lost their explanatory
   comment. Restored, then extended with the Phase 11 routes.
3. **Models and services** — the drop-in carried **older, buggier copies**.
   E.g. `Plan::priceFor()` lost its `(string)` cast and `strtoupper`
   handling, `NullPaymentGateway`, `ProrationCalculator`, `TaxCalculator`,
   `BudgetService`, `SubventionSubmission` and the `SubventionSubmission`
   controller all reverted to pre-fix versions. All restored, then
   `Subscription` and `SubscriptionService` were extended for Phase 11.
4. **`app/Http/Middleware/SecurityHeaders.php`** and
   **`RolePermissionSeeder`** — the drop-in's older copies; restored.
5. **`RegisterController.php`** — the drop-in's only change was deleting a
   genuine 20-line docblock explaining the payment-gated flow. No code
   change; restored.
6. **The landing page route had two owners.** The drop-in registers `/` in
   `routes/web.php` as `landing`, but `routes/marketing.php` *also* owned `/`
   as `home` — and because marketing.php is required last, it silently won.
   The `marketing.php` home page now lives at `/home` (keeping the `home`
   route name so existing links still resolve) and `/` belongs to the landing
   page. This was caught by `ExampleTest`, which is a good argument for
   keeping a bare "`GET /` returns 200" test even when a richer test exists —
   the richer test was passing, because it exercised the *other* route.
7. **`ExampleTest.php`** had to gain `RefreshDatabase`: the landing page
   queries `plans`, so the test was previously passing against whatever dev
database happened to be lying around (or failing with "no such table:
   plans" on a clean one).

### Known gaps, unchanged

Church-name setup collects completion but not the name itself;
storage/feature-flag *plan* limits sit unused; no invoice PDFs; real
Paystack/Flutterwave network code still throws (only its shape is real);
no 2FA; no dependency scanning in CI. The dunning timer still uses
`updated_at` as a proxy for "time in this status".

---

## Verification (what was actually run)

- `php artisan migrate` — clean, including the Phase 11
  `add_trial_and_pending_plan_to_churches_table` migration.
- `php artisan db:seed` — `RolePermissionSeeder`, `PlanSeeder`, `TourSeeder`,
  `FeatureFlagSeeder` all applied.
- `php artisan test` — **224 tests, 648 assertions, all passing.**
- Live HTTP smoke check against a running server: `/` (200, landing page),
  `/register` (200), `/plans` (200), `/health` (200, reporting per-dependency
  status).


---

## Phase 12 — stated pricing, and the styled form system

Two requests, one of which turned out to be a correctness bug rather than a
cosmetic gap.

### 1. Starter, Growth, Denomination and Enterprise now state their price

**What was actually wrong.** There were two independent plan lists:

- the `plans` **table** — what the app charges. Checkout, plan limits, invoices
  and the billing screens all read from it. It had real numbers.
- `config('marketing.plans')` — what the pricing pages displayed. Every tier
  had `'price' => null` and rendered **"Price on request"**, deliberately, from
  an earlier phase that was told not to invent pricing.

So the marketing page said "Price on request" while the checkout charged
₦25,000. Each list was individually defensible; the combination is the single
worst bug a pricing page can have — the visitor is quoted one thing and billed
another. There was also a naming mismatch: the marketing tiers were Starter /
Growth / **Denomination** / Enterprise, but the database had Starter / Growth /
**Professional** — so "Denomination" was advertised and could not be bought.

**The fix.** One place a price is written down — `config('billing.plans')`:

- `PlanSeeder` seeds the `plans` table from it (so the seeded rows and the
  config cannot drift).
- `PlanCatalog` resolves the display list. **The database row wins over the
  config** whenever one exists, because that is what checkout actually charges —
  a price a platform admin edits at runtime must be the price the page shows.
  The config only fills gaps (a fresh install, or a tier with no purchasable
  row).
- `Professional` was renamed to `Denomination` so the advertised tier is the
  purchasable tier.
- **Enterprise is advertised, not sold.** It has no listed price, so
  `monthly_price` is now NULLABLE — storing `0.00` to satisfy the old NOT NULL
  constraint would have been far worse, because 0 is a real number that reads
  as "free tier" and would flow into proration and comparisons as one. Null
  means "quoted individually": `Plan::displayPrice()` renders **Custom**,
  `Plan::isSelfServe()` is false, it gets a "Talk to us" CTA instead of a
  checkout button, and `is_active = false` keeps it out of every plan picker.

Prices are stated on all four: **Starter ₦10,000/mo · Growth ₦25,000/mo ·
Denomination ₦60,000/mo · Enterprise Custom**, with the annual equivalent
(₦100,000 / ₦250,000 / ₦600,000 — two months free) shown beneath the monthly
figure. Present on the landing page (`/`), the marketing pricing page
(`/pricing`), the post-registration plan picker (`/plans`), the billing hub and
the plan-change table — all rendering through the same `Plan::displayPrice()`,
so six pages cannot format the same number six ways.

`Plan::priceFor()` (used by checkout and renewal, and relied on by existing
tests) is unchanged.

### 2. The styled form system

A design system already existed (`cf-field`, `cf-label`, `cf-input`,
`cf-select`, `cf-textarea`, `cf-alert`, `cf-btn`, `cf-table`, `cf-card`) and the
dashboard already used it — but **every application view was still a stub**.
Members was a bare `<form>` with one search box and a link to `#` for "Add
Member"; Events, Announcements, Tasks, Departments, Families and Groups were
`<ul>` lists; the platform-admin dashboard was two lines:
`<x-layout><h1>Platform Admin</h1></x-layout>`.

Built five form components and rebuilt the pages on them:

| component | what it handles |
|---|---|
| `x-form.input` | label, hint, error, required marker, `aria-invalid`/`aria-describedby` |
| `x-form.select` | accepts a plain map **or** `{value,label}` pairs (a plain map silently casts numeric-string keys to ints) |
| `x-form.textarea` | wide by default; a long field in a 2-column grid is a bad field |
| `x-form.checkbox` | visually styled box with the input hidden but still focusable and in the a11y tree |
| `x-form.card` | the form shell: title, description, a validation summary, body, actions |

Rebuilt on them, with real create forms wired to what the controllers actually
validate: **members, events, announcements, tasks, departments, families,
groups, financial accounts, attendance sessions, SMS campaigns, prayer
requests, pastoral cases, subvention submissions**, plus the billing hub and
plan table from Phase 11.

The **platform-admin dashboard** got a real controller and screen: churches and
users, paying vs trialing (with a conversion figure), **subscription and SMS
revenue reported separately** (§31 — one combined total is what would hide a
collapse in either), recent signups, a "needs attention" support queue, and a
subscriptions-by-status breakdown. Settings and feature-flags were rebuilt with
the same components.

Every form follows the same three rules: **errors appear beside their field**
(not only in a summary at the top), **input survives a failed submit**, and
**the create panel opens itself when validation fails** — otherwise the errors
are hidden behind a collapsed toggle, which is worse than not collapsing at all.

### 3. The bug only a browser found

Everything above passed its tests, and the smoke sweep — which walks every
authenticated GET route asserting 200 — was green. Then the pages were loaded
in a real browser, and the console was full of:

```
Refused to execute inline script because it violates the following
Content-Security-Policy directive: "script-src 'self' 'unsafe-eval'"
```

**Phase 10's CSP had no inline-script allowance.** Every inline `<script>` in
the app was blocked: the landing page's tab explorer, the dashboard's Chart.js
render, and every one of the new form toggles. Nothing 500'd. Every page
returned 200 with all its markup intact — which is exactly why no PHP test and
no smoke sweep could see it. The tests were checking *presence*; the browser
was checking *execution*.

The fix is a **per-response nonce**, not `'unsafe-inline'`:

- `SecurityHeaders` generates a nonce per request and exposes it via
  `SecurityHeaders::nonce()`, stamped onto every inline `<script>` tag.
- `script-src` carries `'nonce-...'`, so the app's own scripts run and nothing
  else does. `'unsafe-inline'` would also re-enable scripts injected by an
  attacker, which is most of what CSP exists to stop — it would fix the symptom
  by removing the protection.
- `'unsafe-inline'` is emitted **only** in `local`/`testing` as a fallback for
  anything that injects a script without the nonce; production gets the nonce
  alone.
- `https://cdn.jsdelivr.net` was added to `script-src` for Chart.js.

### The tests that matter most in this phase

- `tests/Feature/SecurityHeadersCspTest.php` — the policy carries a nonce, the
  nonce is **unique per response** (a reused nonce is as good as none), rendered
  pages tag their inline scripts with the *response's* nonce, the Chart.js CDN
  is permitted, `'unsafe-inline'` appears only in the dev environment, and the
  other security headers survive.
- `tests/Feature/LandingPageTest.php` — rewritten for the new contract: all four
  tiers present **with prices stated**, no "Price on request" anywhere, a price
  edited in the database **wins over the config default**, annual prices and the
  toggle, Enterprise advertised but not purchasable, and enterprise routed to
  Contact rather than a payment page.
- `tests/Feature/MemberFormTest.php` — the create form renders every field the
  `StoreMemberRequest` validates (a missing one makes the form quietly
  un-submittable), labels are wired to inputs, an empty list shows a teaching
  empty state, a failed submit re-displays with the error, and a user without
  `members.create` never receives the form markup.
- `tests/Feature/PlatformAdminDashboardTest.php` — a church user is forbidden;
  totals span multiple tenants; the two revenue streams appear as **separate**
  figures; a past-due church lands in the support queue; the dashboard renders
  cleanly with **no data at all** (the conversion percentage divides by counts
  that are all zero on a fresh install); settings save and validate.

### Verified in a browser, not just in tests

Run against a live server with a seeded demo church, on 16 authenticated pages:

- **0 console errors, 0 failed requests** (the CSP violations are gone).
- `.cf-input` / `.cf-select` / `.cf-textarea` / `.cf-label` present on every
  rebuilt page, with **0 bare unstyled inputs** inside any form.
- Clicking a real toggle: `hidden: true, inputsVisible: 0` →
  `hidden: false, inputsVisible: 7`. The landing page's tab explorer panel
  populates. This is the assertion the PHP tests structurally cannot make — a
  CSP violation leaves the `<script>` tag in the DOM and the handler unbound, so
  asserting on markup alone would have passed while the page was broken.

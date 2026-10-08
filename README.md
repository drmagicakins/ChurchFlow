# Church SaaS — Phase 1–9 (Foundation → … → SaaS Billing → Onboarding, Tours & Feature Flags)

This is not a full Laravel install (this sandbox has no access to Packagist, so
`composer install` can't run here). It's the **source for Phase 1** — drop it
into a fresh Laravel app on your own machine and it will run as-is.

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

## What's deliberately NOT built yet

The interactive landing page, the wider platform-admin area (system
health, support tickets), and Phase 10 (security hardening, performance
work, backups, monitoring, CI/CD) — plus the smaller Phase 8 gaps noted
above (church-name setup step still only tracks completion, it doesn't
yet collect the name itself; storage/feature-flag *plan* limits sit
unused; invoice PDFs; real Paystack/Flutterwave network code).

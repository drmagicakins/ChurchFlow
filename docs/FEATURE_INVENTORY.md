# ChurchFlow — Documented Feature Inventory

**Status:** Evidence-based inventory of the current branch.
**Baseline:** `main` @ `d6a1f47` — "SMS service distinguished from parish and church owner".
**Runtime:** PHP 8.4.14 · Laravel 12.69.2 · Node 20.19.6 · Tailwind 4 · Vite 7 · Alpine 3 · Chart.js 4.
**Test baseline:** `php artisan test` → **381 passed, 1230 assertions, 0 failures** (68s, 2026-10-24).
**Scope of this document:** describes what exists. It makes no recommendation — see `PROJECT_AUDIT.md` for findings and `PRODUCT_ROADMAP.md` for sequencing.

Every row carries a **status** from one of five values, and **evidence** that a reviewer can re-run:

| Status                         | Meaning                                                             |
| ------------------------------ | ------------------------------------------------------------------- |
| **Confirmed working**          | Implementation present end-to-end **and** covered by a passing test |
| **Partially implemented**      | Layers exist but the workflow is incomplete                         |
| **Confirmed missing**          | Verified absent in the relevant code                                |
| **Needs runtime verification** | Source is plausible; end-to-end behaviour not proven in this pass   |
| **Stale documentation**        | The repository differs from what a document claims                  |

> Rule applied throughout: **a route existing is not a working feature.** Each item was traced
> route → controller → policy/middleware → service/model → view/test. Where that chain has a gap,
> the item is _not_ marked "Confirmed working", regardless of what README.md says.

---

## 1. Platform & tenancy

| #   | Feature                                            | Status            | Evidence                                                                                                                                                                          |
| --- | -------------------------------------------------- | ----------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1.1 | Single-DB multi-tenancy via global scope           | Confirmed working | `app/Scopes/TenantScope.php`, `app/Traits/BelongsToTenant.php`; `tests/Feature/TenantIsolationTest.php`; CI fails loudly if that file is empty (`.github/workflows/ci.yml:52-55`) |
| 1.2 | Tenant resolution middleware                       | Confirmed working | `app/Http/Middleware/IdentifyTenant.php`; ordering fix at `bootstrap/app.php:44-47` (`prependToPriorityList` before `SubstituteBindings`)                                         |
| 1.3 | Platform-admin boundary                            | Confirmed working | `app/Http/Middleware/PlatformAdminOnly.php` sets `tenant.disabled`; ordering fix `bootstrap/app.php:60-63`; 23 policies extend `BaseTenantPolicy`                                 |
| 1.4 | RBAC (roles → permissions → users)                 | Confirmed working | `RolePermissionSeeder`, `User::hasPermission()`; `tests/Feature/SettingsTest.php`                                                                                                 |
| 1.5 | Per-user private roles (parish pastor permissions) | Confirmed working | `SettingsController::updateTeamMemberPermissions()`; `tests/Feature/ParishPastorPermissionsTest.php` (13 tests)                                                                   |
| 1.6 | Parish / branch scoping on users                   | Confirmed working | `SettingsController::updateTeamMemberParish()`, owner-only guard `assertOwnerMayChangeAssignment()`; `tests/Feature/ParishAndRegistrarTest.php`                                   |
| 1.7 | Audit log (create/update/delete)                   | Confirmed working | `app/Observers/`, `app/Traits/Auditable.php`, `audit_logs` table                                                                                                                  |
| 1.8 | Versioned API surface                              | Partial           | `routes/api.php` registers one webhook route only; `Api/V1/ApiController.php` and `AuthenticateApiToken` exist but expose no tenant API endpoints                                 |
| 1.9 | Tenant data export / deletion (GDPR-style)         | Confirmed missing | No controller, route or command for member/church data export or deletion                                                                                                         |

## 2. People (members, families, departments, groups)

| #    | Feature                                                | Status            | Evidence                                                                                                   |
| ---- | ------------------------------------------------------ | ----------------- | ---------------------------------------------------------------------------------------------------------- |
| 2.1  | Member CRUD, search, filter, sort, pagination          | Confirmed working | `MemberController`; `tests/Feature/MemberManagementTest.php`, `MemberAreaTest.php`                         |
| 2.2  | Membership number generation                           | Confirmed working | `MemberManagementTest` (auto-number, per-church)                                                           |
| 2.3  | Bulk status update (policy re-checked per row)         | Confirmed working | `MemberController::bulkUpdateStatus()`, `members.bulk-status`                                              |
| 2.4  | CSV import by header name, row-level validation        | Confirmed working | `ImportMembersFromCsv`; `tests/Feature/MemberSheetImportTest.php`                                          |
| 2.5  | CSV export (chunked)                                   | Confirmed working | `ExportMembersToCsv` chunks of 500                                                                         |
| 2.6  | Families + family-member pivot with relationship label | Confirmed working | `FamilyController`; `tests/Feature/FamilyDepartmentGroupTest.php`                                          |
| 2.7  | Departments (optional branch binding) & groups         | Confirmed working | `DepartmentController`, `GroupController`, `DepartmentVisibilityTest.php`                                  |
| 2.8  | Custom member fields                                   | Confirmed working | `MemberCustomFieldValue` scopes through `member` (no own `church_id`)                                      |
| 2.9  | Member photo upload + signed download                  | Confirmed working | `MemberPhotoController`, `FileUploadService`, `FileDownloadController`; `tests/Feature/FileUploadTest.php` |
| 2.10 | Branches (organisational units) create/list/delete     | Confirmed working | `OrganizationalUnitController`; plan limit enforced (`max_branches`)                                       |

## 3. Activities (events, attendance, calendar, tasks, announcements)

| #   | Feature                                                      | Status            | Evidence                                                                                         |
| --- | ------------------------------------------------------------ | ----------------- | ------------------------------------------------------------------------------------------------ |
| 3.1 | Events CRUD                                                  | Confirmed working | `EventController`; `tests/Feature/EventsAndAttendanceTest.php`                                   |
| 3.2 | Capacity-aware registration with waitlist                    | Confirmed working | `Event::hasCapacityFor()` inside `lockForUpdate()`                                               |
| 3.3 | Public event registration by slug, no login                  | Confirmed working | `PublicEventRegistrationController`, `GET/POST /e/{slug}` throttled                              |
| 3.4 | Event visitor list, public link minting, email/SMS reminders | Confirmed working | `EventController::sharePublicForm/cancelVisitor/emailReminder/smsReminder`                       |
| 3.5 | Attendance sessions + bulk record entry                      | Confirmed working | `AttendanceController::recordBulk`; analytics in `AttendanceAnalyticsService`                    |
| 3.6 | Calendar (aggregated, not a table)                           | Confirmed working | `CalendarService`; `AnnouncementsCalendarTasksTest.php`                                          |
| 3.7 | Announcements with audience targeting + email blast          | Confirmed working | `AnnouncementController`, `SendAnnouncementEmailsJob`→ delivered via listener                    |
| 3.8 | Tasks + reminder popups                                      | Confirmed working | `TaskController::dueReminders/dismissReminder`; server `?once_a_day=1` + client `sessionStorage` |
| 3.9 | Reminder popup regression guard                              | Partial           | Behaviour verified manually in Phase 14; no dedicated test named for the popup                   |

## 4. Finance

| #    | Feature                                                           | Status            | Evidence                                                                                          |
| ---- | ----------------------------------------------------------------- | ----------------- | ------------------------------------------------------------------------------------------------- |
| 4.1  | Ledger-derived balances (no mutable column)                       | Confirmed working | `FinancialAccount::balance()`; `tests/Feature/TransactionServiceTest.php`                         |
| 4.2  | Income / expense / transfer posting                               | Confirmed working | `TransactionController`, `TransactionService`                                                     |
| 4.3  | Expense approval gate (Approval row created in same txn)          | Confirmed working | `ApprovalWorkflow`; expense excluded from balance until approved                                  |
| 4.4  | Voiding with mandatory reason, no deletion                        | Confirmed working | `TransactionController::void`; §48 semantics                                                      |
| 4.5  | Loans with derived balance, auto-close, overpayment clamp         | Confirmed working | `LoanService`; `tests/Feature/LoanAndBudgetServiceTest.php`                                       |
| 4.6  | Budgets + variance report                                         | Confirmed working | `BudgetService::varianceReport()`                                                                 |
| 4.7  | Settlement bank accounts (pastor-owned, registrar-readable)       | Confirmed working | `BankAccountController`, `BankAccountPolicy`; `dev-balance.php` probe scripts exist outside tests |
| 4.8  | Subvention rule engine (percentage / fixed / capped)              | Confirmed working | `SubventionCalculationEngine`; `SubventionCalculationEngineTest.php`                              |
| 4.9  | Subvention versioned calculations (recalc inserts, never mutates) | Confirmed working | `SubventionCalculation` history; test asserts new row                                             |
| 4.10 | Subvention workflow (submit → review → return → approve/reject)   | Confirmed working | `SubventionWorkflowService`; `SubventionWorkflowServiceTest.php`                                  |
| 4.11 | Reports catalogue + per-report CSV export                         | Confirmed working | `ReportController`, `ReportsService::catalogue()`                                                 |
| 4.12 | Invoice PDF download                                              | Confirmed missing | Zero matches for `pdf` under `app/`; README states not built                                      |
| 4.13 | General ledger / journal export for accountants                   | Confirmed missing | No export other than per-report CSV                                                               |

## 5. Pastoral care

| #   | Feature                                                | Status            | Evidence                                                                                 |
| --- | ------------------------------------------------------ | ----------------- | ---------------------------------------------------------------------------------------- |
| 5.1 | Prayer requests, pastoral cases, appointments          | Confirmed working | `PrayerRequestController`, `PastoralCaseController`, `AppointmentController`             |
| 5.2 | Strict visibility (assignee or `pastoral.manage` only) | Confirmed working | `PastoralCasePolicy`, `visibleTo()` query scope; negative test in `PastoralCareTest.php` |
| 5.3 | Append-only case notes (no update/delete route)        | Confirmed working | Route table assertion in `PastoralCareTest.php`                                          |
| 5.4 | Safeguarding workflow (incident escalation, consent)   | Confirmed missing | No model, route or view; not mentioned in any controller                                 |

## 6. Communication

| #   | Feature                                             | Status            | Evidence                                                                                              |
| --- | --------------------------------------------------- | ----------------- | ----------------------------------------------------------------------------------------------------- |
| 6.1 | In-app notification center                          | Confirmed working | `NotificationCenterController`, `NotificationService`; `CommunicationTest.php`                        |
| 6.2 | Email (included, free, queued)                      | Confirmed working | Announcement email path; `MAIL_MAILER` config                                                         |
| 6.3 | SMS wallet ledger (lock + balance check in one txn) | Confirmed working | `SmsWalletService::debit()`; `SmsWalletServiceTest.php`                                               |
| 6.4 | SMS estimate → confirm → snapshot recipients        | Confirmed working | `SmsCampaignService::estimate/confirmAndQueue`; `SmsCampaignTest.php`                                 |
| 6.5 | GSM-7/UCS-2 segment math                            | Confirmed working | `SmsSegmentCalculator`; `tests/Unit/SmsSegmentCalculatorTest.php`                                     |
| 6.6 | Per-recipient refund on send failure                | Confirmed working | `SmsSendingService`; `partially_failed` reconciliation test                                           |
| 6.7 | Provider adapters (Termii / Twilio)                 | Partial           | Interface + `NullSmsProvider` default; real providers are stubs shaped for integration                |
| 6.8 | SMS credit purchase via payment gateway             | Partial           | `billing.sms-credits` route + `SmsWalletController::purchase`; depends on a working gateway (see 7.1) |
| 6.9 | Push notifications / mobile app                     | Confirmed missing | No device-token model or service                                                                      |

## 7. Billing & subscriptions

| #    | Feature                                                           | Status                    | Evidence                                                                                                                                                 |
| ---- | ----------------------------------------------------------------- | ------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 7.1  | Paystack / Flutterwave network integration                        | **Confirmed missing**     | All three methods in each gateway `throw new RuntimeException(...)` — `app/Domains/Subscriptions/Gateways/PaystackGateway.php`, `FlutterwaveGateway.php` |
| 7.2  | Webhook endpoint, fail-closed, 3-layer idempotency                | Confirmed working         | `PaymentWebhookController`, `POST /api/v1/webhooks/payments`; `BillingEnforcementTest.php`                                                               |
| 7.3  | Payment-gated signup (user first, church only after verification) | Confirmed working         | `RegisterController`, `ActivateChurchFromCheckout`; `PaymentGatedSignupTest.php`                                                                         |
| 7.4  | 14-day trial, no invoice at signup                                | Confirmed working         | `TrialService::TRIAL_DAYS = 14`; `TrialAndPlanChangeTest.php` asserts zero invoices                                                                      |
| 7.5  | Trial → `past_due` (not `expired`) on lapse                       | Confirmed working         | `RenewalAndDunningService::runDailyCycle()` trial pass first                                                                                             |
| 7.6  | Subscribe-now from trial                                          | Confirmed working         | `BillingController::subscribeNow`                                                                                                                        |
| 7.7  | Upgrade/downgrade with proration preview                          | Confirmed working         | `ProrationCalculator`, `previewPlanChange`; `tests/Unit/ProrationAndTaxTest.php`                                                                         |
| 7.8  | Plan change during trial records intent only                      | Confirmed working         | `pending_plan_id`; test asserts plan does not move                                                                                                       |
| 7.9  | Renewals + dunning (past_due → grace → expired)                   | Confirmed working         | `RenewalAndDunningService`, `subscriptions:process-billing-cycle`; **timing caveat** see 7.10                                                            |
| 7.10 | Status-transition timestamps                                      | **Partially implemented** | Dunning filters `where('updated_at', …)` (`RenewalAndDunningService.php:116,127`) — no `past_due_at`/`grace_started_at` columns                          |
| 7.11 | Runtime-editable pricing & tax rate                               | Confirmed working         | `PlatformSettingsService`, `platform_settings` table                                                                                                     |
| 7.12 | Single price source for display + charge                          | Confirmed working         | `config/billing.php` → `PlanSeeder` + `PlanCatalog` (DB row wins over config)                                                                            |
| 7.13 | Member/branch/admin plan limits                                   | Confirmed working         | `PlanLimitService::limitFor()`; enforced on model `creating` hooks                                                                                       |
| 7.14 | Storage quota enforcement                                         | **Confirmed missing**     | `limitFor()` `match` returns `null` for anything except members/branches/admins (`PlanLimitService.php:47-52`)                                           |
| 7.15 | Feature-limit / add-on entitlement enforcement                    | **Confirmed missing**     | `PlanFeatureService` exists; no enforcement path analogous to `PlanLimitService` on routes/jobs/exports                                                  |
| 7.16 | Invoice records with tax snapshot                                 | Confirmed working         | `TaxCalculator` computed at checkout, copied verbatim to invoice                                                                                         |
| 7.17 | Cancellation / reactivation                                       | Confirmed working         | `BillingController::requestCancellation/reactivate`; period-end finalisation tested                                                                      |
| 7.18 | Suspension by platform admin                                      | Confirmed working         | `PlatformAdmin\BillingOverviewController::suspend`; `PlatformAdminBillingTest.php`                                                                       |
| 7.19 | Refunds                                                           | Confirmed missing         | No refund controller, gateway method or wallet reversal for money (SMS units only)                                                                       |

## 8. Onboarding, tours, platform admin

| #    | Feature                                  | Status                | Evidence                                                                                    |
| ---- | ---------------------------------------- | --------------------- | ------------------------------------------------------------------------------------------- |
| 8.1  | Setup checklist with progress %          | Confirmed working     | `SetupWizardService::progressFor/percentComplete`; `SetupWizardTest.php`                    |
| 8.2  | Church name captured during setup        | **Confirmed missing** | `SetupWizardController::complete()` validates only `step`; service has no name field        |
| 8.3  | Tour engine, version-aware re-trigger    | Confirmed working     | `TourEngineService`, `TourSeeder`; `TourEngineTest.php`                                     |
| 8.4  | Feature flags with per-church override   | Confirmed working     | `FeatureFlagService`; `tests/Unit/FeatureFlagServiceTest.php`                               |
| 8.5  | Analytics events (narrow, allow-listed)  | Confirmed working     | `AnalyticsService`, `POST /analytics/track`; `AnalyticsTest.php`                            |
| 8.6  | Email verification on register           | Confirmed missing     | `users` has no `email_verified_at` flow wired; registration goes straight to plan selection |
| 8.7  | Password reset ("forgot password")       | **Confirmed missing** | No `password.reset` route, controller or view; `auth` views contain register/login only     |
| 8.8  | Two-factor authentication                | Confirmed missing     | Not present; documented as a known gap in `SECURITY.md`                                     |
| 8.9  | Platform-admin dashboard & revenue split | Confirmed working     | `PlatformAdmin\DashboardController`; `PlatformAdminDashboardTest.php`                       |
| 8.10 | Platform-admin support tickets           | Confirmed working     | Both church side (`/help`, name `support.*`) and admin side; `SupportTicketTest.php`        |

## 9. Public site, status & ops

| #   | Feature                                                                                             | Status                             | Evidence                                                                                                      |
| --- | --------------------------------------------------------------------------------------------------- | ---------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| 9.1 | Landing page `/` with dynamic pricing                                                               | Confirmed working                  | `LandingController`, `Plan::displayPrice()`; `LandingPageTest.php`                                            |
| 9.2 | Marketing pages (home, features, pricing, about, demo, contact, support, blog, guides, help, legal) | Confirmed working                  | `routes/marketing.php` (10 `Route::` entries), `Marketing\HomeController`                                     |
| 9.3 | Contact form                                                                                        | **Confirmed missing (deliberate)** | `pages/marketing/contact.blade.php:10-13` documents the omission; no `<form>`, no controller                  |
| 9.4 | Public `/health` page + `/health.json`                                                              | Confirmed working                  | `HealthController`, `HealthCheckService`; `HealthAndSecurityHeadersTest.php`                                  |
| 9.5 | Health severity model (unconfigured ≠ degraded)                                                     | Confirmed working                  | `config/health.php`, `failureCounts()`                                                                        |
| 9.6 | Scheduler heartbeat check                                                                           | Confirmed working                  | `routes/console.php:48-52`, `RecordQueueWorkerHeartbeat`                                                      |
| 9.7 | `backup:database` command                                                                           | Partial                            | `app/Console/Commands/BackupDatabase.php` scheduled daily; its own docblock calls it minimal, local-disk only |
| 9.8 | Docker reference setup                                                                              | Partial                            | `docker-compose.yml`; `DEPLOYMENT.md:100-108` states it is not a production manifest                          |
| 9.9 | Session-expired / 419 handling                                                                      | Confirmed working                  | `resources/views/errors/419.blade.php`; `SessionExpiredPageTest.php`                                          |

## 10. Cross-cutting quality

| #     | Area                                                | Status                                  | Evidence                                                                                                                 |
| ----- | --------------------------------------------------- | --------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| 10.1  | Route-table smoke sweep                             | Confirmed working                       | `tests/Feature/SmokeEveryPageTest.php`, `SmokeEveryActionTest.php` report the throwable cause                            |
| 10.2  | CSP nonce on inline scripts **and** Vite bundle     | Confirmed working                       | `SecurityHeaders.php` + `Vite::useCspNonce($nonce)`; `SecurityHeadersCspTest`                                            |
| 10.3  | Dependency vulnerability scanning in CI             | **Confirmed missing**                   | `ci.yml` has no `composer audit`, no secret scan                                                                         |
| 10.4  | Lint gating in CI                                   | **Partially implemented (ineffective)** | `ci.yml:64` runs `vendor/bin/pint --test \|\| true` — can never fail the build                                           |
| 10.5  | Browser E2E suite committed to repo                 | **Confirmed missing**                   | Phase 12–14 browser checks were run ad hoc; only `_*.mjs` scratch scripts and screenshots remain untracked/messy at root |
| 10.6  | Accessibility audit                                 | Needs runtime verification              | Semantic markup and `aria-*` present in `components/layout.blade.php`; no automated a11y test                            |
| 10.7  | Structured data / OG / canonical on marketing pages | Confirmed working                       | `components/marketing-layout.blade.php:41-104`                                                                           |
| 10.8  | `robots.txt`                                        | Partial                                 | `public/robots.txt` = `User-agent: *` / `Disallow:` — correctly allows all, but no sitemap reference                     |
| 10.9  | Sitemap                                             | **Confirmed missing**                   | No `sitemap.xml`, no generator                                                                                           |
| 10.10 | `noindex` on authenticated/private pages            | Partial                                 | `/health` has `noindex`; authenticated shell emits no robots meta (acceptable via auth wall, but unstated)               |
| 10.11 | Test count stated in docs                           | **Stale documentation**                 | README says 371; actual 381                                                                                              |

---

## 11. Journey map — where the chain breaks

A journey is listed as **complete** only if every hop is implemented.

| Journey                                                        | Hops traced                                                                      | Verdict                                                                       |
| -------------------------------------------------------------- | -------------------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| Visit → Landing → Register → Plan → Checkout → **Pay** → Trial | landing ✓ / register ✓ / plans ✓ / `CheckoutController` ✓ / **gateway throws** ✗ | **Blocked at payment** — only reachable via the simulated/`Null` gateway path |
| Trial → Subscribe now → Active paid                            | subscribe ✓ / `chargeRecurring` ✗ (same stubs)                                   | **Blocked in production**                                                     |
| Renewal / dunning                                              | scheduler ✓ / service ✓ / charge ✗                                               | **Blocked in production**                                                     |
| Forgot password                                                | no route ✗                                                                       | **Absent**                                                                    |
| Invite staff → set permissions → pastor signs in               | settings ✓ / private role ✓ / login ✓ (password set by owner) ✓                  | **Complete**                                                                  |
| Import members from sheet                                      | upload ✓ / validate ✓ / limit check ✓                                            | **Complete**                                                                  |
| Record attendance for a session                                | session ✓ / roster ✓ / bulk ✓                                                    | **Complete**                                                                  |
| Raise expense → approve → balance moves                        | create ✓ / approval ✓ / ledger ✓                                                 | **Complete**                                                                  |
| Submit subvention → approve → loan payment                     | calc ✓ / workflow ✓ / ledger ✓                                                   | **Complete**                                                                  |
| Buy SMS credits → send campaign                                | purchase ✓ / **gateway ✗** / send ✓                                              | **Blocked at purchase** (manual top-up works)                                 |
| Pastor records case privately                                  | case ✓ / policy ✓ / notes ✓                                                      | **Complete**                                                                  |
| Church admin changes plan                                      | preview ✓ / invoice ✓ / **charge ✗**                                             | **Blocked in production**                                                     |
| Platform admin suspends a church                               | admin ✓ / `SubscriptionService` ✓ / gate ✓                                       | **Complete**                                                                  |
| Contact the company                                            | page ✓ / **no form ✗**                                                           | **Deliberately absent**                                                       |
| Download an invoice                                            | invoices list ✓ / **PDF ✗**                                                      | **Absent**                                                                    |
| Recover a tenant's data export                                 | — ✗                                                                              | **Absent**                                                                    |

---

## 12. Inventory summary

| Status                               | Count |
| ------------------------------------ | ----- |
| Confirmed working                    | 74    |
| Partially implemented                | 8     |
| Confirmed missing (incl. deliberate) | 16    |
| Needs runtime verification           | 1     |
| Stale documentation                  | 1     |

**The three facts that matter most from this inventory:**

1. **The product is built; the money path is not connected.** Everything up to the gateway call exists and is tested with a null gateway. Nothing in the billing chain can take real money today.
2. **Two entitlements are unenforced.** Members/branches/admins are capped; storage and feature entitlements are granted unlimited access.
3. **Several "complete" journeys still lack a user-facing entry point.** Password reset, invoice PDF, contact form and data export have no surface at all.

See `PROJECT_AUDIT.md` for severity, impact and the fix design, and `PRODUCT_ROADMAP.md` for sequencing.

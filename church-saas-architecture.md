# Church Management SaaS — Technical & Product Architecture
### Phase 0 Deliverable: Legacy Analysis + Target Architecture

---

## A. Existing Project Analysis (`rccgsubvention.zip`)

### A.1 What the legacy app actually is

A single-tenant PHP application for one denomination (RCCG) that automates a monthly **"subvention"** process — provinces (branches) report their tithe/income figures, the system computes how much money the province must remit to (or receive from) headquarters, tracks loan deductions, and routes the result through an approval workflow.

**Stack:** raw procedural PHP (mixed `mysql_*` **and** `mysqli_*` APIs in the same codebase — the `mysql_*` calls in `r-login.php`/`uploadcsv.php` etc. were removed from PHP entirely in PHP 7.0, so large parts of this app cannot run on any supported PHP version today), MySQL/MariaDB, Bootstrap 2, jQuery, jqGrid. No framework, no autoloading, no dependency manager for the core app (there is a stray `admin/package.json` and a `.travis.yml`/`MIT-LICENSE.txt` seemingly left over from a copied vendor library, not the app itself).

### A.2 Database structure (`database/rccgsubv_portal.sql`)

14 flat, denormalized tables, `latin1` charset, no foreign keys, no indexes beyond implicit primary keys, several `int`/`varchar` primary keys with no `AUTO_INCREMENT` visible in the dump:

| Table | Purpose | Notable design issues |
|---|---|---|
| `provinces_svp` (384 rows) | Branch/province master list | `province_code` (e.g. `AB001`) used as a **string foreign key** everywhere instead of an ID; no `churches`/tenant concept — RCCG is the only "tenant" |
| `tbl_groups` (3 rows) | Rate tiers (Group 1/2/3) | Rates (`general_rate`, `minister_rate`, `admin_rate`, `admin_max`) are stored as flat percentages per group — this **is** the configurable-rule seed the new platform should generalize |
| `admin` | Province-level admin users | `password varchar(20)` — passwords are stored **in plaintext or a truncated hash**, not a real hash |
| `super_admin` | HQ-level users | Same plaintext password issue |
| `registration` | End-user accounts (unclear if used) | `MyISAM` engine (no transactions/FK support), same password issue |
| `monthly_svp` (55 rows) | Monthly income figures submitted per province | No status/workflow column — "submitted" is implied only by row existence |
| `calculated_svp` (37 rows) | Computed subvention result per province/month | Written once by the calculation script; no versioning if recalculated |
| `loans_svp` | Loan ledger per province | `total_repay` is **mutated in place** by the calculation script (see A.4) — no payment history/audit trail |
| `upload_loans_svp`, `upload` | Staging tables for CSV bulk import | Raw CSV rows trusted and inserted without validation |
| `approval` | Approved subvention snapshot | Duplicates most of `calculated_svp`'s columns rather than referencing it |
| `update_rent` | Ad-hoc rent adjustments per province/month | Free-text `fullname` field, no member relationship |
| `account_detail`, `banklist` | Bank code / account number per province | No masking, no relationship to a real disbursement/payment record |

**Verdict:** this is a working spreadsheet-replacement, not a relational data model. There is exactly one organizational level (`province`) — no church, no diocese/area hierarchy, no members, no departments, no attendance, no events. Everything the new platform needs beyond "compute a monthly remittance figure" is absent from the legacy system.

### A.3 Authentication & authorization

* Three separate, unrelated user tables (`admin`, `super_admin`, `registration`) with **no shared `users` table and no roles/permissions table** — role is determined entirely by *which table the login query happens to match* (`r-login.php`).
* Passwords compared with plain `=` in raw SQL, stored in `varchar(20)` columns — this rules out any real hashing algorithm (bcrypt/argon2 output is 60+ chars), so passwords are effectively plaintext or weakly obfuscated.
* Login queries build SQL by directly concatenating `$_POST['email']`/`$_POST['password']` into the query string in `r-login.php`: classic **SQL injection**. `mysqli_real_escape_string` is used in some newer files (`admin/calculate-subvention.php`) but not consistently, and the oldest login path uses the non-existent `mysql_real_escape_string`/`mysql_query` functions.
* `error_reporting(0)` is set at the top of nearly every file, which will hide fatal errors (including the `mysql_*`-does-not-exist errors) from developers and likely from attackers probing the app, while still leaving stack traces logged to `error_log` files that are **shipped inside the zip itself** (`admin/error_log`, `user/error_log`, root `error_log`) — a real information-disclosure risk if that pattern continues (e.g., verbose errors written to a web-accessible path).
* No CSRF tokens on any form, no rate limiting on login, no 2FA, no session regeneration on privilege change, no logout-everywhere/session table.
* DB credentials (`root` and blank password, plus a second commented-out real-looking username `rccgsubv_admin` / password `quaZeem.@`) are hard-coded in `connection/config.php`, committed inside the codebase.

### A.4 The subvention calculation (the one piece of real, reusable business logic)

From `admin/calculate-subvention.php`, per province/month, once income figures exist in `monthly_svp`:

```
minister_calc  = minister_tithe  × minister_rate       (rate from the province's group)
admin_calc     = MIN(adminval × admin_rate, admin_max)  (capped per group)
general_calc   = general_tithe  × general_rate
retention      = general_calc + minister_calc
others         = admin_calc + salary
total_val      = retention − others
if total_val < 0:
    subvention = |total_val| − monthly_loan_deduction   (if the province has an open loan)
             or  |total_val|                             (no loan)
else:
    subvention = 0
```

The result and a mutated loan running-total (`total_repay += monthly_deduction`) are written back. This is genuinely useful business logic — a **percentage-of-income remittance formula with a rate tier, a capped fee, and a loan-deduction offset** — but it is hard-coded per denomination in PHP rather than data-driven, has no idempotency guard (running the calculation twice for the same month re-inserts and re-deducts), and updates the loan balance as a side effect of an unrelated report-generation page.

### A.5 Other workflow observations

* **Approval**: `admin/approval.php` / `subvention-approval.php` show a manual review screen where an admin/super-admin edits and confirms figures before they move into the `approval` table — a two-stage (compute → approve) workflow, but with no `submitted_by`/`approved_by`/timestamps/rejection-reason columns to make it auditable.
* **CSV import** (`uploadcsv.php`, `admin/upload-loans-svp.php`): raw `explode(",", ...)` parsing of a textarea/pasted value with **positional column mapping and zero validation** — a malformed row silently inserts garbage or breaks the insert loop.
* **Email**: `functions/func-email.php` / `func-email-advance.php` and `automatic_email_sender.php` — plain `mail()`/SMTP-style notification helpers, no queueing, so bulk sends would be synchronous and slow/unreliable at scale.
* **Reports**: `download.php`, `download-report.php` generate CSV/Excel-ish exports directly from query loops (string concatenation), not a real export library.

### A.6 What to preserve vs. redesign

**Preserve (as re-engineered, data-driven concepts):**
- The rate-tier/group percentage model (`tbl_groups`) → generalized into configurable **subvention rule sets**.
- The retention/subvention/loan-offset **calculation shape** (percentage of income, capped admin fee, loan deduction against a shortfall) → reimplemented as a versioned, testable calculation service.
- The two-stage compute → approve workflow concept → reimplemented as a proper state machine with full audit trail.
- The province list and group assignments (384 provinces, 3 groups) → migratable as **seed/reference data** for an RCCG-specific tenant once the generic organizational-hierarchy model exists.

**Redesign / discard entirely:**
- All authentication and password storage.
- The three-separate-user-tables role model → unified `users` + RBAC.
- Direct SQL string concatenation everywhere → Eloquent/query builder with parameter binding.
- `province_code` string joins → proper foreign-key integer IDs.
- Hard-coded RCCG terminology and hard-coded formulas in PHP → configurable organizational hierarchy + configurable rule engine.
- Synchronous email, unvalidated CSV import, in-place loan balance mutation.
- Bootstrap 2 / jQuery / jqGrid UI.

### A.7 Migration plan for existing data

1. Load the SQL dump into a scratch MySQL instance (do **not** point the new app at it directly).
2. Write one-off Laravel artisan commands (not long-lived code) that:
   - Import `provinces_svp` + `tbl_groups` → new `organizational_units` + a seeded `subvention_rule_sets` for an "RCCG" tenant.
   - Import `admin`/`super_admin`/`registration` → new `users` table, **forcing a password reset** for every migrated account (never carry old password values forward).
   - Import `monthly_svp`/`calculated_svp`/`approval` history → new `subvention_submissions`/`subvention_calculations` tables, tagged as historical/read-only records so the new calculation engine doesn't try to "resume" them.
   - Import `loans_svp` → new `loans` + a synthesized opening `loan_payments` entry so `loan_balance` isn't lost.
3. Validate row counts and spot-check totals (e.g. sum of `subvention` per province/year) against the legacy `calculated_svp` table before decommissioning the old system.

---

## B. Product Architecture

### B.1 Modules (bounded contexts)
Churches & Tenancy · Organizational Structure · Members & Families · Departments & Groups · Attendance · Events & Calendar · Finance (accounts/income/expense/budgets) · Loans · Subvention (rule engine) · Approvals (shared workflow engine) · Communication (email/SMS/push/in-app) · Pastoral Care · Content (sermons/documents) · Reporting · Audit · Subscriptions & Billing · SMS Wallet · Platform Administration · Product Tours/Onboarding.

### B.2 Tenant model
- **Tenant = Church** (the top-level paying account). A tenant owns its own organizational tree, users, members, finances, and settings.
- **User** is a platform identity that belongs to exactly one tenant for normal church staff (a `church_id` on `users`), with a **separate** `platform_admins` table for Anthropic-of-this-SaaS-style staff who are never implicitly a church user (requirement §53).
- **Organizational Unit** is generic and self-referencing (`parent_id`), typed by a per-tenant-configurable `unit_types` table, so "Province," "Diocese," or "Region" are just data, not schema.

### B.3 Permission model
Spatie-style RBAC: `roles`, `permissions`, `role_permissions`, `model_has_roles` — all scoped by `church_id`. Church admins manage their own roles from a configurable role editor (permission *names* are a fixed system catalog like `members.view`, `finance.approve`; which permissions compose a role is configurable per tenant).

---

## C. Database (representative core schema — not exhaustive)

Key relationships (abbreviated, all tenant-scoped tables carry `church_id`):

```
churches 1—* organizational_units (self-referencing tree, unit_type_id → unit_types)
churches 1—* users, 1—* roles
organizational_units 1—* members, 1—* departments
members 1—* member_profiles, *—* families (via family_members, with a role: head/spouse/child)
members *—* departments (department_members), *—* groups (group_members)
events 1—* event_registrations, 1—* attendance_records
financial_accounts 1—* transactions (polymorphic: income/expense/donation/transfer)
budgets 1—* budget_items
loans 1—* loan_payments
subvention_rule_sets 1—* subvention_rules (type: percentage|fixed|capped, base: field reference)
subvention_periods 1—* subvention_submissions (per org unit) 1—* subvention_calculations
approvals: polymorphic (approvable_type, approvable_id) + approval_steps for multi-level review
sms_wallets 1—* sms_transactions; sms_campaigns 1—* sms_campaign_recipients
subscriptions belongsTo plans; invoices belongsTo subscriptions|sms_wallets
audit_logs: polymorphic (auditable_type, auditable_id, user_id, old_values JSON, new_values JSON)
```

**Constraints/indexes:** every tenant-scoped table gets a composite index starting with `church_id`; `organizational_units` gets a `(church_id, parent_id)` index for tree queries; `transactions`/`attendance_records` get `(church_id, occurred_at)` for reporting; unique constraints on `(church_id, email)` for members and `(email)` globally for `users`. Soft deletes (`deleted_at`) on members, events, documents, transactions — never hard-delete financial or people data. Money stored as integer minor units or `decimal(14,2)`, never `float`.

---

## D. Laravel Architecture

```
app/
  Domains/
    Churches/{Models,Actions,Services}
    Members/...
    Organizations/...
    Finance/...
    Subventions/{Models,Services/SubventionCalculationEngine.php,Rules/}
    Loans/...
    Attendance/...
    Events/...
    Approvals/{Services/ApprovalWorkflow.php}   ← shared state-machine engine reused by Subvention/Finance/Loans
    Communication/{Mail,Sms/Providers/{Termii.php,Twilio.php}}
    Subscriptions/{Models,Services/BillingGateway.php,Gateways/{Paystack.php,Flutterwave.php}}
    PlatformAdmin/...
  Http/Controllers   (thin — delegate to Actions/Services)
  Policies           (one per tenant-scoped model, always checks church_id ownership first)
  Jobs               (SendSmsCampaignJob, CalculateSubventionJob, ExportReportJob)
  Events / Listeners (SubventionApproved → NotifyProvinceAdmin, PaymentVerified → ActivateChurch)
```

**Multi-tenancy strategy:** single database, shared schema, **row-level scoping via a global Eloquent scope** (`BelongsToChurch` trait + `TenantScope`) applied automatically to every tenant model, backed by middleware that resolves the current tenant from the authenticated user (or subdomain, if custom domains are added later) and binds it into a `Tenancy` singleton — so a forgotten `where('church_id', ...)` is structurally impossible because the scope is global, not opt-in. Platform-admin routes run in a separate middleware group with tenancy explicitly disabled. This is chosen over separate-database-per-tenant for cost/operational simplicity at the expected scale (hundreds–low thousands of tenants); the abstraction (a `TenantScope` contract) leaves room to move a large/enterprise tenant to its own database later without an application rewrite.

---

## E. UI/UX
Livewire 3 + Tailwind, one shared design-system (tokens for color/spacing/type in a single Tailwind config + Blade component library: buttons, cards, tables-that-become-cards on mobile, modals used sparingly). Role-specific dashboards per §40. Landing page follows the interactive feature-explorer/scroll-story pattern from the brief. Onboarding: setup-progress checklist + a reusable **Tour Engine** (`tours`, `tour_steps`, `user_tour_progress` tables, versioned so a re-released tour can be shown again) that also drives feature-discovery ("✨ New Feature") prompts and, per the billing requirements, explicitly walks new church owners through Subscription and SMS Wallet screens.

---

## F. SaaS Subscription & Billing
- `plans` (feature limits as JSON: `max_members`, `max_branches`, `max_admins`, `storage_mb`, feature flags) → `subscriptions` (status: pending/active/past_due/grace_period/cancelled/expired/suspended) → `invoices`.
- **Payment-gated tenant creation**: a church row is created only after a webhook-verified payment; an interim `checkouts` table (status: pending/expired/completed) holds the user's chosen plan while they complete payment, so abandonment never creates an active tenant.
- `BillingGateway` interface with `Paystack`/`Flutterwave` implementations; internal events (`PaymentVerified`, `PaymentFailed`, `SubscriptionRenewed`) are provider-agnostic — nothing outside the gateway classes knows which provider is active.
- **SMS is billed separately** from the subscription: `sms_wallets` (balance) + `sms_transactions` (ledger: purchase/debit/refund/adjustment, each referencing a campaign or payment) + `sms_credit_packages` (admin-configurable pricing, never hard-coded). Sending is blocked at confirmation time if `estimated_units > wallet_balance`; confirmed campaigns **reserve** credits before the queued job runs, to prevent two simultaneous campaigns from double-spending the same balance.
- Idempotency: every webhook and payment record stores the provider's transaction reference under a unique DB constraint; handlers are safe to run twice.
- Expired/cancelled subscriptions restrict access (read-mostly / banner + upgrade prompt) rather than deleting data, per the data-retention requirement.

---

## G. Security
Hashed passwords (bcrypt/argon2 via Laravel's default), CSRF via Laravel's built-in middleware, parameterized queries via Eloquent everywhere (no raw string interpolation), Form Request validation on every write endpoint, Policies checked in every controller action, tenant scoping as described in §D, rate limiting on login/API, secure file uploads (MIME-sniffed + stored outside the public webroot with signed download URLs, never trusting the client-supplied extension), `.env`-based secrets with a committed `.env.example` containing no real values, and an `audit_logs` table populated via model observers for every sensitive action (§25).

---

## H. Development Roadmap
Phase 0 (this document) → Phase 1 Foundation (auth, tenancy, RBAC, audit) → Phase 2 Church/People (org structure, members, families, departments) → Phase 3 Activities (events, calendar, attendance) → Phase 4 Finance → Phase 5 Subvention (rule engine + migrated RCCG data as first configured tenant) → Phase 6 Pastoral Care → Phase 7 Communication (email free / SMS wallet) → Phase 8 SaaS billing (payment-gated registration) → Phase 9 Onboarding/Tours → Phase 10 Hardening & deployment. Each phase ships with its own tests (unit tests for every financial/subvention calculation, feature tests including an explicit "Church A cannot read Church B's data" test) before the next phase begins.

---

## I. Migration Summary
See §A.7 above — province/group/rate data becomes seed data for a generic rule engine; user accounts are re-created with forced password resets; historical subvention/loan records are imported as read-only history rather than live, resumable state.

---

## Immediate Next Step
Recommend starting **Phase 1 (Foundation)**: Laravel 12 skeleton, Docker, `users`/`churches`/tenancy scope, RBAC tables, login/register/password-reset, and the audit-log observer — with a first automated test asserting cross-tenant isolation before any business module is built on top of it. Say the word and I'll scaffold that phase.

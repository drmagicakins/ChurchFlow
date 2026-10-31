# CHURCHFLOW — PROJECT-WIDE SESSION HANDOFF

**Prepared:** 24 October 2026  
**Source:** documentation-led review of the supplied `ChurchFlow.zip` archive.  
**Important:** this is a proposed handoff draft created outside the repository. It has not been committed or merged into the user's working tree. CodeGPT must reconcile it with the current Git branch and preserve all valid details from the existing root `SESSION-HANDOFF.md` before replacing or merging anything.

## 1. Resume here

The immediate next task is a **read-only, evidence-backed repository audit**. Do not start feature implementation yet. Read root `AGENTS.md`, inspect `git status --short --branch` and recent commits, read the product/security/deployment documents, map the current implementation, and verify the known gaps below against the exact current branch. Create/update `docs/PROJECT_AUDIT.md`, `docs/PRODUCT_ROADMAP.md`, `docs/CUSTOM_ADDONS_ARCHITECTURE.md`, and a canonical development workflow document only if equivalent files do not already exist. Do not change app code, database state, dependencies, UI, or existing features during this audit.

Use `CODEGPT_MASTER_READ_ONLY_AUDIT_PROMPT.md` from the audit pack as the full assignment. If CodeGPT cannot load `AGENTS.md`, stop and configure its custom instructions before proceeding.

## 2. Repository state — must be re-verified

- Project: ChurchFlow, a Laravel church-management SaaS.
- The supplied archive contains Laravel application source, documentation, `.git`, `vendor`, `node_modules`, `legacy`, logs, screenshots, temporary QA scripts, and a root `.env`.
- `composer.json` specifies PHP `^8.2` and Laravel `^12.0`.
- Root `AGENTS.md` contains the strict code-preservation policy.
- Existing root `SESSION-HANDOFF.md` is a detailed feature-specific handoff for parish pastor permissions/parish-role lock. It says “Nothing has been committed,” but that statement is historical and must be checked against current Git status/commit history.
- Current branch, commit hash, dirty/clean status, installed runtime, database state and running services were **not verified by this documentation-only review**. Record them before doing any work.
- The README reports 371 passing tests in its current summary, but this was not independently rerun during this review. Historical test counts elsewhere in README are not current proof.

## 3. Product architecture described in documentation

The README describes foundations for:

- Multi-tenant church accounts, tenant scopes, RBAC, organizational hierarchy and audit logging.
- Member CRM, families, departments/groups, custom fields, CSV import/export.
- Events, attendance, calendar, announcements, tasks and reminders.
- Finance, approvals, transfers/voiding, loans, subvention calculations.
- Restricted pastoral care and append-only case notes.
- Email, in-app notifications and a separately billed SMS wallet/campaign flow.
- Checkout/subscriptions/trial, platform-admin settings, tours/onboarding, feature flags, marketing pages, health/status page and reusable UI components.

These are documentation claims, not a certification that every end-to-end workflow works today. CodeGPT must map each user journey from interface through route, middleware/policy, service/model, persistence, queue/provider and test.

## 4. Highest-priority documented gaps to verify

1. **Payment provider integration:** README says Paystack/Flutterwave network code still throws. Verify actual adapters. This is a commercial blocker until sandbox end-to-end flows are proven.
2. **Plan limits/entitlements:** README says storage and feature-flag limits are stored on plans but not enforced. Verify and implement central server-side entitlement checks only after audit and approval.
3. **Church-name onboarding:** README says setup collects completion but not the actual church name.
4. **Invoice PDFs:** explicitly documented as not built.
5. **Trial/dunning timing:** `updated_at` is used as a proxy for time in a billing status; verify whether unrelated edits can reset timing.
6. **Security automation:** `SECURITY.md` documents no dependency scanning in CI, no built-in 2FA, no WAF/DDoS guidance and selective rate limiting.
7. **Operational readiness:** deployment doc calls Docker a reference/dev setup and the local backup command minimal; off-site backup/restore, monitoring, queue/scheduler reliability and production configuration need evidence.
8. **Public support/content:** landing build notes say contact/ticket forms, blog posts and video demo are not functional/available; legal pages are drafts. Verify before publishing claims.
9. **UI/accessibility/SEO:** perform a role-by-role browser audit, responsive checks, accessibility checks, SEO metadata/indexability review and validation of marketing claims.
10. **Project-wide documentation:** the current handoff is feature-specific. Check whether a canonical workflow doc exists before creating one; the root inventory did not show an obvious root `WORKFLOW.md` or `docs/WORKFLOW.md`.

Use statuses: `Confirmed missing`, `Partially implemented`, `Needs runtime verification`, `Confirmed working`, or `Superseded/stale documentation`. Attach exact file/test evidence to every finding.

## 5. Future custom feature / add-on direction

Design a reusable add-on and entitlement system, not one-off code branches per church. First inspect existing `plans`, `subscriptions`, `invoices`, `checkouts`, feature flags, platform settings and billing services. Separate:

- Plan-included feature/limit.
- Paid recurring or one-time add-on.
- Usage-based service/charge.
- Audited tenant-specific custom contract/override.
- Internal feature rollout flag.

The platform admin should be able to configure add-on catalogue items, price versions/currency/interval, tenant-specific quotes, audited grants/revocations, expiry and quotas. Church-specific access must activate only after verified payment or an explicit audited admin grant. The backend must enforce entitlements on web/API routes, jobs, exports, file access and provider calls. UI hiding is not security. Downgrade/revocation must not silently delete tenant data. Existing invoice prices must remain immutable snapshots.

Candidate future add-ons: advanced reports/analytics, communications, online giving, advanced multi-branch controls, document vault, ticketing/QR check-in, advanced attendance, volunteer rota, facility booking, pastoral workflows, safeguarding workflows, inventory/assets, training, media library, accounting integrations, white-label/custom domains, SSO, API/webhooks and enterprise support. Validate demand, cost-to-serve, legal/privacy implications and support capacity before committing to a build or price.

## 6. Security and archive hygiene warning

The uploaded ZIP contains `.env`, `.git`, `vendor`, `node_modules`, a legacy application, logs and temporary QA artefacts. Do not redistribute this archive as-is. Do not print or copy secret values. Verify locally whether `.env` is tracked; if live credentials may have been exposed, rotate them carefully. Do not delete QA scripts, screenshots, accounts or database rows merely because the old handoff lists them; inventory and obtain approval first.

## 7. Preservation and execution rules

- Read `AGENTS.md` and obey it. No unauthorized removal, redesign, refactor or simplification.
- Preserve all current UI sections, routes, business rules, tenant protections, billing and tests.
- Audit and design first; do not edit application code during the first audit assignment.
- Before any later implementation task, identify exact scope, inspect current implementation, state a plan, and seek approval for cross-cutting changes.
- Never run destructive Git/database commands, dependency upgrades, or QA-data cleanup without explicit approval.
- After approved implementation, inspect `git diff`, run relevant tests, check UI in browser when applicable, and record exact results.
- Do not commit/push unless explicitly requested.
- Update this handoff at the end of each work package and before stopping for context/time limits.

## 8. Required next actions

1. Verify branch, commit and working-tree state without modifying it.
2. Read the full README, architecture, security, deployment, setup, landing build log and existing handoff.
3. Inventory routes, middleware, models, migrations, policies, services, jobs, views, CI and tests.
4. Build an evidence-based completeness matrix and mark stale documentation.
5. Run only safe, approved baseline checks; report what was not run.
6. Produce a prioritized product/engineering/design/security/SEO/marketing backlog with acceptance criteria.
7. Design the add-on entitlement architecture after schema audit.
8. Update the actual repository handoff with the current verified state, preserving the old parish-pastor handoff detail.
9. Stop and request approval before changing app code.

## 9. Verification record for this handoff

- Test suite run during this review: **No**.
- Frontend build run during this review: **No**.
- Live browser testing performed during this review: **No**.
- Production payment integration tested during this review: **No**.
- Security scanner run during this review: **No**.
- Application code changed during this review: **No**.
- Database changed during this review: **No**.
- Git commit/push performed during this review: **No**.

The audit pack contains a documentation-led gap analysis and instructions for the next CodeGPT session. All findings must be verified against the actual current branch before implementation.

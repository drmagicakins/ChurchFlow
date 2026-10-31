# ChurchFlow — Proposed Product Completion Roadmap

This is a proposed sequence, not authorization to implement everything in one pass. Every phase requires a baseline, written acceptance criteria, approval, incremental delivery, tests, diff review and handoff update.

## Phase 0 — Baseline, scope and safe workflow

- Verify branch, commit, working tree and active local changes.
- Confirm Laravel/PHP/Node/database versions and clean setup steps.
- Map all routes, middleware, policies, models, services, jobs, migrations and tests.
- Separate current facts from stale documentation and historical test counts.
- Inventory QA artefacts without deleting them.
- Add project-wide handoff, evidence-backed audit, workflow and ADR index.

**Exit criteria:** all P0/P1 findings have evidence and status; baseline test/build results are recorded; no application behavior changed.

## Phase 1 — Billing and commercial correctness

- Verify checkout → gateway → signed webhook → server-side transaction verification → tenant/subscription activation.
- Replace provider adapters that are still stubs/throwing with tested sandbox integrations.
- Enforce idempotency, replay protection, retry/reconciliation, refunds and failure states.
- Implement plan feature/limit enforcement and a central entitlement API.
- Collect and persist the actual church name during setup.
- Review trial/renewal/dunning state timestamps.
- Implement invoice PDF/print/email and immutable invoice line snapshots.

**Exit criteria:** sandbox purchase, duplicate/out-of-order webhook, failure, renewal and cancellation journeys pass; limits are enforced server-side; no false “paid” state is possible from client-only input.

## Phase 2 — Security, privacy and operations

- Composer/frontend dependency audit and secret scanning in CI.
- Threat model tenant escape, IDOR, privilege escalation, pastoral record leakage, upload attacks, CSV formula injection, webhook abuse and data export leakage.
- Review auth recovery/session settings, 2FA requirement, throttling and CSP behavior.
- Production queue/scheduler monitoring, structured logs/redaction, backups to off-site storage and restore drill.
- Define incident response, RPO/RTO, retention and data export/deletion policy.

**Exit criteria:** high-severity findings remediated or formally accepted; CI gates active; backup restore demonstrated; production runbooks reviewed.

## Phase 3 — End-to-end product QA

- Route and page inventory by role/subscription state.
- Browser tests for owner, restricted staff, parish pastor, platform admin, guest, trial, past-due and expired users.
- Regression coverage for tenant isolation, finance, approvals, billing, CSP/Alpine, confirm dialog, reminders and file downloads.
- Validate forms, tables, chart accuracy, CSV import/export, queue behavior and error recovery.

**Exit criteria:** critical workflows have repeatable automated tests and browser acceptance evidence; known failures are documented.

## Phase 4 — UI/UX, accessibility and SEO

- Establish visual design tokens and component inventory; preserve `mk-*` marketing and `cf-*` app boundaries.
- Audit responsive layouts, keyboard access, focus, labels, contrast, reduced motion and dialog behavior.
- Add coherent loading/empty/error/permission-denied states.
- Verify titles, descriptions, canonical URLs, robots/sitemap, Open Graph, structured data accuracy, image optimization and private-page noindex rules.
- Make contact/support/demo pages functional or clearly unavailable; verify all claims and testimonials.

**Exit criteria:** prioritized issues fixed page-by-page, no wholesale redesign without approval, browser screenshots/tests and accessibility checklist attached.

## Phase 5 — Support and content readiness

- Implement real contact/support submission with validation, spam controls, privacy notice and staff workflow.
- Add verified help articles and onboarding guidance.
- Review pricing/terms and plan limits for consistency between marketing, checkout, invoice and platform admin.
- Obtain legal review of privacy/terms and clear draft notices only after review.

**Exit criteria:** every public form has a real destination and confirmation; published claims match actual functionality.

## Phase 6 — Custom add-ons platform

- Audit existing billing/plan/feature-flag schema.
- Implement catalog + price versions + tenant grants + entitlement service + audit history.
- Build platform-admin configuration and church-facing marketplace/status UI.
- Connect verified payment and invoice lifecycle; implement manual grant with reason/expiry/approval.
- Pilot one add-on (recommend Advanced Reports or Document Management depending on customer demand).

**Exit criteria:** one add-on can be priced by platform admin, purchased or granted to one church, enforced across all backend paths, expired/revoked safely, billed correctly and tested for tenant isolation.

## Phase 7 — Demand-led feature expansion

Potential add-ons: advanced reports, communication campaigns, online giving, multi-branch controls, document vault, ticketing/QR check-in, advanced attendance, volunteer scheduling, facility booking, pastoral follow-up, safeguarding, inventory, training, media, accounting integrations, custom domains/white label, SSO, API/webhooks and enterprise support.

**Exit criteria for each add-on:** validated user demand, cost/pricing model, dependencies, security review, UI/UX spec, acceptance tests, support documentation and measurable outcome.

## Release gate

A release is ready only when the approved scope meets acceptance criteria, relevant tests/builds pass, no unresolved critical/high security issue is silently ignored, payment claims are verified in sandbox/staging, migration/rollback is understood, backups/monitoring are active, legal/marketing claims are accurate and the handoff is current. “Perfect” is not measurable; evidence-backed acceptance criteria are.

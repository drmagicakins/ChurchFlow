# ChurchFlow — Documentation-Led Product, Engineering & Readiness Audit

**Audit date:** 24 October 2026  
**Evidence reviewed:** supplied `ChurchFlow.zip`, especially `README.md`, `AGENTS.md`, `SESSION-HANDOFF.md`, `church-saas-architecture.md`, `SECURITY.md`, `DEPLOYMENT.md`, `HOW-TO-RUN.md`, `LANDING-PAGE-BUILD-LOG.md`, `composer.json`, CI workflow paths, and repository inventory.  
**Audit type:** static/documentation-led review. This document does **not** claim that the full test suite, production integrations, security scans, or live deployment were executed during this review. Documentation claims must be verified against the actual code and a fresh test run before being treated as current facts.

## 1. Executive assessment

ChurchFlow has a substantial Laravel foundation and thoughtful engineering decisions documented: tenant scoping designed to fail closed, policy-level ownership checks, financial ledgers rather than mutable balances, approval workflows, payment webhook idempotency, a separately billed SMS wallet, onboarding/tour foundations, health checks, CSP/security headers, and a strict preservation policy in `AGENTS.md`.

However, **a broad set of documented gaps means the project should not yet be described as fully production-ready or complete**. The most consequential gap is that the subscription/billing architecture has real seams and tests, but the documentation explicitly says Paystack/Flutterwave network implementations still throw. Plan storage/feature limits are not enforced, and the church setup flow still records completion without collecting the church name. These are not cosmetic tasks: they affect payment collection, entitlements, customer onboarding, and commercial readiness.

The existing `SESSION-HANDOFF.md` is detailed but narrowly focused on the parish-pastor permission feature. It is not a durable whole-product roadmap or a reliable project-wide continuation record. It also says “Nothing has been committed,” while the archive contains a Git repository snapshot; this may simply be stale handoff context, but CodeGPT must verify Git status and recent commits instead of assuming either state.

The safest next step is **read-only discovery and documentation design first**, then an approved, sequenced implementation plan. Do not ask CodeGPT to “build everything perfectly” as one giant code change. Ask it to map, specify, validate, and implement one approved work package at a time with regression protection and handoff updates.

## 2. Important archive hygiene and security warning

The supplied ZIP contains a root `.env`, a `.git` directory, `vendor/`, `node_modules/`, a legacy application, and many root-level QA scripts, screenshots, logs, and temporary probe files. This creates unnecessary size and may expose credentials or private repository history.

- **Do not redistribute or upload this ZIP publicly as-is.**
- Inspect `.env` locally without pasting secrets into chats, prompts, tickets, or logs. If any live credentials were included in the archive or shared somewhere untrusted, rotate them: database credentials, `APP_KEY` where operationally appropriate, payment keys/webhook secrets, SMS/email provider keys, and any other tokens. Coordinate `APP_KEY` rotation carefully because it can invalidate encrypted data and sessions.
- Keep `.env` out of Git and share only `.env.example` with blank/safe placeholders. Verify `.gitignore` and `git ls-files .env` locally.
- Do not automatically delete QA files or database rows based only on the old handoff. Inventory them, confirm whether they are tracked and whether the database is disposable, back up if appropriate, then remove only with explicit approval.
- Build a clean source archive using `.gitignore`/an allow-list. Normally exclude `.env`, `.git`, `vendor`, `node_modules`, caches, logs, screenshots, database dumps, and temporary QA artifacts unless there is a specific reason to include them.

## 3. What appears to exist already — verify in code

The README describes these foundations; CodeGPT should confirm each claim against the implementation, route map, migrations, policies, and tests:

- Laravel 12 / PHP 8.2+ application, Blade UI, Vite, Alpine interactions, and a shared UI/design system.
- Church tenant model, `TenantScope` / `BelongsToTenant`, organizational units, RBAC, audit logging, and platform-admin exception path.
- Members, families, departments/groups, custom member fields, CSV import/export.
- Events, registrations/waitlists, attendance, calendar aggregation, announcements, tasks/reminders.
- Finance accounts/transactions, approvals, transfers/voiding, loans and payments, subvention rules/calculations.
- Pastoral care with restrictive visibility and append-only notes.
- Email queueing, in-app notifications, SMS campaigns and a separately charged SMS wallet.
- Checkout/subscription/trial foundations, billing-cycle/dunning services, platform settings, plan records, and a platform-admin area.
- Onboarding checklist, tours, feature flags, analytics hooks, marketing pages, pricing pages, help/resource/legal page shells, health/status page, reusable data-grid and styled forms.
- Security headers/CSP, upload restrictions, rate limits on selected endpoints, and tenant-isolation tests.
- Strict code-preservation rules in root `AGENTS.md`.

**Important:** a feature appearing in a README, migration, menu, model, or controller does not prove the end-to-end user journey works. Verify every feature from UI → route → authorization → validation → service → persistence → queue/provider → feedback/error state → tests.

## 4. Confirmed/documented gaps to prioritize

These are explicitly mentioned in the supplied documentation and must be checked against the current branch before work begins:

### P0 — Commercial and operational blockers

1. **Real payment gateway implementations:** README says Paystack/Flutterwave network code still throws. Implement and test a production-grade provider adapter, signature verification, server-side transaction verification, idempotent webhooks, timeout/retry policy, reconciliation, and sandbox-to-live release checklist. Do not mark billing “done” until an actual sandbox end-to-end payment has been verified.
2. **Plan entitlements are not enforced:** `plans` stores storage/feature limits but the README says these are unused. Build a central entitlement/limit service and enforce it server-side on every relevant action; hiding UI is not authorization.
3. **Church setup name is not collected:** the setup checklist apparently marks completion without saving the actual church name. Implement validation, persistence, safe edit rules, progress recovery, and tests.
4. **Production configuration and deployment proof:** confirm environment variables, queues, scheduler, database backups, off-site backups, restore drills, TLS, error reporting, logging/alerts, and operational ownership. `DEPLOYMENT.md` itself describes the Docker setup as a reference/dev setup and the local backup command as minimal.
5. **Security automation:** `SECURITY.md` says there is no dependency/vulnerability scan in CI, no built-in 2FA, no documented WAF/DDoS guidance, and rate limiting is selective. Prioritize automated dependency audit and secret scanning; assess 2FA and broader throttling based on threat model and compatibility.
6. **Session handoff is not project-wide:** existing `SESSION-HANDOFF.md` is feature-specific and lists temporary QA artefacts and QA database records for cleanup. Turn it into a whole-project continuation ledger while preserving its useful feature history in an archived section or separate dated feature handoff.

### P1 — Billing completeness and customer experience

7. **Invoice PDFs** are explicitly not built. Add immutable invoice snapshots, tax/currency handling, downloadable PDF, accessible print view, email delivery, and tests.
8. **Trial/renewal/dunning timestamp model:** documentation says `updated_at` is used as a proxy for time in a billing status. Introduce explicit status-transition timestamps if code review confirms the risk, so unrelated edits cannot reset grace/dunning timing.
9. **Feature flags vs. paid entitlements:** distinguish internal rollout flags from commercial entitlements. A rollout flag must not become the only billing authorization control.
10. **Pricing/plan truthfulness:** ensure public prices, billing interval, taxes, trial terms, add-on prices, and admin-configured amounts all match the backend configuration and invoice records. Avoid duplicate hard-coded prices.
11. **Contact/support forms and content:** landing-page documentation says there is no real contact form, ticket form, blog content, or video demo. Either implement functioning, protected workflows or keep honest “not configured” states; never ship forms that silently discard submissions or fabricated testimonials.
12. **Legal review:** privacy/terms pages are documented as drafts. Have qualified counsel review applicable jurisdictions and actual data flows before launch; do not remove the draft notice until review is complete.

### P1 — Product and UX quality

13. **Audit every route and role in a real browser:** public marketing pages, guest auth, church owner, staff with restricted permissions, parish pastor, platform admin, expired/past-due subscription, trial, add-on user, and empty/error states.
14. **Responsive/accessibility audit:** keyboard-only operation, visible focus, semantic landmarks/headings, form labels and errors, contrast, reduced motion, dialog focus management, mobile navigation, zoom/reflow, and screen-reader names.
15. **Consistent UX states:** loading, success, validation errors, no data, filtered-empty, permission denied, offline/provider failure, rate-limited, expired subscription, and retryable queue/provider failures.
16. **Data-grid QA:** validate search, sort, filters, pagination, reset, empty states, query persistence, accessibility, tenant isolation, and correct server-side filtering. Confirm it does not falsely imply that only currently rendered rows are the full dataset.
17. **Dashboard/report correctness:** verify chart values against underlying ledgers, date ranges/time zones, currency formatting, permissions, branch/parish filters, exports, and no leakage across tenants.
18. **Performance:** profile actual hot paths and N+1 queries, paginate large datasets, queue expensive work, review indexes/query plans, and define target response times. Avoid speculative caching before measuring.
19. **SEO and marketing verification:** unique titles/descriptions, canonical URLs, robots/sitemap, Open Graph assets, schema markup only where accurate, image optimization, heading hierarchy, 404/redirect handling, indexability, and noindex for authenticated/private pages. Confirm social/contact links and analytics consent/privacy behavior.
20. **Trust and conversion:** real screenshots/demo data clearly labelled, verified testimonials only, transparent plan limits/add-ons, onboarding completion metrics, demo/contact conversion tracking with privacy safeguards, and clear support/response expectations.

### P2 — Scale and enterprise readiness

21. **Multi-branch/parish hierarchy:** test branch-scoped roles and permissions deeply, including users who can view multiple units, inheritance/overrides, transfer between branches, and exports.
22. **Backups/disaster recovery:** define RPO/RTO, off-site encryption, retention, restore test cadence, tenant-level export, and deletion/retention workflows.
23. **Observability:** structured logs with request/correlation IDs, safe redaction, payment/webhook event traceability, queue lag/dead-letter visibility, uptime alerting, and actionable incident history.
24. **Privacy/data lifecycle:** retention schedules, consent and communication preferences, member export/correction/deletion workflows, secure data export, access logging for sensitive pastoral records, and legal basis review.
25. **Automated QA coverage:** unit tests for business calculations; feature tests for role/tenant gates; browser E2E tests for critical journeys; regression tests for CSP/Alpine, confirm dialogs, payment/webhook, onboarding, and all public routes.

## 5. Product architecture: custom-priced features and add-ons

ChurchFlow should support platform-admin-priced add-ons without creating a separate code branch or custom deployment for each church. Build a **generic, tenant-aware entitlement and add-on system**; then ship specific modules as catalog entries.

### Required conceptual distinction

- **Plan feature/limit:** included in Starter/Growth/Denomination/Enterprise (or whatever the actual plan catalog defines).
- **Paid add-on:** separately purchased recurring or one-time entitlement for one church; price and billing cadence controlled by platform admin.
- **Usage-based charge:** measured consumption such as SMS, storage, WhatsApp/provider messages, or premium e-signature usage. Requires usage ledger and billing policy.
- **Custom contract/override:** negotiated price, quota, feature access, start/end date, and notes for one tenant, with audit trail and explicit approval.
- **Feature flag:** release/rollout switch for internal deployment; not a substitute for authorization or paid entitlement.

### Recommended data model (adapt after inspecting existing schema; do not duplicate tables)

- `feature_catalog`: stable key, name, description, category, status, risk level, entitlement type, dependencies, configuration schema/version.
- `plan_features` or existing plan JSON/relationship: plan ID, feature key, enabled flag, quota/limit and configuration. Prefer normalized relationships if querying/admin reporting demands them; avoid a second competing source of truth.
- `addons`: product key, public/internal name, description, active status, billing type (`recurring`, `one_time`, `usage_based`), default billing interval, tax/currency metadata, dependency rules.
- `addon_prices` / price versions: amount in minor currency units, currency, interval, effective-from/to, version/status. Never mutate the price snapshot on an already issued invoice.
- `church_addons` (tenant subscription/entitlement grant): church, add-on, status, start/end, quantity, granted-by, approved-by, source/order/invoice, config override, timestamps.
- `addon_orders` / invoices / invoice lines: quote/checkout, price snapshot, payment state, idempotency key and provider reference; reuse existing checkout/invoice tables where suitable.
- `entitlement_overrides`: tenant-scoped override, value/quota, reason, expiry, author/approver, audit metadata. Do not silently overwrite plan values.
- `usage_events` / usage ledger: idempotent event key, tenant, feature, units, source, timestamp, rated price reference. For money-related usage, ledger entries should be append-only or corrected through reversal entries.

**Before adding schema:** inspect the existing `plans`, `subscriptions`, `invoices`, `checkouts`, feature flags, platform settings and billing services. Reuse or extend the current schema where sound. Write a migration plan and backward-compatible rollout steps first.

### Mandatory entitlement behavior

1. A single `EntitlementService`/policy API resolves effective access from base plan + active paid add-ons + approved custom overrides + applicable usage/quota limits.
2. Enforcement occurs on the backend for every read/write/export/job/API entry point, not only in Blade. UI navigation/buttons are also conditioned for clarity, but remain secondary.
3. Tenant context is always explicit and fail-closed. Platform-admin cross-tenant operations use a separate, audited path.
4. Feature dependencies are validated; removing an add-on must not corrupt or delete tenant data. Downgrade should preserve data and block new writes/creation where necessary, with clear read/export/grace behavior.
5. Payment confirmation is server-side and idempotent. Add-on access begins only after verified payment or explicit audited admin grant; failed/expired payment never grants access.
6. Recurring cancellation, renewal failure, refunds, chargebacks, expiry, grace period, proration, tax, currency, and plan changes have defined states and tests.
7. Platform admin can create/edit/deactivate catalog entries, set price/currency/interval, offer a tenant-specific quote, grant/revoke an add-on, set expiry/quota, and see audit/invoice/payment history. Historical invoice lines preserve the original price.
8. Church admins can discover available add-ons, see included vs paid status and price, request a quote or buy where enabled, view status/renewal/cancellation, and see why a feature is locked.
9. Add-on pricing is configurable without a code deployment. Do not let a church admin alter the platform's canonical price.
10. Avoid arbitrary executable code/config supplied by platform admins. Add-on configuration must be schema-validated, typed, permission-checked, versioned and audited.

## 6. Recommended future paid add-on catalog

Prioritize based on customer discovery and support capacity; these are candidates, not promises or commitments.

### High-value first wave

1. **Advanced reports & analytics** — custom date comparisons, branch/department dashboards, scheduled reports, export packs, giving/attendance trends.
2. **Advanced member communications** — segmentation, scheduled campaigns, campaign analytics, email templates; SMS/WhatsApp provider costs billed separately where applicable.
3. **Website and church app integration** — public events, giving links, member portal integration, API/webhooks, embeddable forms. Keep public website content separate from private member records.
4. **Online giving and donation campaigns** — campaign pages, receipts, recurring giving where legally/provider-supported, reconciliation and fees disclosed clearly.
5. **Advanced multi-branch management** — cross-branch reports, branch-level permissions, centralized configuration, delegated approvals.
6. **Document management** — private document vault, policy/version acknowledgement, secure downloads, retention rules and storage quotas.
7. **Event ticketing & registration premium** — paid tickets if supported, QR check-in, capacity, waitlist, reminders, attendee exports.
8. **Advanced attendance** — QR attendance, kiosk/check-in, service/session templates, volunteer rota integration; avoid covert biometric surveillance.

### Second wave

9. Volunteer/worker scheduling and rota management.
10. Facility/resource booking (rooms, equipment, calendars, conflict rules).
11. Pastoral follow-up workflows and appointment reminders with strict case-level privacy.
12. Child/youth ministry safeguarding workflows: restricted records, consent, authorized pickup, incident reporting; require specialist policy review and tight access logging.
13. Inventory/assets and purchase requisitions.
14. Payroll/stipends or staff HR integrations, subject to local tax/labor requirements.
15. Training/discipleship courses, member learning paths and completion certificates.
16. Live-stream/sermon/media library integrations with captions and rights metadata.
17. Multilingual UI/content and localized currency/time-zone support.
18. Accounting integrations and configurable chart-of-accounts/export mappings.
19. Premium onboarding, data migration and managed support packages (service SKUs, not entitlements alone).
20. Custom domain/white-label branding and branded member portal for higher plans.

### Enterprise/partner features

21. SSO/SAML/OIDC, SCIM provisioning and advanced audit exports.
22. Advanced API access, webhooks, integration secrets/rotation and usage quotas.
23. Data residency or dedicated database options only when operationally supportable.
24. Custom workflows/approval chains with constrained, validated configuration.
25. Enterprise service levels, named support and implementation services.

**Pricing advice:** let platform admin configure price per currency and billing interval, but establish internal pricing rules first: cost to serve, provider costs, support load, storage, payment fees, tax treatment, margin floor, and cancellation/refund policy. Do not hard-code suggested prices in the code or treat a UI-entered number as authoritative without validation and audit.

## 7. UI/UX, accessibility and visual design review checklist

CodeGPT must audit the existing visual language before proposing changes. Preserve the current design and all existing sections during the audit. Deliver annotated findings and wireframe/spec proposals first; do not replace whole pages as part of a “design review.”

- Establish token inventory: color, typography, spacing, radii, shadows, focus, breakpoints, motion, icon rules and component prefixes. Confirm the marketing `mk-*` and app `cf-*` systems do not collide.
- Inventory every public page and authenticated screen; map each page to user role, goal, primary action, empty/error/loading states and relevant permissions.
- Review desktop/tablet/mobile layouts, 320px/390px widths, keyboard use, zoom/reflow, contrast, reduced motion, labels, field errors, dialogs, tables and navigation.
- Confirm charts have text summaries/legends and are not color-only; forms preserve user input after validation failures; destructive actions explain consequences and use accessible confirmation.
- Keep data density appropriate for church admins; provide search/filter/pagination and bulk-action feedback without hiding the current selection or applied filters.
- Validate date/time zones, currency, number formats, translations, long church names, long member names, no-photo states and permission-based menu variations.
- Avoid fabricated reviews, unsupported “secure/compliant” claims, fake metrics, placeholder links, or forms that do not submit to a real backend.
- Produce a design audit, page inventory, prioritized UI backlog and acceptance criteria before making visual changes.

## 8. QA, cybersecurity, SEO and product-marketing definition of done

### QA / reliability
- Fresh install and clean database migrations/seed run.
- Full PHP test suite plus targeted tests; record actual counts and failures.
- Browser E2E of critical journeys across roles and subscription states.
- Route audit: no unexpected 404/500, broken links, console errors, failed assets, duplicate IDs or broken focus behavior.
- Tests prove they fail when the regression is introduced (especially tenant isolation, permissions, payment idempotency and CSP nonce).
- Queue/scheduler/provider failure behavior is observable and recoverable.

### Cybersecurity
- Threat model: tenant escape, privilege escalation, IDOR, pastoral data exposure, webhook spoof/replay, payment duplication, CSV formula injection, malicious upload, XSS/CSRF, session theft, SSRF/open redirects, rate abuse, secret leakage, backup exposure and dependency compromise.
- Check auth/session expiry and regeneration, optional 2FA, authorization on every object/action, tenant scope on exports/jobs/notifications/files, audit-log tamper resistance, safe log redaction, upload private storage and signed access, secure headers/CSP, TLS, cookies, CORS, API throttling and password reset flows.
- Run Composer security audit, frontend dependency audit, secret scan, static analysis and targeted dynamic tests where tooling supports it. Record exceptions with owner and due date.
- Never expose production secrets in prompts or reports. Do not use live credentials or production member data in QA.

### SEO / discoverability
- One accurate H1 per public page, unique title/description, canonical URL, clean status codes, robots/sitemap, noindex private pages, optimized image dimensions/alt text, social previews, accessible internal links, schema markup only for real entities and content, and mobile performance/Core Web Vitals checks.
- Confirm marketing copy, pricing, product screenshots and feature claims match shipped behavior. Keep a claim-to-evidence list for testimonials, security statements, uptime claims and integrations.

### Product marketing / conversion
- Validate ICP and top workflows with real churches before prioritizing add-ons.
- Define plan matrix with exact quotas and included features; show what is included, paid separately, usage-billed, or not yet available.
- Test payment-first onboarding, abandoned checkout recovery, first-value activation, setup checklist, help/support and cancellation/renewal transparency.
- Track funnel events with privacy safeguards and meaningful success metrics; do not add invasive tracking without review.

## 9. Documentation defects and process improvements

- `SESSION-HANDOFF.md` is a feature-specific handoff, not a whole-project state register. Keep its historical notes, but create a project-level handoff with current branch/commit, working tree status, test state, running services, active work package, known risks, next exact action and unresolved questions.
- README setup instructions include a “create a new Laravel project and overlay files” workflow that may not match the actual existing Git repository. Verify the correct clean-clone setup and make one canonical setup guide authoritative.
- README mentions Phase 1–13 in its title/current summary but includes Phase 14 later. Update headings/status only after verifying the code and current tests.
- README historical test counts differ by phase. Mark historical counts as historical and record the latest test run with date, command, environment and result.
- The project has `AGENTS.md`, but CodeGPT must be explicitly configured to load it; do not assume every extension/provider reads it automatically.
- No obvious project-wide `WORKFLOW.md` or `docs/WORKFLOW.md` appeared in the root inventory. Verify if one exists deeper; if not, create a canonical development workflow document.
- Keep architecture decision records (ADR) for tenancy, entitlements/add-ons, billing, data retention and any schema changes.
- Make the session handoff update mandatory after every completed work package and before stopping due to context/time limits.

## 10. Suggested delivery sequence

1. **Stage A — Read-only audit:** map repository, docs, routes, models, migrations, policies, services, jobs, views, tests, dependencies and external providers. Verify Git state. Produce a gap matrix with evidence and risk; no app code edits.
2. **Stage B — Baseline proof:** clean environment setup, migrate/seed, test suite, build, route inventory, browser smoke test and security scan. Record what could not be run.
3. **Stage C — Critical fixes:** payment integration, entitlement enforcement, church-name setup, production configuration/monitoring, dependency scanning and billing state correctness.
4. **Stage D — Customer-ready workflows:** invoices, support/contact intake, help content, plan transparency, add-on catalogue and add-on purchase/grant lifecycle.
5. **Stage E — UI/UX/accessibility/SEO:** prioritized page-by-page improvements with screenshot baselines and regression tests; no wholesale redesign without approval.
6. **Stage F — Data/privacy/operations:** backups/restore drill, privacy/data lifecycle, logs/alerts, performance and disaster recovery.
7. **Stage G — Release readiness:** full regression suite, threat review, sandbox billing proof, staging acceptance, rollback plan, legal review and go/no-go checklist.
8. **Stage H — Custom add-on roadmap:** prioritize with church interviews, cost model and support capacity; build the reusable entitlement platform before building many add-ons.

Every stage requires an explicit acceptance checklist and owner approval. “Perfect” is not a testable definition; use measurable acceptance criteria and record residual risks.

## 11. Scope of this audit

This report is a grounded first pass based on the supplied archive and documentation. It does not certify every source file or claim that all listed issues remain in the current working tree. The next CodeGPT run must verify findings against the exact branch and current code, label each item **Confirmed / Partially implemented / Not implemented / Needs runtime verification / Superseded**, and link each finding to exact file paths, classes, routes, tests or command output.

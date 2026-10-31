# CHURCHFLOW — PROJECT-WIDE SESSION HANDOFF

> This file is the continuation record for the whole product. Update it after every approved work package and before ending a session. Preserve older feature-specific handoffs as history. Do not state a test passed unless it was run and the result was observed.

## 1. Current verified repository state

- Last updated: YYYY-MM-DD HH:MM timezone
- Branch:
- HEAD commit:
- Working tree: clean / dirty (summarize changed paths without overwriting them)
- Runtime: PHP / Laravel / Node / npm / database versions
- Environment: local / staging / production (never include secrets)
- Active work package:
- Overall phase: audit / design / implementation / QA / release

## 2. Verified current state

For each statement, mark `Confirmed working`, `Partially implemented`, `Confirmed missing`, `Needs runtime verification`, or `Superseded/stale documentation` and link to files/tests.

- Authentication and account recovery:
- Tenant isolation and RBAC:
- Members/families/branches/departments:
- Events/attendance/tasks/announcements:
- Finance/approvals/loans/subvention:
- Pastoral care:
- Email/SMS/in-app notifications:
- Plans/subscriptions/trial/billing/payments:
- Plan entitlements and usage limits:
- Paid add-ons/custom contracts:
- Onboarding/setup wizard:
- Platform admin:
- Marketing/public pages/support/legal:
- Security/CI/dependency scans:
- Deployment/queues/scheduler/backups/monitoring:
- SEO/accessibility/performance:

## 3. Last verification record

| Check | Command / method | Date | Actual result | Notes |
|---|---|---|---|---|
| Git status | `git status --short --branch` | | | |
| PHP tests | `php artisan test` | | | |
| Frontend build | `npm run build` | | | |
| Dependency audit | `composer audit` / approved frontend equivalent | | | |
| Browser smoke / E2E | | | | |
| Tenant isolation | | | | |
| Billing sandbox | | | | |

Do not infer success from previous sessions. Rerun when safe and necessary.

## 4. Active task and exact scope

- User-approved task:
- Explicitly in scope:
- Explicitly out of scope:
- Expected files/areas to change:
- Existing behavior that must remain unchanged:
- Acceptance criteria:
- Regression tests required:
- Approval needed before expanding scope: yes/no

## 5. Decisions and constraints

Record architectural/product decisions, date, rationale and alternatives considered. Link ADRs where used.

- Tenant model and fail-closed behavior:
- Authorization rules:
- Billing/entitlement source of truth:
- UI/design-system constraints:
- Data retention/privacy constraints:
- Deployment/rollback constraints:

## 6. Known risks, defects and unresolved questions

| ID | Priority | Issue | Evidence | Impact | Next action | Status |
|---|---|---|---|---|---|---|

Do not remove an issue just because a related UI control exists. Close it only with evidence.

## 7. QA artefacts and test data

List temporary scripts, screenshots, logs, QA accounts and test records if they exist. State whether each is tracked, whether it is safe to remove, and who approved cleanup. Never delete these automatically. Never include passwords or secret values.

## 8. Last session's changes

- Files changed:
- Why each change was necessary:
- Tests/checks run:
- Known regressions or unverified behavior:
- Commit/push status (do not commit/push unless requested):

## 9. Next exact action — resume here

Write a concrete next instruction that can be executed without guessing. Include file paths, what to inspect first, the expected output, and what must not be changed.

Example: “Continue the read-only audit of subscription entitlements. Inspect `Plan`, `Subscription`, checkout activation and all controller/service authorization paths; compare each plan limit with write routes. Do not edit code. Add evidence-backed rows to `docs/PROJECT_AUDIT.md`, then update this handoff.”

## 10. Safe stopping procedure

Before stopping:
1. Review `git status --short` and `git diff --stat` / `git diff` for all session changes.
2. Ensure no secret values are in docs/logs/output.
3. Run only approved, safe checks; record exact results.
4. Update this handoff and the project audit/roadmap if progress changed.
5. Do not commit, push, reset, clean, delete QA data or run destructive commands unless explicitly authorized.
6. State the precise resume point and list any checks not run.

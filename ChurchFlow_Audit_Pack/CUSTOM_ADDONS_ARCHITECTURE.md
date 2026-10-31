# ChurchFlow — Custom Plans, Paid Add-ons & Tenant-Specific Entitlements

## Objective

Allow the platform administrator to sell, price, grant and manage optional features for individual churches without forking the application or deploying custom code per customer. The same deployed module must work safely for any eligible tenant after a verified purchase or authorized grant.

## Product rules

1. Plan inclusion, paid add-ons, usage-based billing, temporary promotions and negotiated contract overrides are distinct concepts.
2. Server-side entitlement enforcement is authoritative. UI visibility is a usability layer only.
3. Tenant access is fail-closed. An absent/unknown entitlement never means “allowed.”
4. Paid access starts only after a verified payment or an explicitly audited platform-admin grant.
5. Revoking access never silently deletes the tenant's data. Define read-only/export/grace behavior per feature.
6. Price changes create new effective price versions. Existing invoices and orders preserve the price snapshot they were issued with.
7. A platform-admin grant or override requires a reason, actor, timestamp, optional approver and optional expiry.
8. Feature flags control release rollout; they do not replace entitlements.
9. Do not allow arbitrary code or unsafe executable configuration in the admin UI.
10. Reuse existing ChurchFlow billing/plan tables after a schema audit; do not create duplicate sources of truth.

## Core entities (conceptual; map to existing schema before implementation)

- **Feature catalog:** stable key, display name, description, category, lifecycle status, entitlement kind, dependencies, risk classification, config schema/version.
- **Plan-feature mapping:** what each subscription plan includes, with quotas/configuration.
- **Add-on product:** catalog entry and commercial type (`recurring`, `one_time`, `usage_based`, or `service`).
- **Price version:** amount in minor units, currency, billing interval, tax treatment, effective dates, active state and immutable reference.
- **Tenant add-on grant:** church, add-on, state, quantity, configuration, start/end, purchase/invoice reference, granted by, approved by and audit timestamps.
- **Checkout/order/invoice line:** price snapshot, status, idempotency key, provider transaction reference and payment lifecycle.
- **Entitlement override:** tenant, feature/quota/config value, reason, expiry, actor/approver and audit trail.
- **Usage ledger:** append-only usage events with unique event key, tenant, feature, units, source and rating/price reference.

These are logical responsibilities, not a command to create all tables. Reuse suitable existing tables and relationships where correct.

## Entitlement resolution

A central service should resolve an effective entitlement for a specific tenant and feature by considering:

1. Tenant and subscription state.
2. Plan-included feature and plan quota.
3. Active paid add-on grants.
4. Approved, non-expired tenant-specific overrides.
5. Dependencies and exclusions.
6. Usage/quota state and any grace period.
7. Global release flag, where appropriate.

The result should be structured (e.g. allowed, reason, limit, remaining usage, source, expires_at) so UI can explain locked features. The implementation must never accept a church ID supplied by a client as proof of tenant identity.

## Enforcement points

- Web controllers and form requests.
- Policies/gates and service methods.
- API endpoints and integrations.
- Queue jobs and scheduled tasks.
- Exports, reports and downloadable files.
- Notifications/campaign sends.
- Storage upload/download and high-cost provider calls.
- Bulk actions and background imports.

Every high-risk path needs tests for tenant A vs tenant B, allowed vs denied roles, active vs expired add-on, payment pending/failed/confirmed, quota boundary and concurrent requests where relevant.

## Platform-admin workflows

- Create/edit/deactivate an add-on; validate stable key uniqueness and dependency graph.
- Configure one or more price versions, currency and recurring interval.
- Create a tenant-specific quote/discount with validity period and terms.
- Review purchase/payment status and safely retry reconciliation.
- Grant/revoke/expire a manual entitlement with reason and audit log.
- Configure quota/quantity and expiry for a specific tenant.
- See tenant entitlement summary, effective source, usage, invoices and renewal state.
- Preview customer-facing presentation before publishing.
- Export an audit trail without exposing other tenants' sensitive data unnecessarily.

## Church-admin workflows

- Browse add-ons with clear labels: included in plan, paid add-on, usage-billed, unavailable, or coming soon.
- See price, currency, recurring interval, tax/fee notes, prerequisites and what happens on cancellation.
- Purchase through the existing verified checkout flow, or request a quote if the platform config requires manual approval.
- See pending/active/past-due/cancelled/expired state, renewal date, invoice history and next action.
- Understand locked features and quota usage without being shown unauthorized data.

## Lifecycle and failure cases

Specify and test: checkout abandoned, payment declined, webhook duplicated/out of order, provider timeout, refund, chargeback, renewal success/failure, grace period, manual admin grant, manual revoke, expiry, upgrade/downgrade, dependency removed, quota reached, add-on deactivated for new sales, and tenant cancellation. Webhooks must be signature-verified, server-side transaction verified and idempotent. Use reconciliation tooling for uncertain provider states.

## UI requirements

- Platform-admin catalogue list/detail/edit with filters, status, price versions, dependency warnings and audit history.
- Church-facing add-on marketplace with clear inclusion and billing terms.
- Entitlement status/usage view for church owners.
- Locked-feature explanation and upgrade/request-quote action.
- Accessible forms, clear validation, confirmation for cancellation/revocation, and responsive layouts.
- Never make pricing changes silently or retroactively alter an existing invoice.

## Implementation sequence

1. Audit current schema/services and map existing plan/feature flag/billing concepts.
2. Write an ADR and migration/backward compatibility plan.
3. Define entitlement contract and test matrix before building UI.
4. Implement central resolution service and backend authorization.
5. Implement catalog/admin controls and audited manual grants.
6. Connect purchase/checkout/webhook/invoice lifecycle.
7. Add church-facing catalogue, status, quota and locked states.
8. Add usage metering only for a specific costed feature; do not build a generic meter with no consumer.
9. Add one pilot add-on end-to-end, verify in sandbox/staging, then expand.
10. Document support, refunds, renewal, cancellation, reconciliation and incident procedures.

## Definition of done

- Effective entitlement has one documented source of truth.
- No endpoint/job/export can bypass it.
- Cross-tenant tests prove isolation.
- Payment and admin grant paths are idempotent and audited.
- Price snapshots are immutable on existing invoices.
- Expiry/revocation preserves data and follows documented access rules.
- All states are visible and understandable to authorized users.
- Tests, browser E2E, migration rollback strategy, docs and session handoff are updated.

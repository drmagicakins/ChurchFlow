# ChurchFlow — Custom Plans, Paid Add-ons & Tenant Entitlements

**Type:** Architecture design. **No code has been written.** Implementation is Phase 7 of `PRODUCT_ROADMAP.md`.
**Prerequisite:** read the existing billing code before proposing schema — this document maps to what is
already there and **must not create a competing source of truth**.

---

## 1. What already exists (verified)

| Concept            | Current implementation                                                                                            |
| ------------------ | ----------------------------------------------------------------------------------------------------------------- |
| Plan catalogue     | `plans` table, seeded from `config/billing.php`; `PlanCatalog` resolves display, DB row wins                      |
| Plan limits        | `plans.max_members / max_branches / max_admins`; enforced by `PlanLimitService` on model `creating` hooks         |
| Subscription       | `subscriptions.status` is the source of truth; `churches.status` is a cache written only by `SubscriptionService` |
| Trial              | `TrialService`, `TRIAL_DAYS = 14`, `trial_ends_at`, `pending_plan_id`                                             |
| Invoices           | `invoices` with immutable tax snapshot and UNIQUE `provider_reference`                                            |
| Checkout           | `checkouts.purpose` = `subscription` \| `sms_credits`; one gateway abstraction, one webhook                       |
| Flags              | `FeatureFlagService` (global + per-church override) — **rollout**, not entitlement                                |
| Pricing at runtime | `platform_settings` via `PlatformSettingsService`                                                                 |

**The gap:** `PlanLimitService::limitFor()` hardcodes three resources and returns `null` (unlimited) for
everything else. `PlanFeatureService` exists but nothing enforces it. Feature flags are rollout controls and
must never become the paid-authorisation control.

---

## 2. The four concepts that must stay separate

Mixing these is the single most common way an add-on system becomes unauditable. ChurchFlow must name them
distinctly, in schema and in UI copy.

| Concept               | Question it answers                    | Where it lives                                           |
| --------------------- | -------------------------------------- | -------------------------------------------------------- |
| **Plan entitlement**  | What does this plan include?           | `plans` + `plan_features`                                |
| **Paid add-on**       | What did this tenant buy on top?       | `addon_purchases` (recurring/one-time)                   |
| **Usage entitlement** | How much of a metered service is left? | existing `sms_wallets` pattern; extend, do not duplicate |
| **Rollout flag**      | Is this feature enabled for beta?      | existing `FeatureFlagService` — never a billing control  |

A fifth, deliberately narrow concept: **tenant override** — an audited, expiring grant for a specific church
(enterprise deal). It must require a reason, an expiry and an approver.

---

## 3. Proposed schema (adapt after inspecting current migrations)

Additive only. No existing table is dropped, renamed or rewritten.

```
plan_features            (plan_id, feature_key, limit_value nullable, reset_period nullable)
  UNIQUE(plan_id, feature_key)
  -- limit_value NULL = unlimited; a numeric value = cap; reset_period for metered use

addon_catalog            (key, name, description, billing_interval, is_active)
addon_price_versions     (addon_catalog_id, amount, currency, effective_from)   -- versioned, never edited
addon_purchases          (church_id, addon_catalog_id, price_version_id, status,
                          starts_at, ends_at, provider_reference nullable, purchased_by)
  UNIQUE(provider_reference)                    -- same idempotency guarantee as invoices
tenant_entitlement_overrides
                         (church_id, feature_key, limit_value nullable, reason, granted_by,
                          approved_by nullable, expires_at nullable, revoked_at nullable)
```

**Rules**

- `addon_price_versions` is append-only. An existing purchase keeps pointing at the price it was bought at —
  the same principle already used for invoice tax snapshots.
- `status` mirrors the subscription vocabulary (`pending/active/past_due/cancelled/expired`) so the UI and
  dunning code do not learn a second language.
- No `church_id`-less pivot without parent scoping: anything one level removed scopes through its parent with
  a `BelongsToTenant`-style trait, matching the existing convention for `EventRegistration`, `LoanPayment`, etc.

---

## 4. Entitlement resolution (one function, one answer)

```
entitlement(church, feature_key):
  1. if a non-revoked, non-expired override exists        -> its limit
  2. else if an active add-on grants this feature         -> the add-on's limit
  3. else if the plan includes the feature                -> the plan's limit
  4. else                                                  -> denied
```

**Properties that matter**

- **Deterministic:** the same inputs always produce the same answer; no ordering by "latest row" heuristics.
- **Fail-closed:** an unknown `feature_key` denies (matching `FeatureFlagService`'s existing convention of
  returning `false` for an unknown key — the same rule, applied to money).
- **Single call site for reads:** one `EntitlementService`. Controllers, jobs, exports and uploads must not
  each re-implement the lookup.
- **Cached per request, not per deploy:** resolve once per request and memoise; invalidate on purchase,
  cancellation or override change.

---

## 5. Enforcement points (UI hiding is not authorization)

Every one of these must call the resolver **server-side**:

| Surface                       | Example                                                                |
| ----------------------------- | ---------------------------------------------------------------------- |
| Web route / controller action | A gated report returns 403, not an empty page                          |
| API route                     | Same middleware, same service                                          |
| Queued job                    | A campaign for a gated channel refuses **before** reserving credits    |
| CSV/PDF export                | Export of a gated dataset refused                                      |
| File upload                   | Storage quota checked pre-write, using the same `assertCanAdd()` shape |
| Provider call                 | SMS/email send checks entitlement before dispatch                      |

**Definition of done for any new gated feature:** a test that posts directly to the route with a tenant that
lacks the entitlement and asserts a refusal. Not a test that the button is hidden.

---

## 6. Lifecycle

```
catalogue (active price version)
   -> church purchases  -> status pending
   -> verified payment  -> status active, starts_at/ends_at set, audited
   -> renewal           -> existing RenewalAndDunningService vocabulary
   -> non-payment       -> past_due -> grace -> expired  (never delete data)
   -> cancellation      -> stays active until period end, then expired
   -> downgrade         -> entitlement drops at period end; data retained, read-only where sensible
```

**Revocation and downgrade rules**

- Never delete tenant data on downgrade. The existing subscription model already states "restriction only,
  nothing is ever deleted" — apply the identical rule.
- A revoked add-on stops **new** use immediately; it must not silently destroy existing records.
- Overrides expire on their own `expires_at`; an expired override must be visible in the admin UI as expired,
  not quietly dropped.

---

## 7. Platform-admin workflows

1. Create/disable catalogue item; add a **new price version** (never edit an existing one).
2. Grant an override with reason, expiry and approver; list all active overrides with remaining time.
3. View a tenant's full entitlement picture: plan, active add-ons, overrides, usage.
4. Revoke an add-on or override — audited, with a reason.

**Audit requirement:** every grant, revocation and price change goes through the existing `Auditable`
trait/observer so it lands in `audit_logs` with before/after values. A billing change that is not auditable is
a dispute waiting to happen.

---

## 8. Church-admin UI requirements

- **Add-ons page:** per item — what it does, price, interval, status, next renewal, cancel.
- **Downgrade warning:** state plainly what will stop working and what data is kept. "You will lose access to
  X; your data is kept and returns if you re-subscribe."
- **Usage meters** for metered features (SMS already models this correctly — reuse it).
- **Honest unavailable state:** when an add-on is not purchasable (payment gateway unavailable), say so; do not
  show a buy button that fails.

---

## 9. Candidate catalogue (roadmap only — not a build list)

**High-value first wave:** advanced analytics/reports · extra SMS bundles · document vault · multi-branch
controls · event ticketing with QR check-in.
**Second wave:** volunteer rota · room/facility booking · safeguarding workflows · inventory/assets ·
accounting integration.
**Enterprise:** white-label & custom domain · SSO · public API/webhooks · dedicated support SLA.

**Every item needs, before it is priced:** evidence of demand, a cost-to-serve estimate, a privacy assessment
(member data), and an agreed support model. Pricing an unbuilt module is how a roadmap becomes a promise.

---

## 10. Definition of done for Phase 7

1. One catalogue item is purchasable end-to-end through the **real** gateway (Phase 1 prerequisite).
2. Entitlement granted only after a verified payment or an audited override.
3. Server-side refusal proven by test on at least: a controller action, a job and an export.
4. Existing plan limits behave exactly as before — no regression in `BillingEnforcementTest`.
5. Revocation and expiry never delete tenant data.
6. Every grant/revoke/price change appears in `audit_logs`.
7. Historical purchases still show the price version they were bought at.

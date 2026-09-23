# ChurchFlow

### The Operating Platform for Modern Churches

**ChurchFlow** is an all-in-one Church Management SaaS platform for churches,
ministries, denominations, and multi-branch organizations — managing people,
finances, activities, communication, and daily operations from one secure,
multi-tenant workspace.

See [`church-saas-architecture.md`](church-saas-architecture.md) for the full
technical and product architecture.

---

## Current state: Phase 1 (Foundation) — implemented

The scaffolding in this repository implements the Phase 1 foundation described in
the architecture document (§H): Laravel 12 skeleton, multi-tenancy, unified auth,
RBAC, the organizational-hierarchy tables, and the audit log.

### What's in place

| Area | Implementation |
|---|---|
| **Tenancy** | `churches` table + a global `TenantScope` applied automatically via the `BelongsToTenant` trait, so tenant models never need a hand-written `where('church_id', ...)`. Fails **closed**: no bound tenant means no rows. |
| **Tenant resolution** | `IdentifyTenant` middleware resolves the current church from the authenticated user and binds it as `tenant.church_id`. |
| **Users & RBAC** | One unified `users` table (replacing the legacy three-table model), with `roles` / `permissions` / `permission_role` / `role_user`. Roles are tenant-scoped; permission *names* are a fixed platform catalog seeded by `RolePermissionSeeder`. |
| **Platform admins** | `is_platform_admin` users have no church context; `PlatformAdminOnly` binds `tenant.disabled = true` so platform routes can see across tenants deliberately. |
| **Organizational hierarchy** | Per-tenant configurable `unit_types` ("Province", "Diocese", "Region", …) + a self-referencing `organizational_units` tree — denomination terminology is data, not schema. |
| **Audit log** | `audit_logs` table populated by the `Auditable` trait + `AuditableObserver` (create/update/delete, before/after JSON, secrets stripped). |
| **Auth** | Register/login/logout with Laravel's built-in bcrypt hashing, CSRF, and login rate limiting. No plaintext passwords, no raw SQL. |
| **Policies** | `BaseTenantPolicy` (defense-in-depth church ownership check) + `MemberPolicy` as the Phase 1 example. |

### The test that matters most

`tests/Feature/TenantIsolationTest.php` asserts that a user in Church A cannot see,
query, or update a member belonging to Church B — **even by guessing the ID directly**
— and that a request with no bound tenant returns *zero* rows rather than all rows.

---

## Local setup

Requirements: PHP 8.2+ (with `pdo_sqlite`, `mbstring`, `openssl`, `curl`, `zip`),
Composer, and Node/npm only if you intend to build front-end assets.

```bash
composer install
cp .env.example .env
php artisan key:generate

# SQLite is the default — just create the file and migrate
php artisan migrate --seed

php artisan serve
```

The database is SQLite by default (`database/database.sqlite`) for zero-config local
development and tests. Point `DB_CONNECTION` at MySQL/MariaDB in `.env` when needed.

Production parity for MySQL:

```bash
php artisan migrate --seed
```

### Running the tests

```bash
php artisan test
```

The suite runs against an in-memory SQLite database (see `phpunit.xml`), so it never
touches your development data.

---

## Roadmap

| Phase | Scope | Status |
|---|---|---|
| 0 | Legacy analysis + target architecture | ✅ (architecture doc) |
| 1 | Foundation: auth, tenancy, RBAC, audit | ✅ implemented |
| 2 | Church & People: org structure, members, families, departments | next |
| 3 | Activities: events, calendar, attendance | planned |
| 4 | Finance: accounts, income, expenses, budgets | planned |
| 5 | Subvention rule engine + migrated RCCG data | planned |
| 6 | Pastoral Care | planned |
| 7 | Communication: email (included) / SMS wallet | planned |
| 8 | SaaS billing: payment-gated registration | planned |
| 9 | Onboarding & product tours | planned |
| 10 | Hardening & deployment | planned |

## License

The Laravel framework is open-sourced software licensed under the
[MIT license](https://opensource.org/licenses/MIT).

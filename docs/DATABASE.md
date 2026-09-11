# DATABASE.md

Engine: MySQL 8+/9 · InnoDB (enforced per-connection — WAMP ships MyISAM default; see `AppServiceProvider`) · utf8mb4.

## Phase 01 tables

| Table | Purpose | Notes |
|---|---|---|
| users | accounts | status (active/suspended), phone, last_login_at, soft deletes |
| tenants | businesses | uuid, slug (unique), business_type, status, currency, soft deletes |
| tenant_users | membership | unique (tenant_id, user_id), status, joined_at |
| roles | role definitions | tenant_id NULL = platform role; unique slug per tenant |
| permissions | permission catalogue | name unique, group |
| role_permissions | role ↔ permission | unique pair |
| user_roles | user ↔ role assignments | tenant-scoped; tenant_id NULL = platform-wide |
| audit_logs | security trail | actor, subject (morph), ip, user_agent, old/new JSON |

Framework tables (`cache`, `jobs`, `sessions`, …) follow the Laravel defaults; sessions and cache are database-backed in dev.

## Conventions

- BIGINT UNSIGNED ids, timestamps everywhere, soft deletes where meaningful.
- Foreign keys with explicit `cascadeOnDelete` / `nullOnDelete`.
- Composite uniques for pivots; indexes on status/tenant_id columns.

## Seeding

`php artisan db:seed` → PermissionSeeder (catalogue) + RoleSeeder (platform `super_admin`).
Tenant roles (owner, manager, front_desk, staff) are provisioned per tenant at creation time via `RoleSeeder::ensureTenantRoles()` — the same call tenant-creation endpoints make.

## Provisioning the first Super Admin

```bash
php artisan superadmin:create "Name" "email@example.com" --password=…
# interactive runs may omit --password (hidden prompt)
```

Never hard-code admin credentials anywhere.

# ARCHITECTURE.md

## Stack

PHP 8.3 · Laravel 13 · MySQL 9 (InnoDB, utf8mb4) · Blade · Livewire 4 · Alpine.js · Tailwind CSS 3 · Vite.

## Layers

```text
app/
  Console/Commands/     superadmin:create
  Http/
    Controllers/        Tenant area, Admin (platform) area, Auth (Breeze)
    Livewire/           TeamManager (team management UI)
    Middleware/         SetTenantContext, EnsureSuperAdmin
    Policies/           TenantPolicy
  Models/
    Concerns/BelongsToTenant   tenant isolation scope + create guard
    Tenant, TenantUser, Role, Permission, AuditLog, User
  Support/
    TenantContext       per-request singleton: the active tenant
    AuditLogger         central audit writing service
    PermissionRegistry  canonical permission catalogue (seeds + cache)
resources/views/
  layouts/   app (dashboard shell), guest (auth/marketing), partials
  tenants/   business selection/creation/settings
  admin/     platform area (users, tenants)
  livewire/  team-manager
  errors/    403/404/419/500/503
```

## Request flow for tenant pages

```text
auth → SetTenantContext (middleware)
  reads session tenant_id
  validates: tenant exists, active, not deleted, user is active member
  initializes TenantContext (or bounces to /tenants)
→ controller/Livewire (policies check per-tenant permissions)
→ Eloquent with BelongsToTenant global scope (queries scoped to context)
```

## Multi-tenancy

- One row in `tenants` per business (hotel/resort/restaurant…).
- `tenant_users` pivot with status governs membership.
- `roles` and `user_roles` are tenant-scoped (`tenant_id`); platform `super_admin` role has `tenant_id = NULL`.
- `BelongsToTenant` denies ALL tenant-owned rows when no context is active — missing context is never global access.

## RBAC

- Permissions are global strings (`team.manage`, `tenants.update`, …) catalogued in `PermissionRegistry`.
- Roles bind permission sets; `user_roles` binds users to roles inside a tenant.
- `User::hasPermissionTo()` resolves permission → roles → assignments for the active tenant.
- Super Admins bypass tenant permission checks (Gate::before) and the `/admin` area is guarded by `EnsureSuperAdmin` middleware.

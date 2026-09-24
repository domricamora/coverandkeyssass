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

## Modules (Phase 02 pattern)

Each domain lives in `app/Modules/<Name>/` with its own Migrations, Models,
Controllers, Services, Policies, Views and a `routes.php` loaded inside the
`web` group by its ServiceProvider (registered in `bootstrap/providers.php`).

| Module | Namespace | Surface |
|---|---|---|
| Marketplace | `App\Modules\Marketplace` | Public, cross-tenant read-only browse of published stays/dining + wish list (Phase 03). |
| Property Management | `App\Modules\PropertyManagement` | Host-side CRUD for properties, room types, rooms, rates, availability, staff (Phase 04). Manages the rows the Marketplace publishes — extends, never duplicates. |

### Module gating

Tenant feature areas are gated by `module.active:<slug>` middleware
(`EnsureModuleActive` → `ModuleService::isEnabled`), e.g.
`auth + tenant.context + module.active:property` for the host area.

### Route binding caveat

Laravel's `SubstituteBindings` middleware is priority-hoisted and runs
**before** custom middleware such as `tenant.context`. Routes that must be
tenant-scoped therefore avoid implicit model binding and resolve parameters
manually inside controllers through scoped queries/parent relations
(Marketplace `publicQuery()`, Property Management `resolveProperty()` +
`$property->roomTypes()->findOrFail(...)`). Foreign rows resolve to a 404.

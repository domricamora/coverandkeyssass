# ARCHITECTURE.md — Hospitality OS

## Stack

PHP 8.3 · Laravel · MySQL 8/9 · Blade · Livewire · Alpine.js · Tailwind CSS · Redis (optional in dev) · Laravel Queue/Scheduler · REST API (`/api/v1`).

## Style

Modular monolith. Each business domain is a self-contained module under `app/Modules/<Module>`:

```
app/Modules/<Module>/
  Models/  Controllers/  Services/  Repositories/  Requests/
  Policies/  Events/  Listeners/  Jobs/  routes.php  Views/  Tests/  Migrations/
```

Modules are auto-discovered by a `ModuleServiceProvider` (registers routes, migrations, views, policies per module).

### Modules (target)

Marketplace, PropertyManagement, Booking, Restaurant, Ordering, Delivery, Payments, CRM, Accounting, Inventory, Staff, Housekeeping, POS, Marketing, Loyalty, Reviews, Messaging, Analytics, Subscriptions, Admin.

## Multi-tenancy

Single database, tenant-scoped rows. `tenant_id` on tenant-owned tables.

- `TenantContext` — resolves current tenant (subdomain/domain/user membership/admin switch).
- `TenantMiddleware` — sets context per request.
- `TenantScope` — global Eloquent scope auto-filtering by `tenant_id`.
- `BelongsToTenant` trait — auto-sets `tenant_id` on create + applies scope.
- `TenantPolicy` base — deny cross-tenant access.

Super Admin operates above tenants (no tenant scope) with explicit guards + audit logging.

## Users & RBAC

Actor types: Customer, Host (+ host staff roles), Admin, Super Admin.
Granular permissions (e.g. `properties.create`, `bookings.cancel`, `payments.refund`). Roles group permissions; users have roles (optionally tenant-scoped).

## Layers

Controller → FormRequest (validate) → Service (business logic/transactions) → Repository/Model → Events. Policies enforce authorization. No fat controllers.

## Key correctness invariants

- Booking: DB transaction + inventory lock; no double-booking; backend authoritative on availability.
- Payments: server-side verification, webhook signature check, idempotency keys.
- Tenant isolation enforced at query layer + policy layer + tests.

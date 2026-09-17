# CHANGELOG.md

## 2026-09-17 — Phase 03: Marketplace (v0.3.0)

Public, cross-tenant marketplace (verified: 70 tests / 181 assertions):

- Public surface: `/stays` landing, `/hotels` + `/search` with keyword/destination/price/guests/type filters and sorting, `/hotels/{location}` destination pages, `/property/{slug}` and `/restaurants` + `/restaurant/{slug}` detail pages.
- Tenant-owned listings with `draft → pending → published → suspended` lifecycle; published rows only on the public side (`publicQuery()` — the single cross-tenant read path).
- Polymorphic media (covers/galleries) with cover fallback; amenities for stays, cuisines for dining.
- Guest reviews (polymorphic, 1–5) with rating aggregates maintained by observers; wish list (favorites) with auth + personal scope and type whitelist.
- `PropertyPolicy` registered for host-side abilities ahead of Phase 04; additive morph map (`property`, `restaurant`).
- Marketing site: features / pricing / contact pages wired into the public layout.
- Reference seeders (`MarketplaceReferenceSeeder`) + demo data seeder; module docs completed (`docs/modules/marketplace.md`).

## 2026-09-11 — Phase 02: Module engine (v0.2.0)

(verified: 65 tests / 158 assertions)

- `modules`, `module_features`, `module_plans`, `tenant_modules` tables; `ModuleService` with dependency auto-enable, trials and guarded disable.
- Admin CRUD for modules and tenant-module assignments (enable/disable per tenant, permission-gated); tenant-facing module enable/disable page with timezone-aware trial countdown.
- `ModuleSeeder`: canonical module catalogue (core, property, booking, workforce, restaurant, inventory, finance, crm, analytics) with monthly plans + feature bullets.
- Branding refresh to **Cover & Keys** (bnb design system, Playfair Display) across marketing/auth/app shells; hospitality design system port.

## 2026-09-11 — Phase 01: Foundation (v0.1.0)

Implemented and verified (49 passing tests, 124 assertions):

- Laravel 13 project on PHP 8.3 + MySQL (InnoDB enforced per-connection; WAMP MyISAM default documented and fixed in dev `my.ini`).
- Auth (Breeze Blade): register, login, logout, password reset, email verification; suspended users blocked; last-login stamping.
- Multi-tenancy: tenants/tenant_users, TenantContext + SetTenantContext middleware, BelongsToTenant global scope (deny-by-default without context), TenantPolicy.
- RBAC: permissions catalogue (PermissionRegistry), per-tenant system roles (owner/manager/front_desk/staff), platform super_admin role, User permission resolution, granular policy checks.
- Team management: Livewire full-page TeamManager (add/change role/remove with owner uniqueness + audits).
- Super Admin area: platform dashboard, user suspend/activate (session revocation, self-protection), tenant create/suspend/activate/delete; `superadmin:create` command (validated, audited).
- Audit logging: registration, login/logout, tenant lifecycle, team changes, platform actions.
- UI: hospitality design system (Fraunces + Work Sans, evergreen/brass palette), responsive app/guest shells, business selector, dashboard, team, settings, admin screens, branded error pages, dark mode.
- Tests: Pest suite incl. tenant isolation + authorization coverage; permission-cache flush between tests.
- Docs: AI/ARCHITECTURE/DATABASE/API/SECURITY/DEPLOYMENT/MODULES/TESTING + this changelog + AI_PROGRESS.md; module docs stubbed (docs/modules).

Known limitations: Redis not available locally (database queue/cache drivers used); PayMongo integration pending (Phase 07); module engine pending (Phase 02).

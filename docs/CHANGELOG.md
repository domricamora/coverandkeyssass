# CHANGELOG.md

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

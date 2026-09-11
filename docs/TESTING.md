# TESTING.md

Runner: Pest (`php artisan test`) against the real MySQL database `hospitality_os_testing` (see phpunit.xml; DB credentials local dev).

## Suite (Phase 01: 49 tests, 124 assertions — passing)

- `tests/Feature/TenantFoundationTest` — registration assigns owner, seeds system roles, suspended login blocked, last-login stamping.
- `tests/Feature/TenantIsolationTest` — cross-tenant switch denied, forged session context rejected, no-context redirect, member-only dashboard, staff blocked from team page, owner allowed.
- `tests/Feature/TeamManagementTest` — Livewire team manager: add (creates account + role), duplicate rejected, role change, removal revokes role, staff cannot use it.
- `tests/Feature/SuperAdminTest` — /admin guarded, suspend/activate users (audit logged), platform admins protected from suspension, tenant suspension blocks context access, `superadmin:create` command incl. weak-password rejection.
- `tests/Unit/AuditTrailTest` — AuditLogger row shape, registration/login/logout events, tenant-creation event.
- Breeze auth/profile suites — adapted (soft-delete account deletion, tenant-area redirects).

## Conventions

- Every new module phase adds feature + authorization + tenant-isolation tests before its phase is marked complete.
- RefreshDatabase runs per test; permission caches are flushed in a global `beforeEach` (Pest.php) to avoid cross-test cache leakage.

## Before marking a phase complete

`php artisan test` green → update `AI_PROGRESS.md` (with real results) → `CHANGELOG.md` → commit.

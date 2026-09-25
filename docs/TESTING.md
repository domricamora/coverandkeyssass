# TESTING.md

Runner: Pest (`php artisan test`) against the real MySQL database `hospitality_os_testing` (see phpunit.xml; DB credentials local dev).

## Phase 34 — Coverage audit (2026-09-26): 297 tests, 1931 assertions, all passing

### Critical scenarios (master plan) and the tests that prove them

| Scenario | Test |
|---|---|
| Cannot double-book a room | `BookingEngineTest` "never sells the same room twice for overlapping nights" and "rejects a duplicate room-night at the database level" (the unique `(room_id, night)` backstop). An 8-process live race was also verified in Phase 05. |
| Cannot access another tenant | `TenantIsolationTest` (6 tests); every module file's "hides another business … behind a 404" test; `CrossTenantModulesTest` (loyalty, marketing); `ApiTest` (`X-Tenant` membership) |
| Cannot bypass permission | `BookingEngineTest` "lets front desk take bookings but not manage promotions, and keeps staff out"; permission tests in every module file; `ApiTest` (the housekeeper gets 403 on business bookings) |
| Duplicate webhook is safe | `PaymentsTest` "confirms the booking once PayMongo reports the payment paid, exactly once" and "rejects webhooks with a bad or stale signature" |
| Failed payment does not confirm the booking | `PaymentsTest` "records a failed payment and lets the guest try again" (the booking stays `pending`) and "does not trust the webhook body: an unpaid session confirms nothing" |
| Refund updates the booking correctly | `PaymentsTest` "refunds through PayMongo when the host marks a cancelled booking refunded" and "keeps the booking unrefunded when PayMongo refuses the refund" |
| Cancelled booking releases inventory | `BookingEngineTest` "releases the rooms when a booking is cancelled" and "lets an expired hold go back on sale" |
| Restaurant order status updates correctly | `OrderingTest` "walks pickup and delivery orders through their states"; `Unit/StateMachinesTest` (order paths and refund eligibility) |
| Delivery updates the order | `DeliveryTest` "needs a driver before dispatch and sets ETAs along the way" |

### Per-module matrix

Every business module has feature, authorization and tenant-isolation tests: Accounting, Billing, Booking, CRM, Customer, Delivery, Folio, Housekeeping, Inventory, Loyalty\*, Maintenance, Marketing\*, Marketplace (+ admin), Messaging, Ordering, Payments, POS, Property management, Restaurant management and reservations, Reviews, Room service, Workforce, and the API.

\* Loyalty and Marketing had no cross-tenant test until `CrossTenantModulesTest` added one in this phase.

Other categories:

| Category | Tests |
|---|---|
| Platform-level (no tenant data to isolate) | module engine, super admin and platform admin (guarded by `super.admin`) |
| Unit (no DB) | `Unit/StateMachinesTest`: booking and order state machines, the occupancy invariant, API money shape. `Unit/AuditTrailTest`. |
| Integration (real services end to end) | `DemoSeedTest`: the whole demo seed through the domain services, with clock travel, checks every booking state, folios, housekeeping and CRM, and a safe re-run. `PerformanceTest`: query budget, incremental CRM. |
| Cross-cutting | `SecurityBaselineTest`, `SeoTest`, `ApiTest` |

### Pitfalls learned (in-process tests)

- **Tokens across requests:**
  - `auth:sanctum` switches the default guard for the rest of the process, and guards cache the user.
  - When a test switches tokens, call `flushHeaders()` and `Auth::forgetGuards()`, as the `apiToken()` helper in `ApiTest` does.
- **Auto-increment IDs:** they never roll back with `RefreshDatabase`. Never derive business numbers from `max('id')`; this bug was found and fixed in `Employee::nextNumber()`.
- **Env-based config:** `config/*.php` reads env variables, so to test production behaviour, set `$_SERVER` / `$_ENV` instead of calling `detectEnvironment()`.

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

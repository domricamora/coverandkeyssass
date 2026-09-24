# CHANGELOG.md

## 2026-09-24 — Phase 06: Customer Portal (v0.6.0)

(verified: 124 tests / 425 assertions)

- New `App\Modules\Customer` module: `/account` dashboard (upcoming/past trips, wish-list/review/notification counts), trips list and detail, printable invoice, self-cancel before the check-in date, one verified review per property after check-out, and database notifications with mark-read.
- Cross-tenant customer reads go through `Booking::forCustomer()` only; tenant relations and writes run in `BookingService::asTenantOf()`.
- "My trips" link in both layout menus. Orders, payments, wallet, loyalty, coupons and messages are deferred to the phases that build them.
- 7 new Pest tests.

## 2026-09-24 — Phase 05: Booking Engine (v0.5.0)

- New `App\Modules\Booking` module: `bookings`, `booking_rooms`, `room_nights`, `promotions`.
- Double booking is prevented by a transaction plus `FOR UPDATE` lock on the room type's rooms, backed by a unique `(room_id, night)` index. Verified with a live race of 8 concurrent processes for one room: exactly 1 booking.
- 9-state machine (pending, held, confirmed, checked_in, checked_out, cancelled, no_show, refunded, completed) with date guards, audited transitions, inventory release on cancel/no-show/early check-out, and auto-cancel of expired holds.
- Pricing per night (rate periods, then weekend price, then base), minimum stay, promo codes (percent/fixed, window, min nights, max uses).
- Host desk: list with filters, manual / hold / walk-in / multi-room / group reservations with live availability, booking detail with actions, 14-day room calendar, promotions screen.
- Marketplace: "Request to book" widget on the property page (only when the business runs the booking module) → pending booking.
- `AvailabilityService::freeRoomIds()` now subtracts booked nights; `availableRoomCount()` uses it.
- Permissions `bookings.view|create|update`, `promotions.manage` (front desk gets bookings, not promotions). Host dashboard now shows real property / upcoming reservation counts.
- 19 new Pest tests.

## 2026-09-17 — Phase 04: Property Management (v0.4.0)

Host-side property management (verified: 98 tests / 320 assertions):

- New `App\Modules\PropertyManagement` module managing the Marketplace `Property` rows: profile CRUD (description, policies JSON, amenities sync, location, check-in/out, pricing), publish/unpublish with audit trail, draft-first lifecycle.
- Media: additive `kind` column on `media` (image/video) — photo + video URLs, single cover with promotion, gallery/video helpers on `HasMedia`.
- Inventory: `room_types` → `rooms` (physical inventory with per-property unique room numbers, active/maintenance/inactive states), `rate_periods` (date-range price overrides with overlap rejection), `availability_blocks` (room type or single room, inclusive ranges).
- `AvailabilityService`: sellable-inventory resolution (`blockedRoomIds`, `availableRoomCount`) — the foundation the Phase 05 booking engine consumes.
- Property staff: per-property assignments restricted to active business members, unique per property + user, audited.
- Module gating: generic `module.active:<slug>` middleware; host area requires the `property` module (trial-aware via ModuleService).
- Permissions: `rooms.*`, `rates.*`, `availability.*`, `properties.staff.manage` added to `PermissionRegistry`; system role map extended (owner full, manager no deletes, front_desk view-only); `db:seed` now refreshes existing tenants' roles.
- Tenant-scoped UI: sidebar "Properties" link (permission-gated), five management screens on the bnb design system.
- Correctness: route model binding runs before `tenant.context` (middleware priority), so all `{property}`/nested params resolve manually through tenant-scoped relations — cross-tenant rows are plain 404s.
- Tests: 24 new Pest tests covering gating, isolation, authorization per role, CRUD, media, inventory, rates, availability and staff.

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

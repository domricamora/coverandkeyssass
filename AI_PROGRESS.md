# AI DEVELOPMENT PROGRESS

## Current Phase

Phase 20 - Accounting — **COMPLETE (verified)**
Next: Phase 21 - CRM

## Completed

- **Phase 01 — Foundation**: Laravel 13 + PHP 8.3 + MySQL; auth (Breeze Blade), multi-tenancy (TenantContext, SetTenantContext, BelongsToTenant deny-by-default, TenantPolicy), RBAC (PermissionRegistry, per-tenant system roles + platform super_admin), team management (Livewire), Super Admin area + `superadmin:create`, audit logging, Cover & Keys branding (bnb design system), 49 tests
- **Phase 02 — Module engine**: modules/module_features/module_plans/tenant_modules, ModuleService (dependency auto-enable, trials, guarded disable), admin CRUD + tenant enable/disable page with timezone-aware trial countdown, ModuleSeeder catalogue (core/property/booking/workforce/restaurant/inventory/finance/crm/analytics), 65 tests
- **Phase 03 — Marketplace**: public cross-tenant browse of published stays and dining (`/stays`, `/hotels` + keyword/destination/price/guests/type filters and sorting, `/hotels/{location}` destination pages, `/property/{slug}`, `/restaurants`, `/restaurant/{slug}`); tenant-owned listings with `draft → pending → published → suspended` lifecycle and `publicQuery()` as the single cross-tenant read path; polymorphic media (cover + ordered galleries); amenities/cuisines; guest reviews with rating aggregates maintained by observers; personal wish list (favorites, auth + type whitelist); marketing site (features/pricing/contact); `MarketplaceReferenceSeeder` + `MarketplaceDemoSeeder`; `PropertyPolicy` registered ahead of Phase 04
- **Bugfix (Phase 03 verification)**: `FavoriteController` typed the wish-list eager-load closure against the wrong `MorphTo` class, so a wish list containing items returned HTTP 500 — found by the new wish-list HTTP tests and fixed to `Illuminate\Database\Eloquent\Relations\MorphTo`
- **Phase 04 — Property Management**: new `App\Modules\PropertyManagement` module extending the Marketplace `Property`; host-side profile CRUD (policies JSON, amenities sync, location, pricing), publish/unpublish with audit trail; photos + videos via additive `media.kind` (single cover with promotion); room types → rooms inventory (per-property unique room numbers, active/maintenance/inactive); rate periods with overlap rejection; availability blocks; `AvailabilityService` (`blockedRoomIds`, `availableRoomCount`) as the Phase 05 foundation; property staff assignments (business members only, audited); generic `module.active:property` gating middleware; permissions (`rooms.*`, `rates.*`, `availability.*`, `properties.staff.manage`) + extended role map; sidebar link + five management screens; `db:seed` refreshes existing tenants' roles
- **Architecture fix (Phase 04)**: Laravel's `SubstituteBindings` runs before `tenant.context` (middleware priority), so module routes resolve `{property}`/nested params manually through tenant-scoped relations — cross-tenant rows are plain 404s (documented in ARCHITECTURE.md + module docs)
- **Phase 05 — Booking Engine**: `App\Modules\Booking` — bookings / booking_rooms / room_nights / promotions; `BookingService` (reserve under `FOR UPDATE` lock + unique `(room_id, night)` backstop, per-night pricing with rate periods + weekend rates + min stay, promo codes, 9-state machine with date guards, hold expiry, audited transitions, inventory release); host desk (list/filters, manual/hold/walk-in/multi-room/group create with live availability, detail + actions, 14-day room calendar, promotions); marketplace "Request to book" → pending; permissions `bookings.*`, `promotions.manage`. Live 8-process race on one room → exactly 1 booking
- **Phase 06 — Customer Portal**: `App\Modules\Customer` — `/account` dashboard, trips (upcoming/past), trip detail, invoice, guest self-cancel before check-in, verified reviews after check-out, database notifications (confirm/cancel) with mark-read; orders/payments/wallet/loyalty/coupons/messages deferred to their phases
- **Phase 07 — PayMongo**: `App\Modules\Payments` — checkout sessions, signed webhook (outside web group, replay window), server-side session verification before confirming, idempotent event ledger + row-locked markPaid, failed payments + retry, refunds vetoing the `refunded` transition, customer Payments tab + booking payment panels
- **Phase 08 — Host Wallet & Commissions**: `App\Modules\Wallet` — commission rates (global / listing / promotional, Super Admin screen + revenue totals), one commission per paid payment, host wallet with pending → available on check-out/no-show and reversal on refund, append-only ledger (sum = balances), payouts (host request, admin paid/reject), all row-locked; earning + paid status + booking confirmation commit atomically (webhook retry completes a failed attempt)
- **Phase 09 — Restaurant Management**: `App\Modules\RestaurantManagement` — host restaurant profile (hours, cuisines, contact, photos, audited publish), menu builder (categories → items → modifier groups with min/max → options), `MenuItem::priceWith()` server-side pricing with rule enforcement, dining areas + tables, public menu on `/restaurant/{slug}`, permissions `menu.*`, `tables.*`
- **Phase 10 — Restaurant Reservations**: `table_reservations` + `ReservationService` — slots from opening hours + sitting length, smallest-fit table allocation under `FOR UPDATE` lock on the restaurant tables (live 8-process race → exactly 1), pending/confirmed/seated/completed/cancelled/no_show with date guards, host day calendar + phone bookings, marketplace "Book a table" with slot picker, customer Tables tab + self-cancel, notifications, permissions `reservations.*`
- **Phase 11 — Online Food Ordering**: `App\Modules\Ordering` — session cart, server-side pricing via `MenuItem::priceWith()`, order promo codes (`promotions.applies_to`), inclusive/exclusive tax, pickup/delivery, cash or PayMongo; 9-state order machine; snapshot `orders`/`order_items`; Payments + Wallet generalised (`order_id` beside `booking_id`) — commission on online orders, release on completed, reversal on refund, refused refund vetoes; host order queue + promo codes; customer Orders tab; `TenantContext::runAs()` shared tenant switch
- **Phase 12 — Delivery**: `App\Modules\Delivery` — zones (named / radius via haversine, fee, free-over, minimum, ETA, pause), drivers, scheduled orders, driver assignment (required for dispatch, audited), ETAs on accept/dispatch, delivered time; host delivery setup screen; permission `delivery.manage`
- **Phase 13 — Hotel Room Service**: `fulfillment = room_service` for checked-in guests at the same business (booking + room on the order), "charge to my room" (`room_charge` → `charged`, settled by the Phase 14 folio), delivery path without driver, 10-min walk ETA, restaurant toggle
- **Phase 14 — Guest Folio**: `App\Modules\Folio` — per-booking ledger (charges/payments/refunds, voids, never deletes); idempotent sync of room nights (early check-out aware), discount, PayMongo payments/refunds and room-charge orders; desk charges/payments/refunds; printable + guest read-only folio; permissions `folio.*`
- **Phase 15 — Housekeeping**: `App\Modules\Housekeeping` (workforce) — room housekeeping status (out_of_order leaves sellable inventory), tasks (checkout clean auto-queued on check-out, start/complete/inspect, failed inspection → high-priority re-clean), member-only assignment + notifications, issue reports → maintenance tickets; board; permissions `housekeeping.*`; `staff` role = housekeeper
- **Phase 16 — Maintenance**: `App\Modules\Maintenance` (workforce) — tickets (category, priority, assignment + notification, cost), workflow with reopen, notes thread with system notes, private attachments (local disk, authorised download), room hand-back to housekeeping on last resolve; permissions `maintenance.*`
- **Phase 17 — Staff Management**: `App\Modules\Workforce` — departments, positions, employees (linked member accounts = permissions), weekly roster (no overlap / max 16h / not on leave / overnight), attendance with late minutes, leave with approval cancelling shifts, "My work" self-service; permissions `staff.*`, `schedules.manage`, `attendance.manage`, `leave.approve`
- **Phase 18 — Inventory**: `App\Modules\Inventory` — items/units/categories/suppliers/locations, locked ledgered moves (receive with weighted cost, issue, waste, count, transfer), low-stock alerts, purchase orders with partial receiving, menu recipes with unit conversion; accepted food orders consume stock (idempotent), cancel restores; permissions `inventory.*`, `purchasing.manage`
- **Phase 19 — POS**: optional `pos` module (`App\Modules\Pos`) — register tickets as `channel = pos` orders (tables / counter, kitchen straight away, add lines, manual discounts), split payments with change inside cash sessions, charge to room → folio, close / void / refund, receipts, kitchen display for all channels, daily closing with variance + Z-report; permissions `pos.*`
- **Phase 20 — Accounting**: `App\Modules\Accounting` (finance) — double-entry GL (balanced, idempotent source keys, reversals), automatic postings from folios / orders / POS / PayMongo platform wallet / commissions / payouts / stock / purchasing / maintenance, expenses with input VAT, invoices (receivables), supplier payments (payables), P&L / VAT / trial balance / journal; `accounting:sync`; permissions `accounting.*`
- Tests: Pest suite — **220 passed, 1243 assertions** (Phase 20: 6, Phase 19: 5, Phase 18: 6, Phase 17: 6, Phase 16: 4, Phase 15: 5, Phase 14: 7, Phase 13: 5, Phase 12: 6, Phase 11: 8, Phase 10: 6, Phase 09: 10, Phase 05: 19, Phase 06: 7, Phase 07: 12, Phase 08: 10); previously **98 passed, 320 assertions** (`php artisan test`, real MySQL), including 24 Phase 04 coverage tests
- Documentation: docs/* updated per phase; `docs/modules/{marketplace,property-management}.md` completed; CHANGELOG carries Phase 01–08 entries

## In Progress

- (nothing — Phase 21 not started)

## Pending

- Phases 21–38: CRM, marketing, loyalty, reviews, messaging, notifications, SaaS billing, super admin, marketplace administration, SEO, API, security audit, performance, testing, deployment, backups, monitoring, final audit

## Known Issues

- Redis not installed locally: dev uses database cache/queue drivers (config ready for Redis in production)
- WAMP ships `default_storage_engine=MyISAM`; app now forces InnoDB per connection (server my.ini also updated; apply the same on any host with a MyISAM default)
- The `&` in the project path breaks npm `.bin` shims under cmd — build via `node node_modules/vite/bin/vite.js build`
- **No git remote configured** — commits are local-only until an origin is added (`git remote add origin <url>`)

## Last Agent

Claude Code (Opus)

## Last Updated

2026-09-26

## Last Successful Test

php artisan test

Result: PASS (220 tests, 1243 assertions, MySQL `hospitality_os_testing`)

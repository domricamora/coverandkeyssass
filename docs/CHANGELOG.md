# CHANGELOG.md

## 2026-09-26 — Phase 18: Inventory (v0.18.0)

(verified: 209 tests / 1122 assertions)

- New `App\Modules\Inventory` (inventory module): categories, units with conversion, suppliers, stock locations, items (SKU, weighted-average cost, reorder level), per-location levels and an append-only movement ledger with running balances.
- Moves lock the item row, then the level row. Receive, issue, waste (reason required), stock count (adjustment) and transfer. No negative stock except for sales.
- Low-stock alerts: a notification to managers when an item crosses its reorder level, plus a low-stock filter.
- Purchase orders: draft → ordered → partially received → received at the ordered cost. Over-receipt is blocked. Cancel only before receiving.
- Menu recipes with unit conversion (150 g → 0.15 kg). An accepted food order deducts its ingredients from the restaurant's kitchen location, idempotently. Cancelling an accepted order restores them.
- Screens: stock list, item ledger with movement form, purchase orders, recipes. Permissions `inventory.view/manage`, `purchasing.manage`.
- 6 new Pest tests.

## 2026-09-26 — Phase 17: Staff Management (v0.17.0)

(verified: 203 tests / 1066 assertions)

- New `App\Modules\Workforce`: departments, positions, and employees (numbered, optionally linked to a member account whose tenant role gives their permissions).
- Weekly roster of shifts: 15 minutes to 16 hours, no overlaps, not on approved leave, overnight shifts supported, row-locked per employee.
- Attendance: clock in / out matched to the covering shift, with late minutes and minutes worked. One open clock-in at a time. Managers can clock staff in and out.
- Leave: requests with overlap checks. Approval cancels the shifts inside the range. Reject with a note, or withdraw while pending.
- "My work" self-service: shifts, assigned housekeeping tasks and maintenance tickets, clock button, leave.
- Permissions `staff.view/manage`, `schedules.manage`, `attendance.manage`, `leave.approve`. Sidebar shows Staff or My work.
- 6 new Pest tests.

## 2026-09-25 — Phase 16: Maintenance (v0.16.0)

(verified: 197 tests / 1008 assertions)

- New `App\Modules\Maintenance` provider (workforce): tickets with category, priority, assigned staff (notified), cost, started / resolved / closed times.
- Workflow open → in_progress ⇄ on_hold → resolved → closed, with reopen. Workers claim or progress their own tickets. Managers assign, cost and close. Closed tickets are read-only.
- Notes thread with system notes for every change. Private attachments (images / PDF, 5 MB) on the local disk behind an authorised download route.
- When a room's last active ticket is resolved, the room goes back to housekeeping as dirty. Housekeeping's "Report issue" now opens tickets through `MaintenanceService`.
- Maintenance list and detail screens, sidebar link. Permissions `maintenance.view/work/manage`.
- 4 new Pest tests.

## 2026-09-25 — Phase 15: Housekeeping (v0.15.0)

(verified: 193 tests / 961 assertions)

- New `App\Modules\Housekeeping` (workforce module): `rooms.housekeeping_status` (dirty, cleaning, clean, inspected, maintenance, out_of_order) and `housekeeping_tasks`.
- Check-out makes the rooms dirty and queues a checkout clean. Tasks follow start → complete → inspect. A failed inspection sends the room back to dirty and queues a high-priority re-clean for the same housekeeper.
- Assignment only to members of the business, with a database notification. Housekeepers work their own or unassigned tasks; managers override.
- "Report issue" opens a `maintenance_tickets` row (base of Phase 16) and sets the room to maintenance or out of order. Out-of-order rooms leave `Room::sellable()`, so they cannot be booked.
- Housekeeping board with a room grid, task queue, "My tasks" and open tickets. Permissions `housekeeping.view/work/manage`. The `staff` role becomes the housekeeping role.
- 5 new Pest tests.

## 2026-09-25 — Phase 14: Guest Folio (v0.14.0)

(verified: 188 tests / 918 assertions)

- New `App\Modules\Folio`: `folio_entries`, a per-booking ledger of charges, payments and refunds, with voids and no deletes.
- An idempotent sync posts room nights (weekday and weekend rates; only nights stayed after an early check-out), the promo discount, online PayMongo payments and refunds, and room-service orders charged to the room (Phase 13). Cancelled orders and cancelled-before-stay nights are voided.
- Desk: charges (food, laundry, minibar, activities, transport, other), payments (cash, card, transfer, e-wallet), refunds capped at the net paid, voids of manual lines with a reason. Totals by category and balance due. Printable folio.
- The guest's read-only folio is linked from the trip page, and the host folio from the booking page.
- Permissions `folio.view/manage` (owner, manager, front desk), `folio.void` (owner, manager).
- 7 new Pest tests.

## 2026-09-25 — Phase 13: Hotel Room Service (v0.13.0)

(verified: 181 tests / 851 assertions)

- Orders can be delivered to the room (`fulfillment = room_service`) for signed-in guests with a checked-in stay at a property of the same business. The order is linked to the booking and room.
- "Charge to my room" (`payment_method = room_charge`, `payment_status = charged`) for the folio (Phase 14). Cash and online also work. No payment or commission is created for room charges.
- Room service follows the delivery states without a driver. The ETA is prep time plus a 10-minute walk.
- Restaurant setting `room_service_enabled`. The cart shows a room picker and the room-charge option. Order screens use `fulfillmentLabel()` / `paymentLabel()`.
- Fix found by the new tests: an explicit room id outside a single-room stay no longer falls back to that room.
- 5 new Pest tests.

## 2026-09-25 — Phase 12: Delivery (v0.12.0)

(verified: 176 tests / 820 assertions)

- New `App\Modules\Delivery`: `delivery_zones` (named area or radius around the restaurant; fee, free-over, minimum order, ride ETA, pause) and `delivery_drivers`.
- Checkout: delivery needs an active zone that covers the drop-off point (haversine against the restaurant's coordinates; browser "share my location"). The fee comes from the zone and the minimum order is enforced. Optional scheduled delivery or pickup (prep time up to 7 days ahead).
- Dispatch: drivers can be assigned to accepted delivery orders. Out-for-delivery is refused without a driver, and a driver cannot be cleared while the order is on the road. Assignments are audited.
- ETAs: set on acceptance (scheduled time, or prep plus ride) and on dispatch (ride). Delivered time is recorded. The customer sees ETA, driver and delivered time.
- Host delivery setup screen (zones, prep time, coordinates, drivers); driver dropdown on the order queue and detail. New permission `delivery.manage` (owner, manager).
- 6 new Pest tests. The Phase 11 delivery test moved to zone-based delivery.

## 2026-09-25 — Phase 11: Online Food Ordering (v0.11.0)

(verified: 171 tests / 776 assertions)

- New `App\Modules\Ordering`: `orders` and `order_items` (snapshot names, modifiers, prices and tax settings); `OrderService` (quote, place, 9-state machine, audit, customer notifications); session `CartService` (one restaurant per cart).
- Server-side pricing only: every line is priced through `MenuItem::priceWith()`. Order promo codes use `promotions` (new `applies_to` column; stay and order codes are kept apart). Per-restaurant tax rate, inclusive (PH VAT default) or exclusive.
- Public "Add to order" on the menu (modifier radios or checkboxes, qty, notes), `/cart`, and checkout for pickup or delivery, paid in cash or online (PayMongo).
- Payments and Wallet generalised: `payments.order_id` / `commissions.order_id` sit beside `booking_id`. Online orders earn commission at the restaurant rate, released on completed and reversed on refund. A refund refused by PayMongo vetoes the transition.
- Host order queue per restaurant (by stage, with actions), order detail, and order promo codes. Customer "Orders" tab with pay, cancel-while-pending and payment return.
- `TenantContext::runAs()` replaces the duplicated tenant switch in BookingService and ReservationService.
- Permissions `orders.view/manage` (owner, manager, front desk). Restaurant settings `ordering_enabled`, `tax_rate`, `tax_inclusive`.
- 9 new Pest tests.

## 2026-09-25 — Phase 10: Restaurant Reservations (v0.10.0)

(verified: 162 tests / 680 assertions, plus a live 8-process race for one table → exactly 1 reservation)

- `table_reservations` and `ReservationService`: time slots from opening hours and the sitting length; table allocation (smallest fit) under a `FOR UPDATE` lock on the restaurant's tables. Overlapping pending, confirmed or seated reservations can never share a table.
- State machine: pending → confirmed → seated → completed, plus cancelled and no-show. Date guards on seat and no-show; completing early frees the table. Audited transitions; the customer is notified on confirm or cancel.
- Host desk `/dashboard/restaurants/{slug}/reservations`: day calendar per table, booking list with actions, phone bookings (confirmed immediately, optional table choice).
- Marketplace "Book a table" on `/restaurant/{slug}` (slot picker from `/restaurant/{slug}/slots`). Requests start as pending. Customer "Tables" tab at `/account/reservations` with self-cancel.
- New permissions `reservations.view/manage` (owner, manager, front desk); new setting `restaurants.reservation_duration_minutes`.
- 6 new Pest tests.

## 2026-09-25 — Phase 09: Restaurant Management (v0.9.0)

(verified: 156 tests / 621 assertions)

- New `App\Modules\RestaurantManagement` (gated by `module.active:restaurant`): host profile CRUD, opening hours per day, cuisines, contact details, reservations/delivery flags, photos with cover, audited publish/unpublish.
- Menu builder: `menu_categories` → `menu_items` (price, photo, availability) → `modifier_groups` (min/max rules: required modifiers or optional add-ons) → `modifier_options` (price delta).
- `MenuItem::priceWith(optionIds)` is the server-side price calculation that ordering will use. It enforces group rules, option availability and option ownership.
- Floor plan: `dining_areas` and `restaurant_tables` (label unique per restaurant, seats, active/inactive).
- The public `/restaurant/{slug}` page shows the menu, with "Sold out" items and add-on prices.
- Permissions `menu.view/manage` and `tables.view/manage` (owner and manager manage; front desk views). Restaurants link in the sidebar.
- 10 new Pest tests.

## 2026-09-24 — Phase 08: Host Wallet & Commissions (v0.8.0)

(verified: 146 tests / 560 assertions)

- New `App\Modules\Wallet`: `commission_rates`, `commissions`, `wallets`, `wallet_transactions`, `payouts`; `WalletService` (row-locked, ledgered balance changes).
- Paid payment → commission split at the resolved rate (promotional listing → promotional all → listing → global → default 10%) → host pending balance. Released to available on check-out or no-show; reversed on refund.
- Payouts: host request (min ₱100, at most the available balance); Super Admin marks paid with a transfer reference, or rejects (credited back).
- Super Admin commission rates (global / property / restaurant / promotional) and platform revenue totals; admin dashboard links.
- Payments: `PaymentPaid` / `PaymentRefunded` events; the paid status, earning and booking confirmation now commit in one transaction, so a failure rolls back and the webhook retry completes it. Booking: `BookingTransitioned` event.
- Permissions `wallet.view` (owner, manager), `payouts.request` (owner); Wallet sidebar link. PayMongo test fake moved to `tests/Support/PayMongoFake.php`.
- 10 new Pest tests.

## 2026-09-24 — Phase 07: PayMongo (v0.7.0)

(verified: 136 tests / 485 assertions)

- New `App\Modules\Payments`: `payments` + `payment_events` tables, `PayMongoGateway` (checkout sessions, refunds, signature verification) and `PaymentService`.
- Checkout → webhook / return URL → **server-side session lookup** (status, amount, currency) → booking confirmed. Never confirmed from a redirect or a webhook body alone.
- Idempotent: event ledger with unique event ids, row-locked `markPaid`, unique provider ids, reuse of an open checkout.
- Failed payments recorded with retry; refunds go through PayMongo when a booking is marked refunded (a provider refusal vetoes the transition); a payment that lands after cancellation is recorded and flagged.
- Signed webhook outside the web group (no CSRF/session), with a 5-minute replay window.
- Customer portal Payments tab, payment panels on guest and host booking pages. Booking emits a new `BookingTransitioning` event.
- 12 new Pest tests (PayMongo faked). Real-sandbox run still to do.

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

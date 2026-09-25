# DATABASE.md

Engine: MySQL 8+/9 · InnoDB (enforced per-connection — WAMP ships MyISAM default; see `AppServiceProvider`) · utf8mb4.

## Phase 01 tables

| Table | Purpose | Notes |
|---|---|---|
| users | accounts | status (active/suspended), phone, last_login_at, soft deletes |
| tenants | businesses | uuid, slug (unique), business_type, status, currency, soft deletes |
| tenant_users | membership | unique (tenant_id, user_id), status, joined_at |
| roles | role definitions | tenant_id NULL = platform role; unique slug per tenant |
| permissions | permission catalogue | name unique, group |
| role_permissions | role ↔ permission | unique pair |
| user_roles | user ↔ role assignments | tenant-scoped; tenant_id NULL = platform-wide |
| audit_logs | security trail | actor, subject (morph), ip, user_agent, old/new JSON |

Framework tables (`cache`, `jobs`, `sessions`, …) follow the Laravel defaults; sessions and cache are database-backed in dev.

## Conventions

- BIGINT UNSIGNED ids, timestamps everywhere, soft deletes where meaningful.
- Foreign keys with explicit `cascadeOnDelete` / `nullOnDelete`.
- Composite uniques for pivots; indexes on status/tenant_id columns.
- Room numbers are unique per property **among live rows** — enforced in the application (a MySQL unique index including the nullable `deleted_at` never fires); the composite index backs the lookup.

## Phase 03 tables (Marketplace)

| Table | Purpose | Notes |
|---|---|---|
| locations | destinations (SEO landing pages) | reference data, slug-identified |
| property_types | stay categories | reference data (hotel, resort, villa…) |
| amenities | stay features | reference data; `property_amenity` pivot |
| cuisines | dining categories | reference data; `cuisine_restaurant` pivot |
| properties | tenant-owned stay listings | status lifecycle `draft→pending→published→suspended`, uuid + slug, pricing/capacity/geo, `highlights` + `policies` JSON, rating aggregates, soft deletes |
| restaurants | tenant-owned dining listings | same lifecycle/scope rules as properties |
| media | polymorphic gallery | morph `mediable`, cover flag, `kind` (image/video — Phase 04); no tenant column, reached only through its parent |
| favorites | guest wish list | unique (user, morphed listing); counters maintained by observers |
| reviews | guest reviews | polymorphic `reviewable`, 1–5 rating, status lifecycle; aggregates (`avg_rating`, `reviews_count`) on the listing |

## Phase 04 tables (Property Management)

| Table | Purpose | Notes |
|---|---|---|
| room_types | sellable categories per property ("Deluxe Room") | tenant + property FKs, occupancy, bed info, base/weekend price, min stay, soft deletes; unique name per property among live rows (app-enforced) |
| rooms | physical inventory ("101, 102…") | property + room_type FKs, room_number unique per property (live rows), status `active/maintenance/inactive`, soft deletes |
| rate_periods | date-range price overrides | room_type FK, inclusive start/end, nightly + weekend price, min stay; overlaps rejected in the app |
| availability_blocks | maintenance/owner blocks | room_type FK, optional room FK, inclusive date range, reason; consumed by AvailabilityService |
| property_staff | per-property assignments | property + user FKs, role label, assigned_by; unique (property_id, user_id); members must be active tenant members |

## Phase 05 tables (Booking Engine)

| Table | Purpose | Notes |
|---|---|---|
| promotions | promo codes | tenant FK, optional property FK, `percent/fixed` value, date window, min nights, max/used counts, active flag; unique (tenant_id, code) |
| bookings | reservations `[check_in, check_out)` | unique `reference`; tenant + property FKs, optional customer `user_id`, `created_by`, `promotion_id`; source `walk_in/manual/marketplace`; status (9-state machine); guest info, group name, adults/children; subtotal/discount/total; hold expiry and lifecycle timestamps |
| booking_rooms | rooms assigned to a booking | booking + room_type + room FKs, `nightly_rates` JSON price snapshot, total |
| room_nights | occupied inventory | booking_room + room FKs, `night`; **unique (room_id, night)** is the DB-level double-booking guard; rows deleted when a booking stops occupying the room |

## Phase 06 tables (Customer Portal)

| Table | Purpose | Notes |
|---|---|---|
| notifications | Laravel database notifications | booking confirmed/cancelled messages for customers |

## Phase 07 tables (Payments / PayMongo)

| Table | Purpose | Notes |
|---|---|---|
| payments | one PayMongo checkout attempt per row | tenant + booking FKs, optional customer; unique `checkout_session_id`, unique `provider_payment_id`, unique `refund_id`; amount/currency, status `pending/paid/failed/refunded`, method, failure reason, refund amount/time |
| payment_events | webhook idempotency ledger | unique `event_id`, type, raw JSON payload, `processed_at` |

## Phase 08 tables (Wallet & Commissions)

| Table | Purpose | Notes |
|---|---|---|
| commission_rates | platform commission rates | kind `global/listing/promotional`, nullable morph `rateable` (property/restaurant), percent `rate`, optional date window; no tenant (platform-owned) |
| commissions | split of one paid payment | tenant + booking FKs, unique `payment_id`, rate used, gross / platform_fee / host_amount, status `pending/released/reversed` |
| wallets | host wallet per business | unique `tenant_id`, currency, `pending_balance`, `available_balance` |
| wallet_transactions | append-only ledger | wallet FK, type, bucket `pending/available`, signed amount, `balance_after`, optional commission / payout FK |
| payouts | host withdrawals | wallet FK, amount, method + account, status `requested/paid/rejected`, requested_by / processed_by, reference, note |

## Phase 09 tables (Restaurant Management)

| Table | Purpose | Notes |
|---|---|---|
| menu_categories | menu sections | tenant + restaurant FKs, unique `(restaurant_id, name)`, `is_active`, sort_order |
| menu_items | dishes / drinks | category FK (restrict), price decimal(12,2), currency, photo_url, `is_available`, sort_order |
| modifier_groups | modifiers / add-ons per item | item FK (cascade), `min_select`, nullable `max_select` (null = unlimited) |
| modifier_options | choices in a group | group FK (cascade), price delta, `is_available` |
| dining_areas | floor sections | unique `(restaurant_id, name)` |
| restaurant_tables | physical tables | nullable area FK (nullOnDelete), unique `(restaurant_id, label)`, seats, status `active/inactive` |

Opening hours reuse `restaurants.opening_hours` (JSON `{day: "11:00–22:00"}`).

## Phase 10 tables (Restaurant Reservations)

| Table | Purpose | Notes |
|---|---|---|
| table_reservations | a party at one table for `[reserved_at, ends_at)` | tenant, restaurant, nullable table (nullOnDelete), nullable user / created_by; unique `reference`; source `marketplace/host`; status `pending/confirmed/seated/completed/cancelled/no_show`; party_size, guest contact, special_requests, confirmed/seated/cancelled timestamps; indexes `(restaurant_id, reserved_at)`, `(restaurant_table_id, reserved_at, ends_at)`, `(user_id, reserved_at)` |

`restaurants.reservation_duration_minutes` (default 90) sets the sitting length.

## Phase 11 tables (Online Food Ordering)

| Table | Purpose | Notes |
|---|---|---|
| orders | a food order | tenant, restaurant, nullable user / promotion; unique `reference`; status (9 states); fulfillment `pickup/delivery`; payment_method `online/cash`; payment_status `unpaid/paid/refunded`; customer name / phone, delivery_address, notes; currency, subtotal, discount_total, tax_rate, tax_inclusive, tax_total, delivery_fee, total; accepted/ready/completed/cancelled timestamps |
| order_items | order lines (snapshots) | order FK (cascade), nullable menu_item FK (nullOnDelete), name, `modifiers` JSON `[{group,name,price}]`, unit_price (base + modifiers), quantity, line_total, notes |

Changes to existing tables: `payments.booking_id` and `commissions.booking_id` are now nullable, and each gains a nullable `order_id` FK (exactly one of the two is set). `promotions` gains `applies_to` (`stays` default / `orders`), a nullable `restaurant_id` and `min_subtotal`. `restaurants` gains `ordering_enabled`, `tax_rate` (default 12) and `tax_inclusive` (default true).

## Phase 12 tables (Delivery)

| Table | Purpose | Notes |
|---|---|---|
| delivery_zones | where a restaurant delivers | tenant + restaurant FKs, unique `(restaurant_id, name)`, nullable `radius_km` (null = named area), fee, nullable min_order / free_over, eta_minutes, is_active |
| delivery_drivers | the business's riders | tenant FK, name, phone, vehicle, is_active |

`orders` gains `delivery_zone_id` and `driver_id` (both nullOnDelete), `delivery_lat/lng`, `scheduled_for`, `estimated_at`, `dispatched_at`, `delivered_at`, and an index `(driver_id, status)`. `restaurants` gains `prep_minutes` (default 20).

## Phase 13 changes (Hotel Room Service)

`orders` gains `booking_id` and `room_id` (nullable, nullOnDelete) for `fulfillment = room_service`. It also gets the new payment_method `room_charge` and payment_status `charged`. `restaurants` gains `room_service_enabled` (default false).

## Phase 14 tables (Guest Folio)

| Table | Purpose | Notes |
|---|---|---|
| folio_entries | per-booking ledger | tenant + booking FKs; type `charge/payment/refund`; category; description; quantity × unit_amount = amount (negative for discounts); service_date; reference; nullable `source_key` unique per booking (derived lines); posted_by; voided_at / void_reason / voided_by (never deleted) |

## Phase 15 tables (Housekeeping)

| Table | Purpose | Notes |
|---|---|---|
| housekeeping_tasks | cleaning queue | tenant, property, room, nullable booking; type; status `pending/in_progress/completed/cancelled`; priority; due_on; notes; assigned_to / created_by; started / completed; inspected_by / inspected_at / inspection_passed / inspection_notes |
| maintenance_tickets | repair jobs (workflow in Phase 16) | tenant, property, nullable room; unique reference; title, description; priority `low/normal/high/urgent`; status `open/in_progress/on_hold/resolved/closed`; room_out_of_order; reported_by / assigned_to; resolved_at |

`rooms` gains `housekeeping_status` (default `clean`), `housekeeping_updated_at`, and an index `(property_id, housekeeping_status)`.

## Phase 16 changes (Maintenance)

`maintenance_tickets` gains `category` (default `other`), `cost`, `started_at`, `closed_at`, and an index `(tenant_id, status, priority)`. New table `maintenance_ticket_notes` (tenant, ticket, nullable user, body, is_system). Attachments are `media` rows (`mediable` = ticket, disk `local`, kind `image` / `document`).

## Phase 17 tables (Staff Management)

| Table | Purpose | Notes |
|---|---|---|
| departments | staff departments | tenant, unique `(tenant_id, name)` |
| positions | job titles | tenant, nullable department, hourly_rate, unique `(tenant_id, name)` |
| employees | staff records | tenant; nullable user (unique per tenant), department, position, property; unique `(tenant_id, employee_no)`; name, email, phone, hire_date, employment_type, status |
| shifts | roster | tenant, employee, nullable property, starts_at / ends_at, status `scheduled/cancelled`, notes, created_by |
| attendances | clock records | tenant, employee, nullable shift, clock_in_at / clock_out_at, minutes_worked, late_minutes, recorded_by |
| leave_requests | time off | tenant, employee, type, starts_on / ends_on (inclusive), reason, status, decided_by / decided_at / decision_note |

## Phase 18 tables (Inventory)

| Table | Purpose | Notes |
|---|---|---|
| inventory_categories / stock_locations / suppliers | catalogue | tenant, unique name per tenant (locations: nullable property) |
| inventory_items | stock items | tenant, nullable category, unique `(tenant_id, sku)`, unit, cost_per_unit (weighted avg, 4 dp), reorder_level, is_active |
| stock_levels | on hand | item + location unique, quantity (14,3) |
| stock_movements | ledger | item, location, type, signed quantity, balance_after, unit_cost, reference, unique nullable `source_key`, notes, user |
| purchase_orders / purchase_order_lines | purchasing | supplier + location (restrict), unique reference, status, expected_on, total; lines: item, quantity, received_quantity, unit_cost |
| menu_item_ingredients | recipes | menu item + stock item unique, quantity (stock unit), entered_unit / entered_quantity |

`restaurants` gains `stock_location_id` (nullOnDelete): the kitchen that food sales consume from.

## Phase 19 tables (POS)

| Table | Purpose | Notes |
|---|---|---|
| pos_sessions | cashier shifts | tenant, restaurant, opened_by / closed_by, opening_float, expected_cash, counted_cash, variance, notes, opened_at / closed_at |
| pos_payments | register money | tenant, order, session (restrict), method `cash/card/ewallet/room_charge`, signed amount (negative = refund), tendered, change_given, reference, user |

`orders` gains `channel` (`online` / `pos`, default online), `restaurant_table_id` (nullOnDelete), `discount_reason`, and an index `(restaurant_id, channel, status)`. There is a new payment_method `pos` and a new fulfillment `dine_in`. The module catalogue gains `pos` (depends on `restaurant`).

## Phase 20 tables (Accounting)

| Table | Purpose | Notes |
|---|---|---|
| ledger_accounts | chart of accounts | tenant, unique code and system_key per tenant, type `asset/liability/equity/revenue/expense` |
| journal_entries | postings | tenant, entry_date, memo, reference, unique `(tenant_id, source_key)`, party_type / party_id (supplier / invoice), created_by |
| journal_lines | debits / credits | tenant, entry (cascade), account (restrict), debit, credit |
| expenses | bills | tenant, account, vendor, expense_date, amount (gross), tax_amount, paid_from `cash/bank/payable`, reference, notes |
| invoices / invoice_lines / invoice_payments | receivables | unique `(tenant_id, number)`, customer, issue / due dates, status `draft/issued/paid/void`, tax_rate, subtotal / tax_total / total / amount_paid; lines (qty × unit); payments (paid_on, amount, method, reference) |
| supplier_payments | payables settled | tenant, supplier (restrict), paid_on, amount, method, reference |

## Seeding

`php artisan db:seed` → PermissionSeeder (catalogue) + RoleSeeder (platform `super_admin` **and** a refresh of every existing tenant's system roles, so newly added catalogue permissions reach already-provisioned businesses).
Tenant roles (owner, manager, front_desk, staff) are provisioned per tenant at creation time via `RoleSeeder::ensureTenantRoles()` — the same call tenant-creation endpoints make.

## Provisioning the first Super Admin

```bash
php artisan superadmin:create "Name" "email@example.com" --password=…
# interactive runs may omit --password (hidden prompt)
```

Never hard-code admin credentials anywhere.

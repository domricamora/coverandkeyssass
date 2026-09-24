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

## Seeding

`php artisan db:seed` → PermissionSeeder (catalogue) + RoleSeeder (platform `super_admin` **and** a refresh of every existing tenant's system roles, so newly added catalogue permissions reach already-provisioned businesses).
Tenant roles (owner, manager, front_desk, staff) are provisioned per tenant at creation time via `RoleSeeder::ensureTenantRoles()` — the same call tenant-creation endpoints make.

## Provisioning the first Super Admin

```bash
php artisan superadmin:create "Name" "email@example.com" --password=…
# interactive runs may omit --password (hidden prompt)
```

Never hard-code admin credentials anywhere.

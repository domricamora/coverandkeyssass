# booking

STATUS: COMPLETE — Phase 05 (Booking engine). Verified 2026-09-24: full Pest suite green (124 tests, MySQL `hospitality_os_testing`) plus a live 8-process race against one room (exactly one booking, seven clean "sold out" rejections).

## Purpose

Availability, reservations and the reservation lifecycle for the rooms that
Property Management (Phase 04) defines. Covers the master plan §PHASE 05 list:
availability, calendar, reservations, room inventory, pricing, discounts,
promotions, cancellation, guest information, check-in, check-out, no-show,
walk-in, manual reservation, multi-room and group booking.

## Models (`App\Modules\Booking\Models`)

| Model | Table | Notes |
|---|---|---|
| `Booking` | `bookings` | One stay `[check_in, check_out)` (check-out is the departure day, not a night). Reference `BK…` is the public/route key. Holds guest info, group name, totals and lifecycle timestamps. `user_id` = marketplace customer (null for walk-in / manual). |
| `BookingRoom` | `booking_rooms` | One physical room assigned to a booking with its per-night price snapshot (`nightly_rates` JSON). |
| `Promotion` | `promotions` | Promo code (`percent` or `fixed`), optional property, date window, min nights, max uses. Code unique per tenant, stored upper-case. |
| — | `room_nights` | Occupied inventory: one row per room per night, **unique (room_id, night)**. Rows exist only while the booking occupies the room. |

All tenant-owned via `BelongsToTenant`. `Booking::forCustomer($user)` is the one place the tenant scope is lifted (customer portal), filtered by `user_id`.

## Double-booking prevention

1. `BookingService::reserve()` runs in a DB transaction and first locks the room type's sellable rooms (`SELECT … FOR UPDATE`). Concurrent reservations for the same room type queue up; each one re-reads free inventory after the previous one commits.
2. Free inventory = sellable rooms − availability blocks − rows in `room_nights` (`AvailabilityService::freeRoomIds`, which `availableRoomCount` now also uses).
3. Backstop: the unique `(room_id, night)` index. If anything slips past the lock, the insert fails, the transaction rolls back and the guest gets "just booked by someone else".

Expired holds are released (cancelled) at the start of every reservation for the property, before the transaction opens.

## State machine

| From | To |
|---|---|
| pending | held, confirmed, cancelled |
| held | confirmed, cancelled (auto-cancelled when `hold_expires_at` passes) |
| confirmed | checked_in, cancelled, no_show |
| checked_in | checked_out |
| checked_out | completed |
| cancelled / no_show | refunded |

Guards: check-in and no-show only from the check-in date. Leaving an occupying state (pending, held, confirmed, checked_in) releases `room_nights`: cancel / no-show release all nights, early check-out releases nights from today on. Every transition is audited (`booking.<status>`); confirm and cancel notify the customer (database notification).

Initial status by source: `manual` → confirmed (or `held` with `hold_hours`), `walk_in` → checked_in (must start today), `marketplace` → pending (the host confirms; PayMongo confirms from a verified payment in Phase 07).

## Pricing

Per night: an active rate period wins over room-type defaults; Friday and Saturday nights use the weekend price when set. Minimum stay = max(room-type min, min of any intersecting rate period). Promotions are applied to the subtotal and never discount below zero.

## Routes

Host (`auth`, `tenant.context`, `module.active:booking`), prefix `/dashboard/bookings`, name `bookings.`:
`index` (filters: status, stay dates, search), `create` (with live availability per room type), `store`, `calendar` (rooms × 14 days), `show`, `transition`, `promotions.index|store|toggle`.

Public: `POST /property/{slug}/reserve` (`auth`, throttled) → pending booking. The property page shows the reserve widget only when the business runs the booking module (view composer in `BookingServiceProvider`, so Marketplace does not depend on Booking).

## Permissions

`bookings.view`, `bookings.create`, `bookings.update`, `promotions.manage`. Owner and manager: all. Front desk: view/create/update. Staff: none.

## Tests

`tests/Feature/BookingEngineTest.php` (19 tests): gating, weekend and rate-period pricing, overlapping versus back-to-back stays, DB unique backstop, blocks, group/multi-room plus capacity, cancel releases rooms, state-machine guards, early check-out, no-show → refund, hold expiry, promo codes (percent plus max uses), walk-in, role permissions, tenant isolation, list/show/create/calendar rendering, marketplace request plus module gating.

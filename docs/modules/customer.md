# customer

STATUS: COMPLETE — Phase 06 (Customer portal), for the features whose data exists. Verified 2026-09-24 (124 tests green).

## Purpose

The guest-facing `/account` area (master plan §PHASE 06), across every business the guest booked with.

## Delivered

| Feature | Where |
|---|---|
| Dashboard: upcoming trips, past trips, wish-list / review / unread-notification counts | `GET /account` |
| Bookings: upcoming vs past & cancelled tabs, detail | `/account/bookings`, `/account/bookings/{reference}` |
| Cancellation: guest self-cancel for pending/held/confirmed **before** the check-in date; releases inventory | `POST …/cancel` |
| Invoices: printable per-booking invoice | `…/invoice` |
| Reviews: one verified review per property after check-out (published immediately); list of own reviews | `POST …/review`, `/account/reviews` |
| Notifications: database notifications (booking confirmed / cancelled), mark all read | `/account/notifications` |
| Favorites / Profile | existing `/favorites` (Phase 03) and `/profile` (Breeze), linked from the portal nav |

## Isolation

Every route is `auth`. Bookings are read only through `Booking::forCustomer($user)` (tenant scope lifted, `user_id` filtered), so another guest's reference is a 404. Tenant relations (property, rooms, promotion) and writes (cancel, review) run inside `BookingService::asTenantOf($booking, …)`.

## Deferred (owned by later phases, nothing to show yet)

Orders (Phase 11 ordering), restaurant reservations (restaurant phases), payments and transaction history (Phase 07 PayMongo), wallet (Phase 08), loyalty and rewards, coupons (loyalty/marketing), and messages (messaging). Each one adds its own tab to `customer::partials.nav` when it lands.

## Tests

`tests/Feature/CustomerPortalTest.php` (7 tests): auth, trips isolation between guests, cancel before check-in, refusal from the check-in date, host-confirm notification plus mark read, verified review once, invoice.

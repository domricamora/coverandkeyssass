# folio

STATUS: COMPLETE — Phase 14 (Guest folio). Verified 2026-09-25: full Pest suite green (188 tests / 918 assertions).

Code: `App\Modules\Folio`. It is one ledger per booking (`folio_entries`) of **charges**, **payments** and **refunds**.

```text
Room       ₱3,500      ← room nights (booking_rooms.nightly_rates)
Food         ₱850      ← desk charge / room-service order charged to the room
Transport    ₱800      ← desk charge
------------------
Total      ₱5,150
Payments  −₱5,150      ← online (PayMongo) + desk (cash, card, transfer, e-wallet)
Balance        ₱0
```

## Sources

`FolioService::sync()` mirrors other records. Each derived line has a `source_key` that is unique per booking, so sync is idempotent. It runs on every folio view and print.

| Line | Source | Key |
|---|---|---|
| Room night | `booking_rooms.nightly_rates` | `night:{booking_room}:{date}` |
| Promo discount (negative charge) | `bookings.discount_total` | `discount:{booking}` |
| Online payment / refund | `payments` paid / refunded for the booking | `payment:{id}` / `refund:{id}` |
| Room service | `orders` with `payment_method = room_charge` | `order:{id}` |

Follow-ups:

- **Early check-out**: only nights before the departure day are charged. Later nights already posted are voided.
- **Cancelled or no-show before the stay**: room nights and discount are voided. A cancellation fee is a manual charge.
- **Cancelled or refunded room-service order**: its charge is voided.

## Desk postings (`folio.manage`)

- Charges: food, laundry, minibar, activities, transport, other (quantity × unit). Room and room-service lines only come from their records.
- Payments: cash, card, transfer, e-wallet (optional reference).
- Refunds: at most the net paid so far.
- Void (`folio.void`): manual lines only, with a reason. Nothing is ever deleted, and voids are audited.

Balance = charges − payments + refunds (voided lines excluded). A closed booking (completed or refunded) takes no new charges.

Ceiling: check-out is **not blocked** by an open balance. The folio shows "Balance due", and the desk settles it. Add a guard on `BookingTransitioning` → `checked_out` if a property wants hard enforcement.

## Screens / permissions

- `/dashboard/bookings/{ref}/folio`: ledger, totals by category, post charge / payment / refund, void, printable copy (`/print`). Linked from the booking page.
- `/account/bookings/{ref}/folio`: the guest's read-only copy (their own bookings only). Linked from the trip page.
- Permissions: `folio.view`, `folio.manage` (owner, manager, front desk); `folio.void` (owner, manager).

## Tests

`tests/Feature/FolioTest.php` (7 tests): nights posted once across syncs (weekday and weekend rates); manual charges, void, desk payments and refunds (limits, category rules, synced lines not voidable by hand); early check-out; cancellation before the stay; room-service charges and voiding on cancel; PayMongo payment and refund; screens, front desk cannot void, guest isolation, cross-tenant 404.

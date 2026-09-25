# pos

STATUS: COMPLETE — Phase 19 (Point of sale). Verified 2026-09-26: full Pest suite green (214 tests / 1188 assertions).

Code: `App\Modules\Pos`. It is the optional catalogue module **`pos`** (depends on `restaurant`, auto-enabled with it) and is gated `module.active:pos`. Register tickets are ordinary `orders` rows with `channel = pos`. They go through the same `OrderService` as online orders, so pricing (`MenuItem::priceWith`), tax snapshots, kitchen states, inventory consumption (Phase 18) and folio posting (Phase 14) all apply.

## Flow

```text
open register (float) → ticket at a table or the counter → kitchen (accepted → preparing → ready)
→ add lines / discount → payments (cash with change, card, e-wallet, split) or charge to room
→ close check → [refund] → count drawer → Z-report
```

- **Tables**: `orders.restaurant_table_id`, `fulfillment = dine_in` (the counter is `pickup`). The register shows free and busy tables.
- **Tickets**: `OrderService::placeAtRegister()` skips the online-ordering switches and goes straight to `accepted`, so stock is used. `addLines()` works while the ticket is open and unpaid, and new lines consume stock through `OrderLinesAdded`. `applyDiscount()` (percent or fixed, with a reason, `pos.discount`) re-totals from the order's own tax snapshot.
- **Kitchen display**: `/pos/{restaurant}/kitchen` shows accepted and preparing orders from **every** channel (online, room service, dine-in) with modifiers and notes. A bump moves accepted → preparing → ready.
- **Cashier** (`PosService`):
  - Money is taken **only inside an open session**, with the ticket row locked.
  - Cash may be tendered above the balance; the difference is recorded as change.
  - Card and e-wallet amounts cannot exceed the balance.
  - The ticket becomes `paid` when the balance reaches 0. Paid tickets are frozen.
- **Charge to room**: for an unpaid ticket with no payments, choose a checked-in guest of the same business. The ticket becomes `room_charge` / `charged` and the folio posts it as **food** (room-service deliveries post as room service).
- **Close**: needs a paid or charged ticket (dine-in tables cannot be completed unpaid). It steps any kitchen states the register skipped.
- **Void**: only unpaid tickets with no payments. Cancelling an accepted order restores stock.
- **Refund** (`pos.refund`): a full refund of a paid, closed or cancelled register ticket. A negative `pos_payments` row comes out of the open drawer and the order becomes `refunded`. The online host queue cannot close or refund register tickets.
- **Receipts**: printable 80 mm layout with lines, discount, VAT, payments, tendered and change.
- **Shift management / daily closing**: one open `pos_session` per restaurant (row-locked open). Expected cash = float + cash taken − cash refunds. Counting records counted cash and the variance. The Z-report shows takings by method, refunds, net, tickets, discounts and tax.

## Tables

`pos_sessions` (restaurant, opened_by / closed_by, opening_float, expected / counted cash, variance, notes, opened_at / closed_at) and `pos_payments` (order, session, method `cash/card/ewallet/room_charge`, signed amount, tendered, change_given, reference, user). `orders` gains `channel`, `restaurant_table_id` and `discount_reason`.

## Permissions

| Permission | Roles |
|---|---|
| `pos.use` (tickets, payments, kitchen display, open register, void) | owner, manager, front desk |
| `pos.discount`, `pos.refund`, `pos.manage` (close the day, Z-reports) | owner, manager |

## Tests

`tests/Feature/PosTest.php` (5 tests): split bill with change, discount and add-lines re-totalling, frozen after paid, close; no money without a session, unpaid table cannot close, queue cannot close register tickets; charge to room → folio food line, checked-out guest refused; refund out of the drawer, void, day close with variance and the Z-report figures, closed session takes no money; HTTP register / kitchen bump / receipt / Z-report, front-desk limits, staff refused, module gating, tenant isolation.

# ordering

STATUS: COMPLETE — Phase 11 (Online food ordering). Verified 2026-09-25: full Pest suite green (171 tests / 776 assertions).

Code: `App\Modules\Ordering`. The menu it sells comes from `App\Modules\RestaurantManagement` (Phase 09).

## Flow

```text
/restaurant/{slug} menu → "Add to order" (modifiers, qty, notes) → session cart (one restaurant)
→ /cart (priced by the server, promo code) → checkout (auth): pickup | delivery, cash | online
→ order `pending` ─ online: PayMongo checkout → webhook / return → server-side lookup → payment_status paid
→ host queue: accepted → preparing → ready → completed (pickup)
                                          └→ out_for_delivery → delivered → completed (delivery)
```

States: `pending, accepted, preparing, ready, out_for_delivery, delivered, completed, cancelled, refunded` (`Order::nextStates()`).

- `pending` / `accepted` → `cancelled` (a customer can cancel only while `pending`).
- `cancelled` / `completed` → `refunded` only for orders **paid online**. The refund goes through PayMongo (`OrderTransitioning` listener). If PayMongo refuses, the transition is vetoed.
- An online order cannot be accepted until it is paid.

## Pricing rules

- The cart stores only item ids, option ids, quantity and notes. Every render and the checkout re-price through `OrderService::quote()`, which calls `MenuItem::priceWith()` per line (modifier min/max, availability, ownership). The client never sends a price.
- Unavailable items and items in hidden categories are rejected.
- Discount: an order promo code (`promotions.applies_to = orders`, optional `restaurant_id`, `min_subtotal`, `max_uses`, `ends_on`). Stay codes cannot be used for orders, and order codes cannot be used for stays.
- Tax: `restaurants.tax_rate` (default 12). With `tax_inclusive` (default, as PH menus include VAT), tax is the share inside the price: `taxable − taxable / (1 + rate)`, and the total is unchanged. With exclusive tax, it is added on top.
- `delivery_fee` is 0 until Phase 12 (delivery zones and fees).
- `orders` / `order_items` snapshot names, modifier names and prices, and tax settings. Later menu edits never change an order.

## Payments and wallet

- `payments.order_id` / `commissions.order_id` sit beside `booking_id`; exactly one of the two is set.
- `PaymentService::checkoutOrder()` shares `openCheckout()` with bookings (reuses an open session and refuses a second payment). `markPaid()` sets `orders.payment_status = paid` in the same transaction as the commission.
- Commission is resolved for the restaurant listing (restaurant rates set in Phase 08 now apply). It is released to the available balance when the order is **completed** and reversed on refund.
- Ceiling: **cash orders carry no platform commission**, because the money never passes through the platform. Invoice the host for these if the business model needs it.

## Routes / permissions

| Route | Who |
|---|---|
| `POST /restaurant/{slug}/cart`, `GET /cart`, `PATCH /cart/{key}` | anyone (session) |
| `POST /cart/checkout` | signed in |
| `/account/orders` (list, show, cancel, pay, payment-return) | the ordering customer only (`Order::forCustomer`; foreign ref → 404) |
| `/dashboard/restaurants/{slug}/orders` (queue, show, status, promo codes) | `orders.view` / `orders.manage` (owner, manager, front desk); promo codes need `promotions.manage` |

A restaurant accepts orders when it is published, has `ordering_enabled`, its business is active and the business has the `restaurant` module. Otherwise the cart endpoints return 404 and the menu has no "Add to order".

## Tests

`tests/Feature/OrderingTest.php` (9 tests): quote math (modifiers, inclusive and exclusive tax, promo with minimum, stay code rejected), cart → checkout → snapshot (menu edit afterwards), bad modifiers / unavailable item / ordering disabled, delivery rules, pickup and delivery state paths, the PayMongo online flow → commission → release → refund → reversal, refused refund keeps the order unrefunded, customer isolation and cancel rules, host queue permissions and tenant isolation.

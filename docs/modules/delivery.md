# delivery

STATUS: COMPLETE — Phase 12 (Delivery). Verified 2026-09-25: full Pest suite green (176 tests / 820 assertions).

Code: `App\Modules\Delivery` (zones, drivers, setup screen). The pricing and dispatch rules live in `App\Modules\Ordering\Services\OrderService`, because they are part of placing and moving an order.

## Zones (`delivery_zones`)

Per restaurant. There are two kinds:

- **Named area** (`radius_km` null), e.g. "Station 2". The customer picks it and no distance check is done.
- **Radius** (`radius_km` set), a circle around `restaurants.latitude/longitude`. The customer shares a location (browser geolocation button at checkout), and the haversine distance must be within the radius. No location, or a location outside the circle, is refused.

Terms per zone:

- `fee`, waived when the order (after discount) reaches `free_over`
- `min_order`, checked against the subtotal
- `eta_minutes`, the ride time
- `is_active` (paused zones cannot be chosen)

The customer can choose Delivery only when the restaurant has `delivery_enabled` and at least one active zone.

## Orders

New columns: `delivery_zone_id`, `driver_id`, `delivery_lat/lng`, `scheduled_for`, `estimated_at`, `dispatched_at`, `delivered_at`. `delivery_fee` feeds the Phase 11 totals.

| Step | Rule |
|---|---|
| Place | Zone must belong to the restaurant, be active and cover the drop-off. The fee comes from the zone. The minimum order is enforced. |
| Scheduled | Optional `scheduled_for`: at least `restaurants.prep_minutes` ahead and at most 7 days out. |
| Accepted | ETA = `scheduled_for`, or now + prep time (+ zone ride time for deliveries). |
| Driver | `assignDriver()` only for delivery orders that are accepted, preparing, ready or out for delivery. The driver must be active. It cannot be cleared once on the road. Audited. |
| Out for delivery | Refused without a driver. Sets `dispatched_at` and ETA = now + ride time. |
| Delivered | Sets `delivered_at`. |

Pickup orders have no zone, driver or ride time. Hotel room service is Phase 13.

## Drivers (`delivery_drivers`)

These are plain tenant records (name, phone, vehicle, on/off duty), shared by all the business's restaurants. Staff accounts come with Phase 17. A driver id from another business is a 404, because the lookup is tenant-scoped.

## Screens / permissions

- `/dashboard/restaurants/{slug}/delivery`: zones (add, pause, remove), prep time, restaurant coordinates, drivers. Needs `delivery.manage` (owner, manager).
- Order queue and detail: driver dropdown on delivery orders, ETA and scheduled time. Assigning a driver needs `orders.manage`.
- Checkout: area select with terms, "share my location", "schedule for later". Customer order page: ETA, driver name and phone while out for delivery, delivered time.

## Tests

`tests/Feature/DeliveryTest.php` (6 tests): fee, free-over and minimum; missing, paused or foreign zone; radius check with and without location (distance sanity: 5.5 km); driver required for dispatch, ETAs, no unassign on the road, off-duty driver, pickup takes no driver; scheduled window; cart checkout with a zone; setup screen, driver assignment, permissions, tenant isolation (foreign zone and driver → 404).

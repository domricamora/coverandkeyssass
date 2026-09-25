# restaurant

STATUS: COMPLETE — Phase 09 (Restaurant management). Verified 2026-09-25: full Pest suite green (156 tests / 621 assertions). Reservations (Phase 10) and ordering (Phase 11) build on this.

Code: `App\Modules\RestaurantManagement` (host side). The marketplace listing itself is `App\Modules\Marketplace\Models\Restaurant`.

## Structure

```text
Restaurant ─┬─ MenuCategory ── MenuItem ── ModifierGroup ── ModifierOption
            ├─ DiningArea ── RestaurantTable
            └─ media (photos, cover)   opening_hours (JSON)   cuisines
```

Menu example (master plan):

```text
Burger ₱250          → MenuItem price 250
  Add-ons            → ModifierGroup min 0, no max
    Cheese +₱30      → ModifierOption
    Bacon  +₱50
    Egg    +₱25
```

## Models (`App\Modules\RestaurantManagement\Models`)

| Model | Table | Notes |
|---|---|---|
| `MenuCategory` | `menu_categories` | Unique name per restaurant; `is_active` hides it from the public menu. Delete refused while items remain. |
| `MenuItem` | `menu_items` | Price + currency, optional photo URL, `is_available` ("Sold out" publicly). `priceWith(optionIds)` returns the unit price with modifiers. |
| `ModifierGroup` | `modifier_groups` | Per item. `min_select` > 0 = required modifier; `max_select` null = no limit. |
| `ModifierOption` | `modifier_options` | Price delta (0 allowed), `is_available`. |
| `DiningArea` | `dining_areas` | Unique name per restaurant. Deleting an area leaves its tables unassigned. |
| `RestaurantTable` | `restaurant_tables` | Label unique per restaurant, seats, `active/inactive`. |

All rows are tenant-owned (`BelongsToTenant`) and hard-deleted; orders (Phase 11) must snapshot item names and prices.

## Pricing rule

`MenuItem::priceWith(array $optionIds)` is the one server-side price calculation. It throws a `ValidationException` (`modifiers` key) when:

- an option does not belong to the item,
- an option is unavailable,
- a group gets fewer than `min_select` or more than `max_select` choices.

Ordering must use it and never trust a client total.

## Routes / permissions

Prefix `/dashboard/restaurants`, names `restaurants.*`, middleware `auth`, `tenant.context`, `module.active:restaurant`.

| Area | Routes | Permission |
|---|---|---|
| Profile, hours, cuisines, photos | index/create/store/show/edit/update/destroy, media.* | `restaurants.view/create/update/delete` |
| Publish lifecycle | publish, unpublish (audited) | `restaurants.publish` |
| Menu builder | `menu`, categories.*, items.*, groups.*, options.* | `menu.view` / `menu.manage` |
| Floor plan | `tables`, areas.*, tables.* | `tables.view` / `tables.manage` |

Roles: owner and manager hold everything (the manager cannot delete restaurants); front desk has `restaurants.view`, `menu.view`, `tables.view`.

Parameters are resolved through the tenant-scoped restaurant (`RestaurantManagementController::resolveRestaurant`), so a foreign restaurant, or a foreign item id under your own restaurant, returns 404.

## Public surface

`/restaurant/{slug}` now shows the menu: active categories that have items, prices, "Sold out" for unavailable items, and available modifier options. It drops the tenant scope only on eager loads under a listing that `publicQuery()` already limited to published restaurants.

## Reservations (Phase 10)

Verified 2026-09-25: suite green (162 tests / 680 assertions), plus a live 8-process race for one table: exactly one reservation and seven clean rejections.

`TableReservation` (`table_reservations`): one party at one table for `[reserved_at, ends_at)`, with a `TR…` reference.

```text
pending → confirmed → seated → completed
   ↘ cancelled   ↘ cancelled / no_show
```

- **Time slots**: `ReservationService::slotsFor()` reads the day's opening hours (`"11:00–14:00, 17:00–22:00"`, en dash or hyphen, several ranges, past midnight). It emits a slot every 30 minutes, and the last slot leaves a full sitting (`restaurants.reservation_duration_minutes`, default 90) before closing. A missing or "Closed" day has no slots. Marketplace requests must land on a slot. Host reservations can use any time.
- **Overbooking**: `reserve()` locks the restaurant's active tables (`FOR UPDATE`) and then excludes tables that have an overlapping `pending/confirmed/seated` reservation. It picks the smallest table that seats the party (or the table the host chose). Time ranges cannot carry a unique index, so the lock is the guarantee (proven by the race).
- **Guards**: seating only on the reservation day; no-show only after the reserved time; completing early shortens `ends_at`, so the table frees up.
- **Sources**: `host` is confirmed immediately; `marketplace` is pending until the host confirms. The customer gets a database notification on confirm or cancel.
- **Customer**: `/account/reservations` (the "Tables" tab) lists only their own rows (`TableReservation::forCustomer`). They can self-cancel before the reserved time. Another customer's reference returns 404.
- **Public**: `/restaurant/{slug}` shows "Book a table" only when `reservations_enabled` is on and the business has the restaurant module. The slot picker calls `GET /restaurant/{slug}/slots?date=`. The request is `POST /restaurant/{slug}/reserve` (auth).
- **Host desk**: `restaurants.reservations` shows a day calendar per table, the booking list with state actions, and a phone-booking form. Permissions: `reservations.view` / `reservations.manage` (owner, manager, front desk).

Ceilings: one table per party (no table combining); fixed 30-minute grid.

## Tests

`tests/Feature/RestaurantReservationsTest.php` (6 tests): slot derivation, allocation without overbooking (smallest fit, back-to-back, cancel frees, too-large party, no overlapping rows), state machine and date guards, marketplace request rules, customer isolation and cancel, host desk permissions and tenant isolation.

`tests/Feature/RestaurantManagementTest.php` (10 tests): module gating, create with hours and cuisines plus publish, cross-tenant 404s (including a foreign item id), front-desk read-only access, the menu builder flow, rejecting another restaurant's category, `priceWith` (the add-on example, a required single choice, a foreign option, an unavailable option), availability toggle, the floor plan, and the public menu.

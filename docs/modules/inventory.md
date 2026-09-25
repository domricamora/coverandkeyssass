# inventory

STATUS: COMPLETE — Phase 18 (Inventory). Verified 2026-09-26: full Pest suite green (209 tests / 1122 assertions).

Code: `App\Modules\Inventory`, gated by the **inventory** module (`module.active:inventory`). It covers both hotels (amenities, linen) and restaurants (ingredients).

## Catalogue

| Model | Table | Notes |
|---|---|---|
| `InventoryCategory` | `inventory_categories` | Unique per business. |
| `StockLocation` | `stock_locations` | "Main store", "Kitchen" (optional property). |
| `Supplier` | `suppliers` | Contact details. |
| `InventoryItem` | `inventory_items` | SKU (unique, upper-cased), name, **unit**, weighted-average `cost_per_unit`, `reorder_level`, active. |
| `StockLevel` | `stock_levels` | On-hand per item × location (unique pair). |
| `StockMovement` | `stock_movements` | Append-only signed ledger: receipt, issue, adjustment, transfer_in / out, waste, sale, sale_return. Stores `balance_after`, unit cost, reference, user, and a unique `source_key` for derived moves. |
| `PurchaseOrder` / `PurchaseOrderLine` | `purchase_orders` / `purchase_order_lines` | `PO…` reference, supplier, delivery location, lines (qty, unit cost, received). |
| `MenuItemIngredient` | `menu_item_ingredients` | Recipe line: menu item → stock item, quantity in the stock unit (plus the unit as entered). |

**Units** (`Support\Unit`): pc, dozen, pack, bottle, g, kg, ml, l. Conversion works within a dimension (g↔kg, ml↔l, pc↔dozen), so "150 g" of beef stocked in kg is saved as 0.15 kg. Incompatible units are refused.

## Rules (`InventoryService::move()`)

- Every change locks the **item row, then the level row** (always in that order, so no deadlocks). It writes one ledger line with the balance after it, so the ledger always sums to the level.
- Stock cannot go below zero, **except for sales**: the kitchen is never blocked. The level shows negative until someone counts it.
- **Receipts** update the weighted-average cost.
- **Count**: books the difference as an `adjustment`.
- **Transfer**: out and in, in one transaction.
- **Waste**: needs a reason.
- **Low-stock alert**: when an item's total **crosses** its reorder level, every member with `inventory.manage` gets a `LowStock` database notification (once per crossing). The list has a "low on stock" filter (`InventoryItem::lowStock()`).
- **Purchase orders**:
  - draft → ordered → partially_received → received. Draft or ordered can be cancelled.
  - Blank lines are dropped.
  - Receiving books receipts at the line cost, and cannot exceed what is outstanding.

## Menu ingredients (restaurants)

A restaurant is linked to a kitchen `stock_location_id` on the Recipes screen. Then:

- When the kitchen **accepts** an order (`OrderTransitioned` → accepted), each line uses recipe × quantity as `sale` moves. They are keyed `order:{id}:line:{line}:item:{item}`, so replays are ignored.
- **Cancelling an accepted order** books `sale_return` moves. Food already being prepared cannot be cancelled (order state machine).
- A restaurant without a stock location, or menu items without recipes, never touch stock.

Example (master plan): Burger = 1 bun + 150 g beef + 1 cheese. Accepting 2 burgers uses 2 buns, 0.3 kg beef and 2 cheese slices.

## Screens / permissions

- `/dashboard/inventory`: items with on-hand, reorder level and average cost (low items flagged), plus new item, categories, locations and suppliers.
- `/inventory/items/{id}`: levels by location, the ledger, movement form (receive / issue / waste / count / transfer), item settings.
- `/inventory/purchase-orders`: list and new PO (dynamic lines). `/{ref}`: mark ordered, receive quantities, cancel.
- `/inventory/recipes?restaurant=`: kitchen location and ingredients per menu item.

| Permission | Roles |
|---|---|
| `inventory.view`, `inventory.manage`, `purchasing.manage` | owner, manager |

## Tests

`tests/Feature/InventoryTest.php` (6 tests): weighted cost, issue / waste / count, ledger sums to the level; transfers; low-stock notification only when crossing; purchase order draft → partial → full at cost, over-receipt and cancel rules; recipe unit conversion, accept deducts, idempotent replay, cancel restores, negative stock for sales; screens, SKU uniqueness, waste reason, front desk refused, module gating, cross-tenant 404.

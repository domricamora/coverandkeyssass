<?php

use App\Models\Module;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\PurchaseOrder;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Supplier;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\RestaurantManagement\Models\MenuCategory;
use App\Modules\RestaurantManagement\Models\MenuItem;
use App\Support\ModuleService;
use Illuminate\Validation\ValidationException;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 18 (Inventory) — ledgered stock moves with weighted cost, transfers,
| waste, counts, low-stock alerts, purchase orders, recipes with unit
| conversion, food orders consuming / restoring stock, screens.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    PropertyManagementFixtures::bootstrap();
    [$this->owner, $this->tenant] = MarketplaceFixtures::business('Kitchen A');
    foreach (['inventory', 'restaurant'] as $slug) {
        app(ModuleService::class)->enableForTenant(Module::query()->where('slug', $slug)->firstOrFail(), $this->tenant);
    }
    MarketplaceFixtures::asTenant($this->tenant);

    $this->store = StockLocation::create(['name' => 'Main store']);
    $this->kitchen = StockLocation::create(['name' => 'Kitchen']);
    $this->beef = InventoryItem::create(['sku' => 'BEEF', 'name' => 'Ground beef', 'unit' => 'kg', 'reorder_level' => 5]);
    $this->bun = InventoryItem::create(['sku' => 'BUN', 'name' => 'Burger bun', 'unit' => 'pc']);
});

function inv(): InventoryService
{
    return app(InventoryService::class);
}

function onHand(InventoryItem $item, StockLocation $location): float
{
    return (float) StockLevel::query()->where('inventory_item_id', $item->id)->where('stock_location_id', $location->id)->value('quantity');
}

it('keeps a ledger with weighted average cost, waste and counts', function () {
    inv()->receive($this->beef, $this->store, 10, 400, $this->owner);
    inv()->receive($this->beef, $this->store, 10, 500, $this->owner);
    expect((float) $this->beef->refresh()->cost_per_unit)->toBe(450.0);

    inv()->issue($this->beef, $this->store, 3, $this->owner, 'Staff meal');
    inv()->waste($this->beef, $this->store, 0.5, 'Spoiled', $this->owner);
    expect(fn () => inv()->issue($this->beef, $this->store, 50, $this->owner))->toThrow(ValidationException::class)
        ->and(fn () => inv()->receive($this->beef, $this->store, 0, 1, $this->owner))->toThrow(ValidationException::class);

    expect(inv()->count($this->beef, $this->store, 15, $this->owner)->quantity)->toEqual('-1.500')
        ->and(inv()->count($this->beef, $this->store, 15, $this->owner))->toBeNull()
        ->and(onHand($this->beef, $this->store))->toBe(15.0);

    // The ledger adds up to the level, and each line carries the running balance.
    $movements = StockMovement::query()->where('inventory_item_id', $this->beef->id)->orderBy('id')->get();
    expect((float) $movements->sum('quantity'))->toBe(15.0)
        ->and((float) $movements->last()->balance_after)->toBe(15.0)
        ->and($movements->pluck('type')->all())->toBe(['receipt', 'receipt', 'issue', 'waste', 'adjustment']);
});

it('transfers between locations without creating stock', function () {
    inv()->receive($this->bun, $this->store, 100, 8, $this->owner);
    inv()->transfer($this->bun, $this->store, $this->kitchen, 40, $this->owner);

    expect(onHand($this->bun, $this->store))->toBe(60.0)
        ->and(onHand($this->bun, $this->kitchen))->toBe(40.0)
        ->and(fn () => inv()->transfer($this->bun, $this->kitchen, $this->store, 41, $this->owner))->toThrow(ValidationException::class)
        ->and(fn () => inv()->transfer($this->bun, $this->store, $this->store, 1, $this->owner))->toThrow(ValidationException::class)
        ->and($this->bun->refresh()->totalStock())->toBe(100.0);
});

it('alerts managers once when stock falls to the reorder level', function () {
    inv()->receive($this->beef, $this->store, 8, 400, $this->owner);
    expect(InventoryItem::query()->lowStock()->count())->toBe(0);

    inv()->issue($this->beef, $this->store, 3, $this->owner);   // 5 = reorder level → alert
    inv()->issue($this->beef, $this->store, 1, $this->owner);   // already low → no new alert

    expect($this->owner->notifications()->count())->toBe(1)
        ->and($this->owner->notifications()->first()->data['message'])->toContain('Ground beef')
        ->and(InventoryItem::query()->lowStock()->pluck('sku')->all())->toBe(['BEEF']);
});

it('orders from suppliers and receives in parts at the ordered cost', function () {
    $supplier = Supplier::create(['name' => 'Island Meats']);
    $po = inv()->createPurchaseOrder($supplier, $this->store, [
        ['inventory_item_id' => $this->beef->id, 'quantity' => 20, 'unit_cost' => 420],
        ['inventory_item_id' => $this->bun->id, 'quantity' => 200, 'unit_cost' => 7.5],
        ['inventory_item_id' => $this->bun->id, 'quantity' => 0, 'unit_cost' => 1], // blank line ignored
    ], $this->owner);

    expect((float) $po->total)->toBe(9900.0)->and($po->lines()->count())->toBe(2)
        ->and(fn () => inv()->receivePurchaseOrder($po, [], $this->owner))->toThrow(ValidationException::class); // still a draft

    inv()->markOrdered($po);
    [$beefLine, $bunLine] = $po->lines()->orderBy('id')->get()->all();

    inv()->receivePurchaseOrder($po->refresh(), [$beefLine->id => 12], $this->owner);
    expect($po->refresh()->status)->toBe(PurchaseOrder::PARTIAL)
        ->and(fn () => inv()->receivePurchaseOrder($po, [$beefLine->id => 9], $this->owner))->toThrow(ValidationException::class); // only 8 left

    inv()->receivePurchaseOrder($po, [$beefLine->id => 8, $bunLine->id => 200], $this->owner);
    expect($po->refresh()->status)->toBe(PurchaseOrder::RECEIVED)
        ->and(onHand($this->beef, $this->store))->toBe(20.0)
        ->and((float) $this->beef->refresh()->cost_per_unit)->toBe(420.0)
        ->and(fn () => inv()->cancelPurchaseOrder($po))->toThrow(ValidationException::class);
});

it('deducts recipe ingredients when the kitchen accepts an order and restores them on cancel', function () {
    $restaurant = MarketplaceFixtures::restaurant($this->tenant, $this->owner, ['status' => Restaurant::STATUS_PUBLISHED, 'ordering_enabled' => true, 'tax_rate' => 12, 'tax_inclusive' => true]);
    MarketplaceFixtures::asTenant($this->tenant);
    $restaurant->forceFill(['stock_location_id' => $this->kitchen->id])->save();
    $cheese = InventoryItem::create(['sku' => 'CHS', 'name' => 'Cheese slice', 'unit' => 'pc']);
    $burger = MenuItem::create(['restaurant_id' => $restaurant->id, 'menu_category_id' => MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Burgers'])->id, 'name' => 'Burger', 'price' => 250]);

    // Burger requires: 1 bun, 150 g beef, 1 cheese.
    inv()->setIngredient($burger, $this->bun, 1, 'pc');
    expect((float) inv()->setIngredient($burger, $this->beef, 150, 'g')->quantity)->toBe(0.15);
    inv()->setIngredient($burger, $cheese, 1, 'pc');
    expect(fn () => inv()->setIngredient($burger, $this->beef, 1, 'pc'))->toThrow(ValidationException::class); // pc → kg

    foreach ([[$this->bun, 20], [$this->beef, 5], [$cheese, 20]] as [$item, $qty]) {
        inv()->receive($item, $this->kitchen, $qty, 10, $this->owner);
    }

    $orders = app(OrderService::class);
    $place = fn () => $orders->place($restaurant->refresh(), [['item_id' => $burger->id, 'quantity' => 2]], ['fulfillment' => Order::PICKUP, 'payment_method' => Order::PAY_CASH, 'customer_name' => 'Ana'], null);

    $order = $place();
    expect(onHand($this->bun, $this->kitchen))->toBe(20.0); // placing does not touch stock

    $orders->transition($order, Order::ACCEPTED);
    expect(onHand($this->bun, $this->kitchen))->toBe(18.0)
        ->and(onHand($this->beef, $this->kitchen))->toBe(4.7)
        ->and(onHand($cheese, $this->kitchen))->toBe(18.0);

    inv()->consumeOrder($order); // replay → idempotent
    expect(onHand($this->bun, $this->kitchen))->toBe(18.0);

    $orders->transition($order, Order::CANCELLED);
    expect(onHand($this->bun, $this->kitchen))->toBe(20.0)->and(onHand($this->beef, $this->kitchen))->toBe(5.0);

    // Selling past the count never blocks the kitchen: the level goes negative until counted.
    inv()->count($this->bun, $this->kitchen, 1, $this->owner);
    $orders->transition($place(), Order::ACCEPTED);
    expect(onHand($this->bun, $this->kitchen))->toBe(-1.0);
});

it('runs the inventory screens with permissions, gating and isolation', function () {
    PropertyManagementFixtures::login($this->owner, $this->tenant);

    $this->post(route('inventory.items.store'), ['sku' => 'soap-1', 'name' => 'Bath soap', 'unit' => 'pc', 'reorder_level' => 50])->assertRedirect();
    $soap = InventoryItem::query()->where('sku', 'SOAP-1')->firstOrFail();
    $this->post(route('inventory.items.store'), ['sku' => 'SOAP-1', 'name' => 'Dup', 'unit' => 'pc'])->assertSessionHasErrors('sku');

    $this->post(route('inventory.items.move', $soap->id), ['action' => 'receive', 'location_id' => $this->store->id, 'quantity' => 200, 'unit_cost' => 12])->assertSessionHasNoErrors();
    $this->post(route('inventory.items.move', $soap->id), ['action' => 'waste', 'location_id' => $this->store->id, 'quantity' => 2])->assertSessionHasErrors('notes');
    $this->post(route('inventory.items.move', $soap->id), ['action' => 'transfer', 'location_id' => $this->store->id, 'to_location_id' => $this->kitchen->id, 'quantity' => 20])->assertSessionHasNoErrors();

    $this->get(route('inventory.index'))->assertOk()->assertSee('Bath soap')->assertSee('200 pc');
    $this->get(route('inventory.items.show', $soap->id))->assertOk()->assertSee('Transfer Out')->assertSee('180 pc');
    $this->get(route('inventory.purchase-orders.index'))->assertOk();
    $this->get(route('inventory.recipes'))->assertOk();

    // Front desk has no inventory access.
    PropertyManagementFixtures::login(MarketplaceFixtures::member($this->tenant, 'front_desk'), $this->tenant);
    $this->get(route('inventory.index'))->assertForbidden();

    // A business without the module is refused; another business's rows are 404.
    [$ownerB, $tenantB] = MarketplaceFixtures::business('Kitchen B');
    PropertyManagementFixtures::login($ownerB, $tenantB);
    $this->get(route('inventory.index'))->assertForbidden();
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'inventory')->firstOrFail(), $tenantB);
    $this->get(route('inventory.items.show', $soap->id))->assertNotFound();
    $this->post(route('inventory.items.move', $soap->id), ['action' => 'issue', 'location_id' => $this->store->id, 'quantity' => 1])->assertNotFound();
});

<?php

use App\Models\Module;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Models\Order;
use App\Modules\Pos\Services\PosService;
use App\Modules\RestaurantManagement\Models\MenuCategory;
use App\Modules\RestaurantManagement\Models\MenuItem;
use App\Modules\RestaurantManagement\Models\RestaurantTable;
use App\Support\ModuleService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;

/*
| Restaurant floor (React/Inertia, phase 2 of the dashboard rebuild): table
| plan, kitchen rail, seat-and-order from a table (with modifiers), pay,
| close to free the table, tenant isolation.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    $this->travelTo(CarbonImmutable::parse('2030-09-05 19:00'));

    [$this->owner, $this->tenant] = BookingFixtures::hotel();
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'pos')->firstOrFail(), $this->tenant);

    $this->restaurant = MarketplaceFixtures::restaurant($this->tenant, $this->owner, ['status' => Restaurant::STATUS_PUBLISHED, 'tax_rate' => 12, 'tax_inclusive' => true]);
    MarketplaceFixtures::asTenant($this->tenant);
    $this->table = RestaurantTable::create(['restaurant_id' => $this->restaurant->id, 'label' => 'T1', 'seats' => 4, 'status' => 'active']);
    $this->burger = MenuItem::create(['restaurant_id' => $this->restaurant->id, 'menu_category_id' => MenuCategory::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Mains'])->id, 'name' => 'Burger', 'price' => 250]);
    $this->cheese = $this->burger->modifierGroups()->create(['name' => 'Add-ons'])->options()->create(['name' => 'Cheese', 'price' => 30]);
});

it('shows the table plan and menu, seats a table and sends the order to the kitchen', function () {
    $this->get(route('floor.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('RestaurantFloor/Index')
            ->where('areas.0.tables.0.label', 'T1')
            ->where('areas.0.tables.0.ticket', null)
            ->where('menu.0.items.0.name', 'Burger')
            ->where('menu.0.items.0.groups.0.options.0.name', 'Cheese'));

    $this->post(route('floor.tickets.store', $this->restaurant->slug), [
        'restaurant_table_id' => $this->table->id,
        'lines' => [
            ['item_id' => $this->burger->id, 'options' => [$this->cheese->id], 'quantity' => 2, 'notes' => 'no onions'],
            ['item_id' => $this->burger->id, 'quantity' => 1],
        ],
    ])->assertRedirect();

    $order = Order::query()->sole();
    expect($order->restaurant_table_id)->toBe($this->table->id)
        ->and($order->items)->toHaveCount(2)
        ->and((float) $order->total)->toBe(810.0); // 2 × (250 + 30) + 250

    $this->get(route('floor.index', ['ticket' => $order->reference]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('areas.0.tables.0.ticket.reference', $order->reference)
            ->where('kitchen.0.where', 'Table T1')
            ->where('kitchen.0.items.0.mods', 'Cheese')
            ->where('ticket.due', 810)
            ->where('ticket.items.0.notes', 'no onions'));
});

it('takes payment and closes the ticket, freeing the table', function () {
    $this->post(route('floor.tickets.store', $this->restaurant->slug), ['restaurant_table_id' => $this->table->id, 'lines' => [['item_id' => $this->burger->id, 'quantity' => 1]]]);
    $order = Order::query()->sole();
    app(PosService::class)->openSession($this->restaurant, 1000, $this->owner);

    $this->post(route('pos.tickets.pay', [$this->restaurant->slug, $order->reference]), ['method' => 'cash', 'tendered' => 500])->assertRedirect();
    $this->post(route('floor.tickets.close', [$this->restaurant->slug, $order->reference]))->assertRedirect(route('floor.index', ['restaurant' => $this->restaurant->slug]));

    expect($order->refresh()->status)->toBe(Order::COMPLETED);

    $this->get(route('floor.index'))->assertInertia(fn (Assert $page) => $page->where('areas.0.tables.0.ticket', null)->where('stats.sales', 250));
});

it('keeps another business out of this restaurant', function () {
    [, $tenantB] = BookingFixtures::hotel(name: 'Hotel B'); // now signed in as Hotel B's owner
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'pos')->firstOrFail(), $tenantB);

    $this->post(route('floor.tickets.store', $this->restaurant->slug), ['lines' => [['item_id' => $this->burger->id, 'quantity' => 1]]])->assertNotFound();
    $this->get(route('floor.index', ['restaurant' => $this->restaurant->slug]))->assertNotFound();
});

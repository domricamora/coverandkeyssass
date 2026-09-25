<?php

use App\Models\Module;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Folio\Services\FolioService;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\Pos\Models\PosPayment;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Pos\Services\PosService;
use App\Modules\RestaurantManagement\Models\MenuCategory;
use App\Modules\RestaurantManagement\Models\MenuItem;
use App\Modules\RestaurantManagement\Models\RestaurantTable;
use App\Support\ModuleService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 19 (POS) — tables, tickets to the kitchen, split payments with
| change, discounts, charge to room (folio), close, void, refunds, cash
| sessions with variance and Z-report, kitchen display, permissions.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    $this->travelTo(CarbonImmutable::parse('2030-09-05 19:00'));

    [$this->owner, $this->tenant, $this->property, $this->type] = BookingFixtures::hotel();
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'pos')->firstOrFail(), $this->tenant); // pulls in restaurant

    $this->restaurant = MarketplaceFixtures::restaurant($this->tenant, $this->owner, ['status' => Restaurant::STATUS_PUBLISHED, 'tax_rate' => 12, 'tax_inclusive' => true]);
    MarketplaceFixtures::asTenant($this->tenant);
    $this->table = RestaurantTable::create(['restaurant_id' => $this->restaurant->id, 'label' => 'T1', 'seats' => 4, 'status' => 'active']);
    $this->burger = MenuItem::create(['restaurant_id' => $this->restaurant->id, 'menu_category_id' => MenuCategory::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Mains'])->id, 'name' => 'Burger', 'price' => 250]);
    $this->cheese = $this->burger->modifierGroups()->create(['name' => 'Add-ons'])->options()->create(['name' => 'Cheese', 'price' => 30]);
    $this->restaurant->refresh();
});

function posService(): PosService
{
    return app(PosService::class);
}

function posTicket(array $lines, ?RestaurantTable $table = null): Order
{
    return app(OrderService::class)->placeAtRegister(test()->restaurant, $lines, $table ?? test()->table, test()->owner);
}

it('rings up a table, splits the bill with change and closes the check', function () {
    $session = posService()->openSession($this->restaurant, 2000, $this->owner);
    expect(fn () => posService()->openSession($this->restaurant, 0, $this->owner))->toThrow(ValidationException::class);

    $order = posTicket([['item_id' => $this->burger->id, 'option_ids' => [$this->cheese->id], 'quantity' => 2]]);
    expect($order->status)->toBe(Order::ACCEPTED)->and($order->channel)->toBe('pos')
        ->and($order->fulfillment)->toBe(Order::DINE_IN)->and((float) $order->total)->toBe(560.0);

    app(OrderService::class)->addLines($order, [['item_id' => $this->burger->id, 'quantity' => 1]]);
    app(OrderService::class)->applyDiscount($order->refresh(), 'percent', 10, 'Senior citizen');
    expect((float) $order->refresh()->total)->toBe(729.0) // (560 + 250) − 10%
        ->and((float) $order->tax_total)->toBe(78.11);

    expect(fn () => posService()->pay($order, 'card', 800, $this->owner))->toThrow(ValidationException::class); // above balance
    posService()->pay($order, 'card', 229, $this->owner, reference: 'AUTH-1');
    $cash = posService()->pay($order->refresh(), 'cash', 500, $this->owner, tendered: 1000);

    expect((float) $cash->amount)->toBe(500.0)->and((float) $cash->change_given)->toBe(500.0)
        ->and($order->refresh()->payment_status)->toBe(Order::PAID)
        ->and(posService()->balanceDue($order))->toBe(0.0)
        ->and(fn () => app(OrderService::class)->addLines($order, [['item_id' => $this->burger->id, 'quantity' => 1]]))->toThrow(ValidationException::class);

    posService()->close($order);
    expect($order->refresh()->status)->toBe(Order::COMPLETED)
        ->and($session->cashInDrawer())->toBe(2500.0);
});

it('never takes money without an open register and never closes an unpaid table', function () {
    $order = posTicket([['item_id' => $this->burger->id, 'quantity' => 1]]);

    expect(fn () => posService()->pay($order, 'cash', 250, $this->owner, tendered: 250))->toThrow(ValidationException::class);

    app(OrderService::class)->transition($order, Order::PREPARING);
    app(OrderService::class)->transition($order, Order::READY);
    expect(fn () => app(OrderService::class)->transition($order, Order::COMPLETED))->toThrow(ValidationException::class);

    // The host order queue cannot close or refund register tickets.
    PropertyManagementFixtures::login($this->owner, $this->tenant);
    $this->post(route('restaurants.orders.transition', [$this->restaurant, $order->reference]), ['status' => 'completed'])->assertSessionHasErrors('status');
});

it('charges an in-house guest room and puts it on the folio as food', function () {
    $guestStay = BookingFixtures::reserve($this->property, $this->type, ['check_in' => '2030-09-05', 'check_out' => '2030-09-07']);
    app(BookingService::class)->transition($guestStay, Booking::CHECKED_IN);
    posService()->openSession($this->restaurant, 0, $this->owner);

    $order = posTicket([['item_id' => $this->burger->id, 'quantity' => 2]]);
    posService()->chargeToRoom($order, $guestStay, $guestStay->rooms()->value('room_id'), $this->owner);

    expect($order->refresh()->payment_status)->toBe(Order::CHARGED)
        ->and($order->payment_method)->toBe(Order::PAY_ROOM)
        ->and(fn () => posService()->pay($order, 'cash', 1, $this->owner, tendered: 1))->toThrow(ValidationException::class);

    app(FolioService::class)->sync($guestStay);
    expect(app(FolioService::class)->totals($guestStay)['by_category']['food'])->toBe(500.0);

    // Checked-out guests cannot sign.
    $gone = BookingFixtures::reserve($this->property, $this->type, ['check_in' => '2030-09-10', 'check_out' => '2030-09-11']);
    $other = posTicket([['item_id' => $this->burger->id, 'quantity' => 1]], RestaurantTable::create(['restaurant_id' => $this->restaurant->id, 'label' => 'T2', 'seats' => 2, 'status' => 'active']));
    expect(fn () => posService()->chargeToRoom($other, $gone, $gone->rooms()->value('room_id'), $this->owner))->toThrow(ValidationException::class);
});

it('refunds a closed ticket out of the drawer and closes the day with a variance', function () {
    $session = posService()->openSession($this->restaurant, 1000, $this->owner);
    $order = posTicket([['item_id' => $this->burger->id, 'quantity' => 2]]);
    posService()->pay($order, 'cash', 500, $this->owner, tendered: 500);
    posService()->close($order->refresh());

    $void = posTicket([['item_id' => $this->burger->id, 'quantity' => 1]], RestaurantTable::create(['restaurant_id' => $this->restaurant->id, 'label' => 'T3', 'seats' => 2, 'status' => 'active']));
    app(OrderService::class)->transition($void, Order::CANCELLED);

    posService()->refund($order->refresh(), 'cash', 'Cold food', $this->owner);
    expect($order->refresh()->status)->toBe(Order::REFUNDED)
        ->and((float) PosPayment::query()->where('order_id', $order->id)->sum('amount'))->toBe(0.0)
        ->and($session->cashInDrawer())->toBe(1000.0)
        ->and(fn () => posService()->refund($order, 'cash', 'again', $this->owner))->toThrow(ValidationException::class);

    $keep = posTicket([['item_id' => $this->burger->id, 'quantity' => 1]]);
    posService()->pay($keep, 'ewallet', 250, $this->owner);
    posService()->pay(posTicket([['item_id' => $this->burger->id, 'quantity' => 1]], RestaurantTable::create(['restaurant_id' => $this->restaurant->id, 'label' => 'T4', 'seats' => 2, 'status' => 'active'])), 'cash', 250, $this->owner, tendered: 300);

    $closed = posService()->closeSession($session, 1240, $this->owner, 'Short 10');
    expect((float) $closed->expected_cash)->toBe(1250.0)->and((float) $closed->variance)->toBe(-10.0);

    $report = posService()->report($closed);
    expect($report['by_method'])->toBe(['cash' => 750.0, 'ewallet' => 250.0])
        ->and($report['refunds'])->toBe(500.0)
        ->and($report['net'])->toBe(500.0)
        ->and(fn () => posService()->closeSession($closed, 0, $this->owner))->toThrow(ValidationException::class)
        ->and(fn () => posService()->pay($keep->refresh(), 'cash', 1, $this->owner, tendered: 1))->toThrow(ValidationException::class);
});

it('runs the register, tickets, kitchen display and Z-report over HTTP with permissions', function () {
    PropertyManagementFixtures::login($this->owner, $this->tenant);

    $this->post(route('pos.sessions.open', $this->restaurant), ['opening_float' => 500])->assertSessionHasNoErrors();
    $this->post(route('pos.tickets.store', $this->restaurant), ['restaurant_table_id' => $this->table->id, 'item_id' => $this->burger->id, 'options' => [$this->cheese->id], 'quantity' => 1])->assertRedirect();
    $order = Order::query()->where('channel', 'pos')->firstOrFail();

    $this->get(route('pos.register', $this->restaurant))->assertOk()->assertSee('T1')->assertSee($order->reference);
    $this->get(route('pos.kitchen', $this->restaurant))->assertOk()->assertSee('Table T1')->assertSee('Cheese');
    $this->post(route('pos.kitchen.bump', [$this->restaurant, $order->reference]))->assertRedirect();
    expect($order->refresh()->status)->toBe(Order::PREPARING);

    $this->post(route('pos.tickets.pay', [$this->restaurant, $order->reference]), ['method' => 'cash', 'tendered' => 300])->assertSessionHasNoErrors();
    $this->post(route('pos.tickets.close', [$this->restaurant, $order->reference]))->assertRedirect(route('pos.register', $this->restaurant));
    $this->get(route('pos.tickets.receipt', [$this->restaurant, $order->reference]))->assertOk()->assertSee('TOTAL')->assertSee('280.00')->assertSee('20.00');

    $session = PosSession::query()->firstOrFail();
    $this->post(route('pos.sessions.close', [$this->restaurant, $session->id]), ['counted_cash' => 780])->assertRedirect();
    $this->get(route('pos.sessions.show', [$this->restaurant, $session->id]))->assertOk()->assertSee('₱780.00');

    // Front desk can ring up but not discount or close the day; staff cannot use the POS.
    PropertyManagementFixtures::login($desk = MarketplaceFixtures::member($this->tenant, 'front_desk'), $this->tenant);
    $this->post(route('pos.sessions.open', $this->restaurant), ['opening_float' => 0])->assertSessionHasNoErrors();
    $this->post(route('pos.tickets.store', $this->restaurant), ['item_id' => $this->burger->id, 'quantity' => 1])->assertRedirect();
    $counter = Order::query()->where('channel', 'pos')->latest('id')->firstOrFail();
    expect($counter->fulfillment)->toBe(Order::PICKUP)->and($counter->restaurant_table_id)->toBeNull();
    $this->post(route('pos.tickets.discount', [$this->restaurant, $counter->reference]), ['type' => 'fixed', 'value' => 50, 'reason' => 'x'])->assertForbidden();
    $this->post(route('pos.sessions.close', [$this->restaurant, PosSession::query()->whereNull('closed_at')->value('id')]), ['counted_cash' => 0])->assertForbidden();
    $this->post(route('pos.tickets.cancel', [$this->restaurant, $counter->reference]))->assertRedirect();
    expect($counter->refresh()->status)->toBe(Order::CANCELLED);

    PropertyManagementFixtures::login(MarketplaceFixtures::member($this->tenant, 'staff'), $this->tenant);
    $this->get(route('pos.register', $this->restaurant))->assertForbidden();

    // Restaurant module alone is not enough; another business gets 404s.
    [$ownerB, $tenantB] = MarketplaceFixtures::business('Kitchen B');
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'restaurant')->firstOrFail(), $tenantB);
    PropertyManagementFixtures::login($ownerB, $tenantB);
    $this->get(route('pos.register', $this->restaurant))->assertForbidden();
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'pos')->firstOrFail(), $tenantB);
    $this->get(route('pos.register', $this->restaurant))->assertNotFound();
});

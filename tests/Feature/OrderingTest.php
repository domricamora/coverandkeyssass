<?php

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Booking\Models\Promotion;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\Payments\Models\Payment;
use App\Modules\RestaurantManagement\Models\MenuCategory;
use App\Modules\RestaurantManagement\Models\MenuItem;
use App\Modules\Wallet\Models\Commission;
use App\Modules\Wallet\Models\Wallet;
use App\Support\ModuleService;
use App\Support\TenantContext;
use Illuminate\Validation\ValidationException;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PayMongoFake;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 11 (Online Food Ordering) — cart, server-side pricing with modifiers,
| discounts and taxes, checkout (cash / PayMongo), the order state machine,
| commissions, refunds, customer isolation and the host queue.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    PropertyManagementFixtures::bootstrap();
    PayMongoFake::configure();
});

/** @return array{0: User, 1: Tenant, 2: Restaurant, 3: MenuItem, 4: array<string, int>} */
function kitchen(array $attributes = []): array
{
    [$owner, $tenant] = MarketplaceFixtures::business('Kitchen A');
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'restaurant')->firstOrFail(), $tenant);

    $restaurant = MarketplaceFixtures::restaurant($tenant, $owner, $attributes + [
        'status' => Restaurant::STATUS_PUBLISHED,
        'ordering_enabled' => true,
        'delivery_enabled' => true,
        'tax_rate' => 12,
        'tax_inclusive' => true,
    ]);

    MarketplaceFixtures::asListing($restaurant);
    $category = MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Burgers']);
    $burger = MenuItem::create(['restaurant_id' => $restaurant->id, 'menu_category_id' => $category->id, 'name' => 'Burger', 'price' => 250]);
    $addons = $burger->modifierGroups()->create(['name' => 'Add-ons']);
    $options = [];
    foreach (['Cheese' => 30, 'Bacon' => 50, 'Egg' => 25] as $name => $price) {
        $options[$name] = $addons->options()->create(['name' => $name, 'price' => $price])->id;
    }
    MarketplaceFixtures::asTenant(null);

    return [$owner, $tenant, $restaurant->refresh(), $burger, $options];
}

function placeOrder(Restaurant $restaurant, array $lines, array $data = [], ?User $customer = null): Order
{
    return app(TenantContext::class)->runAs($restaurant, fn () => app(OrderService::class)->place($restaurant, $lines, $data + [
        'fulfillment' => Order::PICKUP,
        'payment_method' => Order::PAY_CASH,
        'customer_name' => 'Ana',
        'customer_phone' => '0917',
    ], $customer));
}

function moveOrder(Order $order, string ...$states): Order
{
    return app(TenantContext::class)->runAs($order, function () use ($order, $states) {
        foreach ($states as $state) {
            app(OrderService::class)->transition($order, $state);
        }

        return $order->refresh();
    });
}

it('prices lines on the server with modifiers, discounts and taxes', function () {
    [, , $restaurant, $burger, $opt] = kitchen();
    $quote = fn (array $lines, ?string $code = null) => app(TenantContext::class)->runAs($restaurant, fn () => app(OrderService::class)->quote($restaurant->refresh(), $lines, $code));
    $lines = [['item_id' => $burger->id, 'option_ids' => [$opt['Cheese']], 'quantity' => 2]];

    // ₱280 × 2, VAT 12% included.
    expect($quote($lines))->toMatchArray(['subtotal' => 560.0, 'discount' => 0.0, 'tax' => 60.0, 'total' => 560.0]);

    MarketplaceFixtures::asListing($restaurant);
    $restaurant->update(['tax_inclusive' => false]);
    Promotion::create(['code' => 'LUNCH10', 'name' => 'Lunch', 'type' => 'percent', 'value' => 10, 'applies_to' => Promotion::FOR_ORDERS, 'restaurant_id' => $restaurant->id, 'min_subtotal' => 500]);
    Promotion::create(['code' => 'STAY20', 'name' => 'Stay', 'type' => 'percent', 'value' => 20]);
    MarketplaceFixtures::asTenant(null);

    expect($quote($lines))->toMatchArray(['tax' => 67.2, 'total' => 627.2])
        ->and($quote($lines, 'lunch10'))->toMatchArray(['discount' => 56.0, 'tax' => 60.48, 'total' => 564.48])
        ->and(fn () => $quote($lines, 'STAY20'))->toThrow(ValidationException::class) // a stay code
        ->and(fn () => $quote([['item_id' => $burger->id, 'quantity' => 1]], 'LUNCH10'))->toThrow(ValidationException::class); // below minimum
});

it('runs cart to checkout and snapshots the order', function () {
    [, $tenant, $restaurant, $burger, $opt] = kitchen();

    $this->post(route('cart.add', $restaurant->slug), ['item_id' => $burger->id, 'options' => [$opt['Cheese'], $opt['Bacon']], 'quantity' => 2])
        ->assertSessionHasNoErrors();
    $this->get(route('cart.show'))->assertOk()->assertInertia(fn ($p) => $p->component('Order/Checkout')->where('quote.total', 660)->where('signedIn', false));

    $this->post(route('cart.checkout'), ['fulfillment' => 'pickup', 'payment_method' => 'cash', 'customer_phone' => '0917'])
        ->assertRedirect(route('login'));

    $guest = User::factory()->create();
    $this->actingAs($guest)->post(route('cart.checkout'), [
        'fulfillment' => 'pickup', 'payment_method' => 'cash', 'customer_phone' => '0917', 'notes' => 'No onions',
    ])->assertRedirect();

    $order = Order::forCustomer($guest)->firstOrFail();
    expect($order->status)->toBe(Order::PENDING)
        ->and((float) $order->total)->toBe(660.0)
        ->and($order->tenant_id)->toBe($tenant->id);

    $line = app(TenantContext::class)->runAs($order, fn () => $order->items()->first());
    expect($line->name)->toBe('Burger')->and((float) $line->unit_price)->toBe(330.0)
        ->and($line->modifierLabel())->toBe('Cheese, Bacon');

    // Menu edits never rewrite the order.
    MarketplaceFixtures::asListing($restaurant);
    $burger->update(['price' => 999, 'name' => 'Mega Burger']);
    MarketplaceFixtures::asTenant(null);

    $this->get(route('account.orders.show', $order->reference))->assertOk()->assertSee('Burger')->assertSee('₱660.00')->assertDontSee('Mega');
    $this->get(route('cart.show'))->assertInertia(fn ($p) => $p->component('Order/Checkout')->where('lines', []));
});

it('rejects bad modifier choices, unavailable items and closed restaurants', function () {
    [, , $restaurant, $burger, $opt] = kitchen();

    MarketplaceFixtures::asListing($restaurant);
    $patty = $burger->modifierGroups()->create(['name' => 'Patty', 'min_select' => 1, 'max_select' => 1]);
    $single = $patty->options()->create(['name' => 'Single', 'price' => 0]);
    $fries = MenuItem::create(['restaurant_id' => $restaurant->id, 'menu_category_id' => $burger->menu_category_id, 'name' => 'Fries', 'price' => 90, 'is_available' => false]);
    MarketplaceFixtures::asTenant(null);

    $this->post(route('cart.add', $restaurant->slug), ['item_id' => $burger->id, 'options' => [$opt['Egg']], 'quantity' => 1])->assertSessionHasErrors('modifiers');
    $this->post(route('cart.add', $restaurant->slug), ['item_id' => $fries->id, 'quantity' => 1])->assertSessionHasErrors('cart');
    $this->post(route('cart.add', $restaurant->slug), ['item_id' => $burger->id, 'options' => [$single->id], 'quantity' => 1])->assertSessionHasNoErrors();

    MarketplaceFixtures::asListing($restaurant);
    $restaurant->update(['ordering_enabled' => false]);
    MarketplaceFixtures::asTenant(null);
    $this->post(route('cart.add', $restaurant->slug), ['item_id' => $burger->id, 'options' => [$single->id], 'quantity' => 1])->assertNotFound();
});

it('walks pickup and delivery orders through their states', function () {
    [, , $restaurant, $burger] = kitchen();
    $lines = [['item_id' => $burger->id, 'quantity' => 1]];

    $pickup = moveOrder(placeOrder($restaurant, $lines), Order::ACCEPTED, Order::PREPARING, Order::READY);
    expect($pickup->nextStates())->toBe([Order::COMPLETED])
        ->and(fn () => moveOrder($pickup, Order::OUT_FOR_DELIVERY))->toThrow(ValidationException::class);
    expect(moveOrder($pickup, Order::COMPLETED)->completed_at)->not->toBeNull();

    MarketplaceFixtures::asListing($restaurant);
    $zone = $restaurant->deliveryZones()->create(['name' => 'Station 2', 'fee' => 0]);
    $driver = \App\Modules\Delivery\Models\Driver::create(['name' => 'Jun']);
    MarketplaceFixtures::asTenant(null);

    $delivery = moveOrder(placeOrder($restaurant, $lines, ['fulfillment' => Order::DELIVERY, 'delivery_address' => 'Beach front', 'delivery_zone_id' => $zone->id]), Order::ACCEPTED, Order::PREPARING, Order::READY);
    app(TenantContext::class)->runAs($delivery, fn () => app(OrderService::class)->assignDriver($delivery, $driver));
    moveOrder($delivery, Order::OUT_FOR_DELIVERY, Order::DELIVERED, Order::COMPLETED);
    expect($delivery->status)->toBe(Order::COMPLETED);

    // Cash orders never become refundable through PayMongo; cancelled is terminal.
    $cancelled = moveOrder(placeOrder($restaurant, $lines), Order::CANCELLED);
    expect($cancelled->nextStates())->toBe([])
        ->and(fn () => moveOrder($cancelled, Order::ACCEPTED))->toThrow(ValidationException::class);
});

it('takes online payment through PayMongo, earns commission and refunds', function () {
    [$owner, $tenant, $restaurant, $burger] = kitchen();
    $guest = User::factory()->create();

    $this->post(route('cart.add', $restaurant->slug), ['item_id' => $burger->id, 'quantity' => 2]);
    PayMongoFake::fake();
    $this->actingAs($guest)->post(route('cart.checkout'), ['fulfillment' => 'pickup', 'payment_method' => 'online', 'customer_phone' => '0917'])
        ->assertRedirect('https://checkout.paymongo.test/cs_test_1');

    $order = Order::forCustomer($guest)->firstOrFail();
    expect($order->payment_status)->toBe(Order::UNPAID)
        ->and(fn () => moveOrder($order, Order::ACCEPTED))->toThrow(ValidationException::class); // not paid yet

    PayMongoFake::fake(paid: true, amount: 50000);
    PayMongoFake::webhook('evt_order_paid', 'checkout_session.payment.paid', ['id' => 'cs_test_1'])->assertOk();

    expect($order->refresh()->payment_status)->toBe(Order::PAID);

    MarketplaceFixtures::asTenant($tenant);
    $commission = Commission::query()->where('order_id', $order->id)->firstOrFail();
    expect((float) $commission->gross)->toBe(500.0)->and((float) $commission->host_amount)->toBe(450.0)
        ->and((float) Wallet::query()->first()->pending_balance)->toBe(450.0);

    moveOrder($order, Order::ACCEPTED, Order::PREPARING, Order::READY, Order::COMPLETED);
    expect((float) Wallet::query()->first()->available_balance)->toBe(450.0)
        ->and($commission->refresh()->status)->toBe(Commission::RELEASED);

    moveOrder($order, Order::REFUNDED);
    expect($order->payment_status)->toBe('refunded')
        ->and(Payment::query()->where('order_id', $order->id)->value('status'))->toBe(Payment::REFUNDED)
        ->and($commission->refresh()->status)->toBe(Commission::REVERSED)
        ->and((float) Wallet::query()->first()->available_balance)->toBe(0.0);
});

it('keeps an order unrefunded when PayMongo refuses the refund', function () {
    [, $tenant, $restaurant, $burger] = kitchen();
    $guest = User::factory()->create();

    $this->post(route('cart.add', $restaurant->slug), ['item_id' => $burger->id, 'quantity' => 1]);
    PayMongoFake::fake();
    $this->actingAs($guest)->post(route('cart.checkout'), ['fulfillment' => 'pickup', 'payment_method' => 'online', 'customer_phone' => '0917']);
    $order = Order::forCustomer($guest)->firstOrFail();

    PayMongoFake::fake(paid: true, amount: 25000, refundStatus: 400);
    PayMongoFake::webhook('evt_1', 'checkout_session.payment.paid', ['id' => 'cs_test_1']);

    $this->post(route('account.orders.cancel', $order->reference))->assertSessionHasNoErrors();

    expect(fn () => moveOrder($order->refresh(), Order::REFUNDED))->toThrow(ValidationException::class)
        ->and($order->refresh()->status)->toBe(Order::CANCELLED)
        ->and($order->payment_status)->toBe(Order::PAID);
});

it('shows customers only their own orders and cancels only pending ones', function () {
    [, , $restaurant, $burger] = kitchen();
    $guest = User::factory()->create();
    $other = User::factory()->create();
    $mine = placeOrder($restaurant, [['item_id' => $burger->id, 'quantity' => 1]], [], $guest);
    $accepted = moveOrder(placeOrder($restaurant, [['item_id' => $burger->id, 'quantity' => 1]], [], $guest), Order::ACCEPTED);

    $this->actingAs($other)->get(route('account.orders.index'))->assertOk()->assertDontSee($mine->reference);
    $this->get(route('account.orders.show', $mine->reference))->assertNotFound();
    $this->post(route('account.orders.cancel', $mine->reference))->assertNotFound();

    $this->actingAs($guest)->get(route('account.orders.index'))->assertOk()->assertSee($mine->reference);
    $this->post(route('account.orders.cancel', $accepted->reference))->assertSessionHasErrors('order');
    $this->post(route('account.orders.cancel', $mine->reference))->assertSessionHasNoErrors();

    expect($mine->refresh()->status)->toBe(Order::CANCELLED)
        ->and($guest->notifications()->count())->toBe(2); // accepted + cancelled
});

it('runs the host order queue with permissions and tenant isolation', function () {
    [$owner, $tenant, $restaurant, $burger] = kitchen();
    $order = placeOrder($restaurant, [['item_id' => $burger->id, 'quantity' => 3]]);
    PropertyManagementFixtures::login($owner, $tenant);

    $this->get(route('restaurants.orders.index', $restaurant))->assertOk()->assertSee($order->reference);
    $this->get(route('restaurants.orders.show', [$restaurant, $order->reference]))->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('Restaurants/Order')->where('order.lines.0.qty', 3));
    $this->post(route('restaurants.orders.transition', [$restaurant, $order->reference]), ['status' => 'accepted'])->assertSessionHasNoErrors();
    $this->post(route('restaurants.orders.transition', [$restaurant, $order->reference]), ['status' => 'completed'])->assertSessionHasErrors('status');
    expect($order->refresh()->status)->toBe(Order::ACCEPTED);

    $this->post(route('restaurants.orders.promotions.store', $restaurant), ['code' => 'fries5', 'name' => 'Fries', 'type' => 'fixed', 'value' => 5])->assertSessionHasNoErrors();
    MarketplaceFixtures::asTenant($tenant);
    expect(Promotion::query()->where('code', 'FRIES5')->value('applies_to'))->toBe(Promotion::FOR_ORDERS);

    $staff = MarketplaceFixtures::member($tenant, 'staff');
    PropertyManagementFixtures::login($staff, $tenant);
    $this->get(route('restaurants.orders.index', $restaurant))->assertForbidden();

    [$ownerB, $tenantB] = MarketplaceFixtures::business('Kitchen B');
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'restaurant')->firstOrFail(), $tenantB);
    PropertyManagementFixtures::login($ownerB, $tenantB);
    $this->get(route('restaurants.orders.index', $restaurant))->assertNotFound();
    $this->post(route('restaurants.orders.transition', [$restaurant, $order->reference]), ['status' => 'preparing'])->assertNotFound();
});

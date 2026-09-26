<?php

use App\Models\Module;
use App\Models\User;
use App\Modules\Delivery\Models\DeliveryZone;
use App\Modules\Delivery\Models\Driver;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\RestaurantManagement\Models\MenuCategory;
use App\Modules\RestaurantManagement\Models\MenuItem;
use App\Support\ModuleService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 12 (Delivery) — zones (named / radius), fees, minimum order, free
| delivery, drivers and assignment, dispatch rules, ETA, scheduled orders,
| host setup screen and isolation.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    PropertyManagementFixtures::bootstrap();
    $this->travelTo(CarbonImmutable::parse('2030-06-04 12:00'));

    [$this->owner, $this->tenant] = MarketplaceFixtures::business('Kitchen A');
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'restaurant')->firstOrFail(), $this->tenant);

    // White Beach, Boracay.
    $this->restaurant = MarketplaceFixtures::restaurant($this->tenant, $this->owner, [
        'status' => Restaurant::STATUS_PUBLISHED, 'ordering_enabled' => true, 'delivery_enabled' => true,
        'tax_rate' => 12, 'tax_inclusive' => true, 'prep_minutes' => 20,
        'latitude' => 11.9674, 'longitude' => 121.9248,
    ]);

    MarketplaceFixtures::asListing($this->restaurant);
    $category = MenuCategory::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Mains']);
    $this->item = MenuItem::create(['restaurant_id' => $this->restaurant->id, 'menu_category_id' => $category->id, 'name' => 'Adobo', 'price' => 300]);
    $this->station = DeliveryZone::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Station 2', 'fee' => 50, 'min_order' => 250, 'free_over' => 1000, 'eta_minutes' => 25]);
    $this->nearby = DeliveryZone::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Within 2 km', 'radius_km' => 2, 'fee' => 80, 'eta_minutes' => 15]);
    $this->driver = Driver::create(['name' => 'Jun', 'phone' => '0918']);
    MarketplaceFixtures::asTenant(null);
    $this->restaurant->refresh();
});

function deliver(array $data, int $qty = 1): Order
{
    $restaurant = test()->restaurant;

    return app(TenantContext::class)->runAs($restaurant, fn () => app(OrderService::class)->place($restaurant, [['item_id' => test()->item->id, 'quantity' => $qty]], $data + [
        'fulfillment' => Order::DELIVERY, 'payment_method' => Order::PAY_CASH, 'customer_name' => 'Ana', 'delivery_address' => 'Beach path',
    ], null));
}

function step(Order $order, string ...$states): Order
{
    return app(TenantContext::class)->runAs($order, function () use ($order, $states) {
        foreach ($states as $s) {
            app(OrderService::class)->transition($order, $s);
        }

        return $order->refresh();
    });
}

it('charges the zone fee, waives it over the threshold and enforces the minimum', function () {
    $order = deliver(['delivery_zone_id' => $this->station->id]);
    expect((float) $order->delivery_fee)->toBe(50.0)->and((float) $order->total)->toBe(350.0);

    expect((float) deliver(['delivery_zone_id' => $this->station->id], 4)->delivery_fee)->toBe(0.0); // ₱1,200 ≥ free_over

    MarketplaceFixtures::asListing($this->restaurant);
    $this->station->update(['min_order' => 400]);
    MarketplaceFixtures::asTenant(null);
    expect(fn () => deliver(['delivery_zone_id' => $this->station->id]))->toThrow(ValidationException::class);

    // No zone, another restaurant's zone, or a paused zone → refused.
    expect(fn () => deliver([]))->toThrow(ValidationException::class);
    MarketplaceFixtures::asListing($this->restaurant);
    $this->nearby->update(['is_active' => false]);
    MarketplaceFixtures::asTenant(null);
    expect(fn () => deliver(['delivery_zone_id' => $this->nearby->id, 'delivery_lat' => 11.968, 'delivery_lng' => 121.925]))->toThrow(ValidationException::class);
});

it('checks radius zones against the drop-off point', function () {
    // ~0.5 km away → inside; ~5.5 km away → outside; no location → refused.
    $inside = deliver(['delivery_zone_id' => $this->nearby->id, 'delivery_lat' => 11.9719, 'delivery_lng' => 121.9248]);
    expect((float) $inside->delivery_fee)->toBe(80.0)->and((float) $inside->delivery_lat)->toBe(11.9719);

    expect(fn () => deliver(['delivery_zone_id' => $this->nearby->id, 'delivery_lat' => 12.0170, 'delivery_lng' => 121.9248]))->toThrow(ValidationException::class)
        ->and(fn () => deliver(['delivery_zone_id' => $this->nearby->id]))->toThrow(ValidationException::class);

    expect(round(DeliveryZone::distanceKm(11.9674, 121.9248, 12.0170, 121.9248), 1))->toBe(5.5);
});

it('needs a driver before dispatch and sets ETAs along the way', function () {
    $order = step(deliver(['delivery_zone_id' => $this->station->id]), Order::ACCEPTED);
    expect($order->estimated_at->format('H:i'))->toBe('12:45'); // 20 prep + 25 ride

    step($order, Order::PREPARING, Order::READY);
    expect(fn () => step($order, Order::OUT_FOR_DELIVERY))->toThrow(ValidationException::class);

    $service = app(OrderService::class);
    app(TenantContext::class)->runAs($order, fn () => $service->assignDriver($order, $this->driver));

    $this->travelTo(CarbonImmutable::parse('2030-06-04 12:30'));
    step($order, Order::OUT_FOR_DELIVERY);
    expect($order->dispatched_at->format('H:i'))->toBe('12:30')->and($order->estimated_at->format('H:i'))->toBe('12:55');

    // Cannot unassign while on the road; off-duty drivers cannot be assigned.
    expect(fn () => app(TenantContext::class)->runAs($order, fn () => $service->assignDriver($order, null)))->toThrow(ValidationException::class);

    step($order, Order::DELIVERED);
    expect($order->delivered_at)->not->toBeNull();

    $other = step(deliver(['delivery_zone_id' => $this->station->id]), Order::ACCEPTED);
    MarketplaceFixtures::asTenant($this->tenant);
    $this->driver->update(['is_active' => false]);
    expect(fn () => $service->assignDriver($other, $this->driver->refresh()))->toThrow(ValidationException::class);

    // Pickup orders take no driver.
    $pickup = step(app(TenantContext::class)->runAs($this->restaurant, fn () => $service->place($this->restaurant, [['item_id' => $this->item->id, 'quantity' => 1]], [
        'fulfillment' => Order::PICKUP, 'payment_method' => Order::PAY_CASH, 'customer_name' => 'Ben',
    ], null)), Order::ACCEPTED);
    expect($pickup->estimated_at->format('H:i'))->toBe('12:50') // 12:30 + 20 prep
        ->and(fn () => $service->assignDriver($pickup, Driver::create(['name' => 'Lea'])))->toThrow(ValidationException::class);
});

it('takes scheduled orders inside the allowed window and uses them as the ETA', function () {
    expect(fn () => deliver(['delivery_zone_id' => $this->station->id, 'scheduled_for' => '2030-06-04 12:10']))->toThrow(ValidationException::class) // < prep time
        ->and(fn () => deliver(['delivery_zone_id' => $this->station->id, 'scheduled_for' => '2030-06-12 12:00']))->toThrow(ValidationException::class); // > 7 days

    $order = step(deliver(['delivery_zone_id' => $this->station->id, 'scheduled_for' => '2030-06-04 19:00']), Order::ACCEPTED);
    expect($order->scheduled_for->format('H:i'))->toBe('19:00')->and($order->estimated_at->format('H:i'))->toBe('19:00');
});

it('checks out a delivery from the cart with a zone', function () {
    $this->post(route('cart.add', $this->restaurant->slug), ['item_id' => $this->item->id, 'quantity' => 1]);

    $guest = User::factory()->create();
    $this->actingAs($guest)->get(route('cart.show'))->assertInertia(fn ($p) => $p->where('zones.0.name', 'Station 2')->where('zones.1.name', 'Within 2 km')->where('zones.1.radius', true));
    $this->post(route('cart.checkout'), [
        'fulfillment' => 'delivery', 'payment_method' => 'cash', 'customer_phone' => '0917',
        'delivery_zone_id' => $this->station->id, 'delivery_address' => 'Near D*Mall',
    ])->assertRedirect();

    $order = Order::forCustomer($guest)->firstOrFail();
    expect((float) $order->delivery_fee)->toBe(50.0)->and($order->delivery_zone_id)->toBe($this->station->id);

    $this->get(route('account.orders.show', $order->reference))->assertOk()->assertSee('Delivery fee')->assertSee('₱350.00');
});

it('runs the delivery setup screen with permissions and isolation', function () {
    PropertyManagementFixtures::login($this->owner, $this->tenant);

    $this->get(route('restaurants.delivery.index', $this->restaurant))->assertOk()->assertSee('Station 2')->assertSee('Jun');
    $this->post(route('restaurants.delivery.zones.store', $this->restaurant), ['name' => 'Station 1', 'fee' => 40, 'eta_minutes' => 20])->assertSessionHasNoErrors();
    $this->post(route('restaurants.delivery.zones.store', $this->restaurant), ['name' => 'Station 1', 'fee' => 40, 'eta_minutes' => 20])->assertSessionHasErrors('name');
    $this->post(route('restaurants.delivery.drivers.store', $this->restaurant), ['name' => 'Lea', 'vehicle' => 'Motorbike'])->assertSessionHasNoErrors();
    $this->patch(route('restaurants.delivery.settings', $this->restaurant), ['prep_minutes' => 35])->assertSessionHasNoErrors();
    expect($this->restaurant->refresh()->prep_minutes)->toBe(35);

    // Host assigns a driver from the queue.
    $order = step(deliver(['delivery_zone_id' => $this->station->id]), Order::ACCEPTED);
    $this->post(route('restaurants.orders.driver', [$this->restaurant, $order->reference]), ['driver_id' => $this->driver->id])->assertSessionHasNoErrors();
    expect($order->refresh()->driver_id)->toBe($this->driver->id);

    $desk = MarketplaceFixtures::member($this->tenant, 'front_desk');
    PropertyManagementFixtures::login($desk, $this->tenant);
    $this->get(route('restaurants.delivery.index', $this->restaurant))->assertForbidden();

    [$ownerB, $tenantB] = MarketplaceFixtures::business('Kitchen B');
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'restaurant')->firstOrFail(), $tenantB);
    PropertyManagementFixtures::login($ownerB, $tenantB);
    $this->get(route('restaurants.delivery.index', $this->restaurant))->assertNotFound();
    $this->post(route('restaurants.delivery.zones.toggle', [$this->restaurant, $this->station->id]))->assertNotFound();

    // Another business's driver cannot be assigned here (tenant scope → 404).
    $foreignDriver = Driver::create(['name' => 'Foreign']);
    PropertyManagementFixtures::login($this->owner, $this->tenant);
    $this->post(route('restaurants.orders.driver', [$this->restaurant, $order->reference]), ['driver_id' => $foreignDriver->id])->assertNotFound();
});

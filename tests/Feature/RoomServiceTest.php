<?php

use App\Models\Module;
use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\Payments\Models\Payment;
use App\Modules\RestaurantManagement\Models\MenuCategory;
use App\Modules\RestaurantManagement\Models\MenuItem;
use App\Support\ModuleService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 13 (Hotel Room Service) — in-house guests order from a restaurant of
| the same business to their room, charged to the stay or paid.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    $this->travelTo(CarbonImmutable::parse('2030-09-05 18:00'));

    [$this->owner, $this->tenant, $this->property, $type] = BookingFixtures::hotel();
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'restaurant')->firstOrFail(), $this->tenant);

    $this->guest = User::factory()->create();
    $this->stay = BookingFixtures::reserve($this->property, $type, [], Booking::SOURCE_MANUAL, $this->guest);
    app(BookingService::class)->asTenantOf($this->stay, fn () => app(BookingService::class)->transition($this->stay, Booking::CHECKED_IN));

    $this->restaurant = MarketplaceFixtures::restaurant($this->tenant, $this->owner, [
        'status' => Restaurant::STATUS_PUBLISHED, 'ordering_enabled' => true, 'room_service_enabled' => true,
        'tax_rate' => 12, 'tax_inclusive' => true, 'prep_minutes' => 20,
    ]);
    MarketplaceFixtures::asListing($this->restaurant);
    $category = MenuCategory::create(['restaurant_id' => $this->restaurant->id, 'name' => 'In-room dining']);
    $this->item = MenuItem::create(['restaurant_id' => $this->restaurant->id, 'menu_category_id' => $category->id, 'name' => 'Club sandwich', 'price' => 420]);
    $this->roomId = $this->stay->rooms()->value('room_id');
    MarketplaceFixtures::asTenant(null);
    auth()->logout();
});

function roomOrder(User $customer, array $data = []): Order
{
    $restaurant = test()->restaurant->refresh();

    return app(TenantContext::class)->runAs($restaurant, fn () => app(OrderService::class)->place($restaurant, [['item_id' => test()->item->id, 'quantity' => 1]], $data + [
        'fulfillment' => Order::ROOM_SERVICE, 'payment_method' => Order::PAY_ROOM, 'customer_name' => $customer->name,
        'booking_id' => test()->stay->id, 'room_id' => test()->roomId,
    ], $customer));
}

it('offers room service to a checked-in guest and charges it to the stay', function () {
    $this->post(route('cart.add', $this->restaurant->slug), ['item_id' => $this->item->id, 'quantity' => 2]);

    $this->actingAs($this->guest)->get(route('cart.show'))
        ->assertOk()->assertInertia(fn ($p) => $p->where('stays.0.value', $this->stay->id.':'.$this->roomId)->where('stays.0.label', fn ($l) => str_starts_with($l, 'Room 101')));

    $this->post(route('cart.checkout'), [
        'fulfillment' => 'room_service', 'payment_method' => 'room_charge', 'customer_phone' => '0917',
        'room_stay' => $this->stay->id.':'.$this->roomId,
    ])->assertRedirect();

    $order = Order::forCustomer($this->guest)->firstOrFail();
    expect($order->fulfillment)->toBe(Order::ROOM_SERVICE)
        ->and($order->payment_status)->toBe(Order::CHARGED)
        ->and($order->booking_id)->toBe($this->stay->id)
        ->and($order->room_id)->toBe($this->roomId)
        ->and($order->delivery_address)->toContain('Room 101')
        ->and((float) $order->delivery_fee)->toBe(0.0)
        ->and(Payment::query()->withoutGlobalScopes()->count())->toBe(0);

    $this->get(route('account.orders.show', $order->reference))->assertOk()->assertSee('Charged to room');
});

it('delivers room service without a driver and times it as a walk to the room', function () {
    $order = roomOrder($this->guest);
    $service = app(OrderService::class);

    app(TenantContext::class)->runAs($order, function () use ($order, $service) {
        $service->transition($order, Order::ACCEPTED);
        expect($order->estimated_at->format('H:i'))->toBe('18:30'); // 20 prep + 10 walk

        foreach ([Order::PREPARING, Order::READY, Order::OUT_FOR_DELIVERY, Order::DELIVERED, Order::COMPLETED] as $state) {
            $service->transition($order, $state);
        }
    });

    expect($order->refresh()->status)->toBe(Order::COMPLETED)->and($order->driver_id)->toBeNull();
});

it('refuses room service to guests who are not checked in here', function () {
    // No stay at all.
    $stranger = User::factory()->create();
    expect(fn () => roomOrder($stranger))->toThrow(ValidationException::class);

    // Someone else's booking id.
    expect(fn () => roomOrder($stranger, ['booking_id' => $this->stay->id]))->toThrow(ValidationException::class);

    // A room that is not part of the stay.
    expect(fn () => roomOrder($this->guest, ['room_id' => 999999]))->toThrow(ValidationException::class);

    // Checked out → no longer in-house.
    app(BookingService::class)->asTenantOf($this->stay, fn () => app(BookingService::class)->transition($this->stay->refresh(), Booking::CHECKED_OUT));
    expect(fn () => roomOrder($this->guest))->toThrow(ValidationException::class);
});

it('keeps room charges to room service at restaurants that offer it', function () {
    $restaurant = $this->restaurant;
    $service = app(OrderService::class);
    $place = fn (array $data) => app(TenantContext::class)->runAs($restaurant, fn () => $service->place($restaurant->refresh(), [['item_id' => $this->item->id, 'quantity' => 1]], $data + [
        'customer_name' => 'Ana', 'booking_id' => $this->stay->id, 'room_id' => $this->roomId,
    ], $this->guest));

    expect(fn () => $place(['fulfillment' => Order::PICKUP, 'payment_method' => Order::PAY_ROOM]))->toThrow(ValidationException::class);

    // Room service paid in cash is fine; a charged order can be accepted without online payment.
    $cash = $place(['fulfillment' => Order::ROOM_SERVICE, 'payment_method' => Order::PAY_CASH]);
    expect($cash->payment_status)->toBe(Order::UNPAID);
    app(TenantContext::class)->runAs($cash, fn () => $service->transition($cash, Order::ACCEPTED));

    MarketplaceFixtures::asListing($restaurant);
    $restaurant->update(['room_service_enabled' => false]);
    MarketplaceFixtures::asTenant(null);
    expect(fn () => $place(['fulfillment' => Order::ROOM_SERVICE, 'payment_method' => Order::PAY_ROOM]))->toThrow(ValidationException::class);

    // A restaurant of another business never sees this stay.
    [$ownerB, $tenantB] = MarketplaceFixtures::business('Other Co');
    $other = MarketplaceFixtures::restaurant($tenantB, $ownerB, ['status' => Restaurant::STATUS_PUBLISHED, 'ordering_enabled' => true, 'room_service_enabled' => true]);
    expect(app(TenantContext::class)->runAs($other, fn () => $service->roomServiceStays($other, $this->guest)))->toBeEmpty();
});

it('shows room service orders in the host queue with the room', function () {
    $order = roomOrder($this->guest);
    PropertyManagementFixtures::login($this->owner, $this->tenant);

    $this->get(route('restaurants.orders.index', $this->restaurant))->assertOk()->assertSee($order->reference)->assertSee('Charged to room');
    $this->get(route('restaurants.orders.show', [$this->restaurant, $order->reference]))->assertOk()->assertSee('Room 101')->assertSee('Room Service');
});

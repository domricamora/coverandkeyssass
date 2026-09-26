<?php

use App\Models\User;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;

/*
| Guest booking flows (React widgets + checkout pages): sign-in return,
| stay quotes, the review step, cart JSON and the table booking widget.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('returns a guest to the step they left after signing in', function () {
    $this->get('/continue?to=/cart')->assertRedirect(route('login'));

    $user = User::factory()->create();
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/continue?to=%2Fcart');

    $this->get('/continue?to=/cart')->assertRedirect('/cart');
});

it('quotes every room type live for the stay panel, with promo codes', function () {
    BookingFixtures::bootstrap();
    [, , $property, $type] = BookingFixtures::hotel(rooms: 2);
    MarketplaceFixtures::publish($property);
    $this->post(route('bookings.promotions.store'), ['code' => 'SUMMER10', 'name' => 'Summer', 'type' => 'percent', 'value' => 10])->assertSessionHasNoErrors();
    auth()->logout();

    $url = fn (array $q) => route('marketplace.properties.quote', $property->slug).'?'.http_build_query($q + ['check_in' => '2030-09-02', 'check_out' => '2030-09-04', 'guests' => 2]);

    // Mon + Tue nights at the base rate (3,500).
    $this->getJson($url([]))->assertOk()
        ->assertJsonPath('nights', 2)
        ->assertJsonPath('options.0.room_type_id', $type->id)
        ->assertJsonPath('options.0.free', 2)
        ->assertJsonPath('options.0.total', 7000)
        ->assertJsonPath('promo', null);

    $this->getJson($url(['promo_code' => 'summer10']))->assertOk()->assertJsonPath('promo.discount', 700);
    $this->getJson($url(['promo_code' => 'NOPE']))->assertOk()->assertJsonPath('promo', null)->assertJsonPath('promo_error', 'This promo code does not exist.');
    $this->getJson($url(['check_out' => '2030-09-01']))->assertStatus(422);
});

it('mounts the React stay panel and runs the review step before reserving', function () {
    BookingFixtures::bootstrap();
    [, , $property, $type] = BookingFixtures::hotel(rooms: 2);
    MarketplaceFixtures::publish($property);
    auth()->logout();

    $this->get(route('marketplace.properties.show', $property->slug))->assertOk()
        ->assertSee('data-widget="StayPanel"', false)
        ->assertSee('Sign in to book'); // server-rendered no-JS fallback stays inside the mount

    $query = ['check_in' => '2030-09-02', 'check_out' => '2030-09-04', 'guests' => 2, 'room_type_id' => $type->id, 'quantity' => 1];
    $this->get(route('stay.review', $property->slug).'?'.http_build_query($query))->assertRedirect(route('login'));

    $guest = User::factory()->create();
    $this->actingAs($guest)->get(route('stay.review', $property->slug).'?'.http_build_query($query))->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Stay/Review')
            ->where('option.total', 7000)
            ->where('stay.nights', 2)
            ->where('guest.email', $guest->email));

    // Asking for more rooms than are free sends the guest back to pick again.
    $this->get(route('stay.review', $property->slug).'?'.http_build_query(['quantity' => 3] + $query))
        ->assertRedirect()->assertSessionHas('error');
});

it('only follows same-site paths', function () {
    $this->actingAs(User::factory()->create());

    foreach (['https://evil.test/x', '//evil.test', '/\\evil.test', 'cart'] as $to) {
        $this->get('/continue?to='.urlencode($to))->assertRedirect('/');
    }
});

/** Published restaurant taking orders, with one burger (+ cheese add-on). */
function menuKitchen(string $name): array
{
    [$owner, $tenant] = MarketplaceFixtures::business($name);
    app(App\Support\ModuleService::class)->enableForTenant(App\Models\Module::query()->where('slug', 'restaurant')->firstOrFail(), $tenant);
    $restaurant = MarketplaceFixtures::restaurant($tenant, $owner, ['status' => App\Modules\Marketplace\Models\Restaurant::STATUS_PUBLISHED, 'ordering_enabled' => true, 'tax_rate' => 12, 'tax_inclusive' => true]);
    MarketplaceFixtures::asListing($restaurant);
    $category = App\Modules\RestaurantManagement\Models\MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Burgers']);
    $burger = App\Modules\RestaurantManagement\Models\MenuItem::create(['restaurant_id' => $restaurant->id, 'menu_category_id' => $category->id, 'name' => 'Burger', 'price' => 250]);
    $cheese = $burger->modifierGroups()->create(['name' => 'Add-ons'])->options()->create(['name' => 'Cheese', 'price' => 30])->id;
    MarketplaceFixtures::asTenant(null);

    return [$restaurant->refresh(), $burger, $cheese];
}

it('runs the cart as JSON for the menu widget', function () {
    Tests\Support\PropertyManagementFixtures::bootstrap();
    [$a, $burger, $cheese] = menuKitchen('Kitchen A');
    [$b, $other] = menuKitchen('Kitchen B');
    auth()->logout();

    $this->getJson(route('cart.summary'))->assertOk()->assertJsonPath('count', 0)->assertJsonPath('restaurant', null);

    $this->postJson(route('cart.add', $a->slug), ['item_id' => $burger->id, 'options' => [$cheese], 'quantity' => 2])->assertOk()
        ->assertJsonPath('count', 2)
        ->assertJsonPath('subtotal', 560)
        ->assertJsonPath('lines.0.mods', 'Cheese')
        ->assertJsonPath('replaced', false);

    $this->postJson(route('cart.add', $b->slug), ['item_id' => $other->id, 'quantity' => 1])->assertOk()
        ->assertJsonPath('replaced', true)
        ->assertJsonPath('restaurant.slug', $b->slug)
        ->assertJsonPath('count', 1);

    $key = $this->getJson(route('cart.summary'))->json('lines.0.key');
    $this->patchJson(route('cart.update', $key), ['quantity' => 0])->assertOk()->assertJsonPath('count', 0)->assertJsonPath('lines', []);

    $this->postJson(route('cart.add', $a->slug), ['item_id' => $burger->id, 'options' => [999999], 'quantity' => 1])->assertStatus(422);
});

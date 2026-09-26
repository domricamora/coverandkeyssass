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

it('only follows same-site paths', function () {
    $this->actingAs(User::factory()->create());

    foreach (['https://evil.test/x', '//evil.test', '/\\evil.test', 'cart'] as $to) {
        $this->get('/continue?to='.urlencode($to))->assertRedirect('/');
    }
});

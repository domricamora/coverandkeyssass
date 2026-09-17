<?php

use App\Models\User;
use App\Modules\Marketplace\Models\Favorite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MarketplaceFixtures;
use Tests\TestCase;

/*
| Phase 03 (Marketplace) — public HTTP surface.
|
| These tests cover the read-only marketplace and the guest wish list the
| way a browser reaches them: published listings are visible to everyone
| (including anonymous visitors), drafts/suspended rows are a plain 404,
| filters and sorting are honoured, and favorites are strictly personal.
*/
uses(RefreshDatabase::class);

beforeEach(function () {
    MarketplaceFixtures::bootstrap();
});

it('shows the marketplace landing page with published highlights', function () {
    [$property] = MarketplaceFixtures::stay(['name' => 'Sunset Villas']);
    [$restaurant] = MarketplaceFixtures::dining(['name' => 'Ember Kitchen']);

    $this->get(route('marketplace.home'))
        ->assertOk()
        ->assertSee('Sunset Villas')
        ->assertSee('Ember Kitchen')
        ->assertSee('Boracay');
});

it('lists only published stays in search results', function () {
    [$owner, $tenant] = MarketplaceFixtures::business();

    MarketplaceFixtures::property($tenant, $owner, [
        'name' => 'Published Stay',
        'status' => 'published',
        'published_at' => now(),
    ]);
    MarketplaceFixtures::property($tenant, $owner, ['name' => 'Secret Draft']);
    MarketplaceFixtures::property($tenant, $owner, [
        'name' => 'Suspended Stay',
        'status' => 'suspended',
    ]);

    $this->get(route('marketplace.hotels'))
        ->assertOk()
        ->assertSee('Published Stay')
        ->assertDontSee('Secret Draft')
        ->assertDontSee('Suspended Stay');
});

it('opens a published stay page and hides unpublished rows behind a 404', function () {
    [$property, $owner, $tenant] = MarketplaceFixtures::stay(['name' => 'Sunset Villas']);
    $draft = MarketplaceFixtures::property($tenant, $owner, ['name' => 'Secret Draft']);

    $this->get(route('marketplace.properties.show', $property->slug))
        ->assertOk()
        ->assertSee('Sunset Villas');

    $this->get(route('marketplace.properties.show', $draft->slug))
        ->assertNotFound();
});

it('filters stays by destination, price ceiling and party size', function () {
    MarketplaceFixtures::stay([
        'name' => 'Budget Nipa',
        'base_price' => 2500,
        'max_guests' => 2,
    ]);

    MarketplaceFixtures::stay([
        'name' => 'Cliffside Manor',
        'base_price' => 20000,
        'max_guests' => 8,
        'location_id' => MarketplaceFixtures::location('Cebu City')->id,
        'city' => 'Cebu City',
    ], 'Highland Escapes');

    $this->get(route('marketplace.hotels', ['location' => 'boracay']))
        ->assertOk()
        ->assertSee('Budget Nipa')
        ->assertDontSee('Cliffside Manor');

    $this->get(route('marketplace.hotels', ['price_max' => 5000]))
        ->assertOk()
        ->assertSee('Budget Nipa')
        ->assertDontSee('Cliffside Manor');

    $this->get(route('marketplace.hotels', ['guests' => 6]))
        ->assertOk()
        ->assertSee('Cliffside Manor')
        ->assertDontSee('Budget Nipa');
});

it('finds stays by keyword', function () {
    MarketplaceFixtures::stay(['name' => 'Sunset Villas'], 'Sunset Host');
    MarketplaceFixtures::stay(['name' => 'Pine Lodge'], 'Pine Host');

    $this->get(route('marketplace.hotels', ['q' => 'sunset']))
        ->assertOk()
        ->assertSee('Sunset Villas')
        ->assertDontSee('Pine Lodge');
});

it('requires authentication for the wish list', function () {
    $this->get(route('marketplace.favorites.index'))
        ->assertRedirect(route('login'));

    $this->post(route('marketplace.favorites.store', ['property', 1]))
        ->assertRedirect(route('login'));

    $this->delete(route('marketplace.favorites.destroy', ['property', 1]))
        ->assertRedirect(route('login'));
});

it('saves and removes a published stay from the wish list', function () {
    [$property] = MarketplaceFixtures::stay(['name' => 'Sunset Villas']);
    $guest = User::factory()->create();
    $this->actingAs($guest);

    $this->post(route('marketplace.favorites.store', ['property', $property->getKey()]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('favorites', [
        'user_id' => $guest->id,
        'favoritable_type' => 'property',
        'favoritable_id' => $property->getKey(),
    ]);

    $this->get(route('marketplace.favorites.index'))
        ->assertOk()
        ->assertSee('Sunset Villas');

    $this->delete(route('marketplace.favorites.destroy', ['property', $property->getKey()]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('favorites', [
        'user_id' => $guest->id,
        'favoritable_id' => $property->getKey(),
    ]);
});

it('keeps the wish list strictly personal', function () {
    [$property] = MarketplaceFixtures::stay(['name' => 'Sunset Villas']);

    $guestA = User::factory()->create();
    $guestB = User::factory()->create();

    // Created directly (not over HTTP) so guest A's flash message cannot
    // leak into guest B's page through the shared test session.
    MarketplaceFixtures::favorite($guestA, $property);

    $this->actingAs($guestB)
        ->get(route('marketplace.favorites.index'))
        ->assertOk()
        ->assertDontSee('Sunset Villas');

    $this->assertDatabaseHas('favorites', [
        'user_id' => $guestA->id,
        'favoritable_id' => $property->getKey(),
    ]);
});

it('refuses to save unpublished stays to the wish list', function () {
    [$owner, $tenant] = MarketplaceFixtures::business();
    $draft = MarketplaceFixtures::property($tenant, $owner, ['name' => 'Secret Draft']);

    $guest = User::factory()->create();

    $this->actingAs($guest)
        ->post(route('marketplace.favorites.store', ['property', $draft->getKey()]))
        ->assertNotFound();

    $this->assertDatabaseMissing('favorites', [
        'user_id' => $guest->id,
        'favoritable_id' => $draft->getKey(),
    ]);
});

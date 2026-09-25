<?php

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\RestaurantManagement\Models\MenuCategory;
use App\Modules\RestaurantManagement\Models\MenuItem;
use App\Modules\RestaurantManagement\Models\RestaurantTable;
use App\Support\ModuleService;
use Illuminate\Validation\ValidationException;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 09 (Restaurant Management) — host-side profile, menu builder with
| modifiers/add-ons, floor plan, public menu, gating and tenant isolation.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    PropertyManagementFixtures::bootstrap();
});

function enableRestaurantModule(Tenant $tenant): void
{
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'restaurant')->firstOrFail(), $tenant);
}

/** @return array{0: User, 1: Tenant, 2: Restaurant} */
function restaurantHost(string $name = 'Kitchen A'): array
{
    [$owner, $tenant] = MarketplaceFixtures::business($name);
    enableRestaurantModule($tenant);
    $restaurant = MarketplaceFixtures::restaurant($tenant, $owner, ['name' => $name.' Grill']);
    PropertyManagementFixtures::login($owner, $tenant);

    return [$owner, $tenant, $restaurant];
}

/** Burger ₱250 with Add-ons: Cheese +30, Bacon +50, Egg +25 (master plan example). */
function burger(Restaurant $restaurant): MenuItem
{
    MarketplaceFixtures::asListing($restaurant);

    $category = MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Burgers']);
    $item = MenuItem::create(['restaurant_id' => $restaurant->id, 'menu_category_id' => $category->id, 'name' => 'Burger', 'price' => 250]);
    $addons = $item->modifierGroups()->create(['name' => 'Add-ons']);

    foreach (['Cheese' => 30, 'Bacon' => 50, 'Egg' => 25] as $name => $price) {
        $addons->options()->create(['name' => $name, 'price' => $price]);
    }

    return $item->refresh();
}

it('refuses the restaurant area while the restaurant module is not active', function () {
    [$owner, $tenant] = MarketplaceFixtures::business();
    PropertyManagementFixtures::login($owner, $tenant);

    $this->get(route('restaurants.index'))->assertForbidden();

    enableRestaurantModule($tenant);

    $this->get(route('restaurants.index'))->assertOk();
});

it('creates a draft restaurant with opening hours and cuisines', function () {
    [$owner, $tenant] = MarketplaceFixtures::business();
    enableRestaurantModule($tenant);
    PropertyManagementFixtures::login($owner, $tenant);

    $cuisine = \App\Modules\Marketplace\Models\Cuisine::query()->value('id');

    $this->post(route('restaurants.store'), [
        'name' => 'Casa Luz',
        'price_level' => 2,
        'reservations_enabled' => '1',
        'hours' => ['monday' => '11:00–22:00', 'tuesday' => ''],
        'cuisines' => [$cuisine],
    ])->assertRedirect();

    $restaurant = Restaurant::query()->where('name', 'Casa Luz')->firstOrFail();

    expect($restaurant->status)->toBe(Restaurant::STATUS_DRAFT)
        ->and($restaurant->opening_hours)->toBe(['monday' => '11:00–22:00'])
        ->and($restaurant->reservations_enabled)->toBeTrue()
        ->and($restaurant->delivery_enabled)->toBeFalse()
        ->and($restaurant->cuisines()->count())->toBe(1);

    $this->post(route('restaurants.publish', $restaurant))->assertRedirect();
    expect($restaurant->refresh()->isPublished())->toBeTrue();
});

it('hides another tenant restaurant and its menu rows behind a 404', function () {
    [, , $mine] = restaurantHost('Kitchen A');
    [$ownerB, $tenantB] = MarketplaceFixtures::business('Kitchen B');
    $foreign = MarketplaceFixtures::restaurant($tenantB, $ownerB, ['name' => 'Foreign']);
    $foreignItem = burger($foreign);

    [$ownerA] = [User::query()->whereKey($mine->host_id)->first()];
    PropertyManagementFixtures::login($ownerA, Tenant::find($mine->tenant_id));
    MarketplaceFixtures::asTenant(null);

    $this->get(route('restaurants.show', $foreign))->assertNotFound();
    $this->get(route('restaurants.menu', $foreign))->assertNotFound();
    // Foreign item id through my own restaurant is also a 404.
    $this->delete(route('restaurants.items.destroy', [$mine, $foreignItem->id]))->assertNotFound();

    expect(MenuItem::query()->withoutGlobalScopes()->whereKey($foreignItem->id)->exists())->toBeTrue();
});

it('lets front desk view the menu but not change it', function () {
    [, $tenant, $restaurant] = restaurantHost();
    $desk = MarketplaceFixtures::member($tenant, 'front_desk');
    PropertyManagementFixtures::login($desk, $tenant);

    $this->get(route('restaurants.menu', $restaurant))->assertOk();
    $this->get(route('restaurants.tables', $restaurant))->assertOk();
    $this->post(route('restaurants.categories.store', $restaurant), ['name' => 'Mains'])->assertForbidden();
    $this->post(route('restaurants.tables.store', $restaurant), ['label' => 'T1', 'seats' => 2])->assertForbidden();
});

it('builds a menu with categories, items and add-ons', function () {
    [, , $restaurant] = restaurantHost();

    $this->post(route('restaurants.categories.store', $restaurant), ['name' => 'Burgers'])->assertSessionHasNoErrors();
    $category = $restaurant->menuCategories()->firstOrFail();

    $this->post(route('restaurants.items.store', $restaurant), [
        'menu_category_id' => $category->id, 'name' => 'Burger', 'price' => 250,
    ])->assertSessionHasNoErrors();
    $item = $restaurant->menuItems()->firstOrFail();

    $this->post(route('restaurants.groups.store', [$restaurant, $item->id]), ['name' => 'Add-ons', 'min_select' => 0])->assertSessionHasNoErrors();
    $group = $item->modifierGroups()->firstOrFail();

    $this->post(route('restaurants.options.store', [$restaurant, $item->id, $group->id]), ['name' => 'Cheese', 'price' => 30])->assertSessionHasNoErrors();
    $this->post(route('restaurants.options.store', [$restaurant, $item->id, $group->id]), ['name' => 'No onions', 'price' => ''])->assertSessionHasNoErrors();

    expect($group->options()->pluck('price', 'name')->map(fn ($p) => (float) $p)->all())->toBe(['Cheese' => 30.0, 'No onions' => 0.0]);

    $this->get(route('restaurants.menu', $restaurant))->assertOk()->assertSee('Burger')->assertSee('Cheese');

    // Duplicate category names per restaurant are rejected.
    $this->post(route('restaurants.categories.store', $restaurant), ['name' => 'Burgers'])->assertSessionHasErrors('name');
    // Non-empty categories cannot be removed.
    $this->delete(route('restaurants.categories.destroy', [$restaurant, $category->id]))->assertSessionHasErrors('category');
});

it('rejects an item filed under another restaurant category', function () {
    [$owner, $tenant, $restaurant] = restaurantHost();
    $other = MarketplaceFixtures::restaurant($tenant, $owner, ['name' => 'Sister Cafe']);
    $otherCategory = burger($other)->category;
    PropertyManagementFixtures::login($owner, $tenant);

    $this->post(route('restaurants.items.store', $restaurant), [
        'menu_category_id' => $otherCategory->id, 'name' => 'Sneaky', 'price' => 10,
    ])->assertSessionHasErrors('menu_category_id');
});

it('prices an item with its add-ons and enforces modifier rules', function () {
    [, , $restaurant] = restaurantHost();
    $item = burger($restaurant);
    $options = $item->modifierGroups->first()->options->pluck('id', 'name');

    expect($item->priceWith([]))->toBe('250.00')
        ->and($item->priceWith([$options['Cheese'], $options['Bacon']]))->toBe('330.00')
        ->and($item->priceWith([$options['Cheese'], $options['Bacon'], $options['Egg']]))->toBe('355.00');

    // A required single-choice group.
    $size = $item->modifierGroups()->create(['name' => 'Patty', 'min_select' => 1, 'max_select' => 1]);
    $single = $size->options()->create(['name' => 'Single', 'price' => 0]);
    $double = $size->options()->create(['name' => 'Double', 'price' => 90]);

    expect(fn () => $item->priceWith([$options['Cheese']]))->toThrow(ValidationException::class)
        ->and(fn () => $item->priceWith([$single->id, $double->id]))->toThrow(ValidationException::class)
        ->and($item->priceWith([$double->id, $options['Egg']]))->toBe('365.00');

    // Options from another item are rejected.
    $fries = MenuItem::create(['restaurant_id' => $restaurant->id, 'menu_category_id' => $item->menu_category_id, 'name' => 'Fries', 'price' => 90]);
    $dip = $fries->modifierGroups()->create(['name' => 'Dip'])->options()->create(['name' => 'Aioli', 'price' => 20]);
    expect(fn () => $item->priceWith([$single->id, $dip->id]))->toThrow(ValidationException::class);

    // Unavailable options cannot be chosen.
    $options = $item->modifierGroups()->first()->options;
    $options->firstWhere('name', 'Egg')->update(['is_available' => false]);
    expect(fn () => $item->priceWith([$single->id, $options->firstWhere('name', 'Egg')->id]))->toThrow(ValidationException::class);
});

it('toggles item availability', function () {
    [, , $restaurant] = restaurantHost();
    $item = burger($restaurant);

    $this->patch(route('restaurants.items.update', [$restaurant, $item->id]), [
        'menu_category_id' => $item->menu_category_id, 'name' => 'Burger', 'price' => 260,
    ])->assertSessionHasNoErrors();

    expect($item->refresh()->is_available)->toBeFalse()->and((float) $item->price)->toBe(260.0);
});

it('manages dining areas and tables', function () {
    [, , $restaurant] = restaurantHost();

    $this->post(route('restaurants.areas.store', $restaurant), ['name' => 'Terrace'])->assertSessionHasNoErrors();
    $area = $restaurant->diningAreas()->firstOrFail();

    $this->post(route('restaurants.tables.store', $restaurant), ['label' => 'T1', 'seats' => 4, 'dining_area_id' => $area->id, 'status' => 'active'])->assertSessionHasNoErrors();
    $this->post(route('restaurants.tables.store', $restaurant), ['label' => 'T1', 'seats' => 2])->assertSessionHasErrors('label');

    $table = $restaurant->tables()->firstOrFail();
    $this->patch(route('restaurants.tables.update', [$restaurant, $table->id]), ['label' => 'T1', 'seats' => 6, 'status' => 'inactive'])->assertSessionHasNoErrors();
    expect($table->refresh()->seats)->toBe(6)->and($table->status)->toBe(RestaurantTable::STATUS_INACTIVE);

    $this->delete(route('restaurants.areas.destroy', [$restaurant, $area->id]))->assertSessionHasNoErrors();
    expect($table->refresh()->dining_area_id)->toBeNull();

    $this->get(route('restaurants.tables', $restaurant))->assertOk()->assertSee('T1');
});

it('shows the menu with add-ons on the public restaurant page', function () {
    [, , $restaurant] = restaurantHost();
    $item = burger($restaurant);
    MenuItem::create(['restaurant_id' => $restaurant->id, 'menu_category_id' => $item->menu_category_id, 'name' => 'Milkshake', 'price' => 120, 'is_available' => false]);
    $restaurant->publish();

    auth()->logout();
    MarketplaceFixtures::asTenant(null);

    $this->get(route('marketplace.restaurants.show', $restaurant->slug))
        ->assertOk()
        ->assertSee('Burgers')
        ->assertSee('₱250.00')
        ->assertSee('Cheese +₱30.00')
        ->assertSee('Milkshake')
        ->assertSee('Sold out');
});

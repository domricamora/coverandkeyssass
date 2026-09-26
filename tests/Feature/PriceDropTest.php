<?php

use App\Models\User;
use App\Modules\Marketplace\Models\Favorite;
use App\Modules\Marketplace\Notifications\PriceDropped;
use Illuminate\Support\Facades\Notification;
use Tests\Support\MarketplaceFixtures;

/*
| Price-drop alerts on wish-listed stays: the price is captured when saved,
| a drop of 5%+ alerts once, small moves and unpublished stays never do.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('alerts a guest once when a saved stay gets at least 5% cheaper', function () {
    MarketplaceFixtures::bootstrap();
    Notification::fake();
    [$property] = MarketplaceFixtures::stay(['base_price' => 5000]);
    $guest = User::factory()->create();
    $favorite = MarketplaceFixtures::favorite($guest, $property);
    MarketplaceFixtures::asTenant(null);

    expect((float) $favorite->saved_price)->toBe(5000.0);

    $property->withoutGlobalScope('tenant')->whereKey($property->id)->update(['base_price' => 4900]); // 2% — too small
    $this->artisan('favorites:price-drops')->assertSuccessful();
    Notification::assertNothingSent();

    $property->withoutGlobalScope('tenant')->whereKey($property->id)->update(['base_price' => 4500]); // 10% off
    $this->artisan('favorites:price-drops')->expectsOutput('Price-drop alerts sent: 1');
    Notification::assertSentTo($guest, PriceDropped::class, fn (PriceDropped $n) => str_contains($n->message(), '10% less'));
    expect((float) Favorite::query()->find($favorite->id)->saved_price)->toBe(4500.0);

    $this->artisan('favorites:price-drops')->expectsOutput('Price-drop alerts sent: 0'); // same drop: no repeat
});

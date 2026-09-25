<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Modules\Marketplace\Models\Location;
use App\Modules\Marketplace\Models\Property;
use App\Modules\PlatformAdmin\Models\ContentReport;
use Tests\Support\MarketplaceFixtures;

/*
| Phase 29 (Marketplace Administration) — ranking with sponsored / featured
| placement and boosts, listing and host verification, categories and
| locations, and guest reports handled in the moderation queue.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    MarketplaceFixtures::bootstrap();
    $this->admin = User::factory()->create();
    $this->admin->assignPlatformRole();
});

function listed(string $name, array $placement = [], string $business = 'Biz'): Property
{
    [$property] = MarketplaceFixtures::stay(['name' => $name], $business.' '.$name);
    $property->forceFill($placement)->save();
    MarketplaceFixtures::asTenant(null);

    return $property;
}

it('ranks sponsored, then live featured, then by score — and labels sponsorship', function () {
    listed('Loved Small', ['avg_rating' => 5.0, 'reviews_count' => 1]);          // 50.2
    listed('Loved Big', ['avg_rating' => 4.5, 'reviews_count' => 50]);            // 55
    listed('Paid Spot', ['sponsored_until' => now()->addWeek(), 'avg_rating' => 3.0]);
    listed('Old Feature', ['is_featured' => true, 'featured_until' => now()->subDay()]);
    listed('Live Feature', ['is_featured' => true, 'avg_rating' => 1.0]);
    listed('Boosted', ['ranking_boost' => 50, 'avg_rating' => 1.0]);            // 60

    $this->get(route('marketplace.hotels'))->assertOk()
        ->assertSeeInOrder(['Paid Spot', 'Live Feature', 'Boosted', 'Loved Big', 'Loved Small', 'Old Feature'])
        ->assertSee('Sponsored');
});

it('lets Super Admins set placement and verify listings and hosts, shown to guests', function () {
    $property = listed('Seaside Inn');

    $this->actingAs($this->admin)->post(route('admin.listings.placement', ['properties', $property->id]), [
        'is_featured' => '1', 'featured_until' => now()->addMonth()->toDateString(), 'sponsored_until' => '', 'ranking_boost' => 10, 'verified' => '1',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $fresh = Property::query()->withoutGlobalScope('tenant')->find($property->id);
    expect($fresh->isFeaturedNow())->toBeTrue()
        ->and($fresh->ranking_boost)->toBe(10)
        ->and($fresh->isVerified())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'platform.listing.placement')->exists())->toBeTrue();

    $this->post(route('admin.listings.placement', ['properties', $property->id]), ['ranking_boost' => 99])->assertSessionHasErrors('ranking_boost');

    $this->post(route('admin.tenants.verify', $property->tenant_id), ['verified' => 1, 'note' => 'Mayor permit checked'])->assertRedirect();
    $this->get(route('marketplace.properties.show', $property->slug))->assertOk()->assertSee('Verified property')->assertSee('Verified host');

    $this->post(route('admin.tenants.verify', $property->tenant_id), ['verified' => 0])->assertRedirect();
    $this->get(route('marketplace.properties.show', $property->slug))->assertDontSee('Verified host');

    $guest = User::factory()->create();
    $this->actingAs($guest)->post(route('admin.listings.placement', ['properties', $property->id]), ['ranking_boost' => 50])->assertForbidden();
    $this->post(route('admin.tenants.verify', $property->tenant_id), ['verified' => 1])->assertForbidden();
});

it('manages categories and locations, refusing to delete ones in use', function () {
    $this->actingAs($this->admin)->get(route('admin.taxonomy.index'))->assertOk()->assertSee('Boracay');

    $this->post(route('admin.taxonomy.store', 'locations'), ['name' => 'Siargao Isle Test', 'region' => 'Caraga', 'flag' => '1'])->assertSessionHasNoErrors();
    $siargao = Location::query()->where('slug', 'siargao-isle-test')->sole();
    expect($siargao->is_featured)->toBeTrue()->and($siargao->region)->toBe('Caraga');

    $this->post(route('admin.taxonomy.store', 'locations'), ['name' => 'Siargao Isle Test'])->assertSessionHasErrors('slug');
    $this->put(route('admin.taxonomy.update', ['locations', $siargao->id]), ['name' => 'Siargao North', 'slug' => 'siargao-north'])->assertSessionHasNoErrors();
    expect($siargao->refresh())->name->toBe('Siargao North')->is_featured->toBeFalse();

    listed('Island Stay');
    $boracay = MarketplaceFixtures::location('Boracay');
    $this->delete(route('admin.taxonomy.destroy', ['locations', $boracay->id]))->assertSessionHasErrors('taxonomy');
    $this->delete(route('admin.taxonomy.destroy', ['locations', $siargao->id]))->assertSessionHasNoErrors();
    expect(Location::query()->find($siargao->id))->toBeNull();

    $this->post(route('admin.taxonomy.store', 'cuisines'), ['name' => 'Kapampangan', 'flag' => '1'])->assertSessionHasNoErrors();
    $this->get(route('admin.taxonomy.index', 'cuisines'))->assertSee('Kapampangan');
    $this->get(route('admin.taxonomy.index', 'colours'))->assertNotFound();
});

it('takes guest reports once each and closes them from the moderation queue', function () {
    $property = listed('Too Good To Be True');
    [$guest, $other] = [User::factory()->create(), User::factory()->create()];

    $this->post(route('listings.report', ['properties', $property->slug]), ['reason' => 'scam'])->assertRedirect(route('login'));

    $this->actingAs($guest)->get(route('marketplace.properties.show', $property->slug))->assertSee('Report this listing');
    $this->post(route('listings.report', ['properties', $property->slug]), ['reason' => 'scam', 'details' => 'Asked me to pay by bank transfer'])->assertRedirect();
    $this->post(route('listings.report', ['properties', $property->slug]), ['reason' => 'scam'])->assertRedirect();
    $this->post(route('listings.report', ['properties', $property->slug]), ['reason' => 'aliens'])->assertSessionHasErrors('reason');
    $this->actingAs($other)->post(route('listings.report', ['properties', $property->slug]), ['reason' => 'misleading'])->assertRedirect();
    $this->post(route('listings.report', ['properties', 'no-such-place']), ['reason' => 'scam'])->assertNotFound();

    expect(ContentReport::query()->where('status', 'open')->count())->toBe(2);

    $report = ContentReport::query()->where('user_id', $guest->id)->sole();
    $this->actingAs($this->admin)->get(route('admin.moderation.index'))->assertOk()->assertSee('Too Good To Be True')->assertSee('Asked me to pay by bank transfer');
    $this->post(route('admin.moderation.resolve', $report), ['action' => 'suspend'])->assertSessionHasErrors('note');
    $this->post(route('admin.moderation.resolve', $report), ['action' => 'suspend', 'note' => 'Confirmed scam'])->assertRedirect();

    expect(Property::query()->withoutGlobalScope('tenant')->find($property->id)->status)->toBe(Property::STATUS_SUSPENDED)
        ->and(ContentReport::query()->where('status', 'resolved')->count())->toBe(2);
    $this->post(route('admin.moderation.resolve', $report), ['action' => 'dismiss'])->assertSessionHasErrors('report');
    $this->get(route('marketplace.properties.show', $property->slug))->assertNotFound();

    $this->actingAs($guest)->get(route('admin.moderation.index'))->assertForbidden();
});

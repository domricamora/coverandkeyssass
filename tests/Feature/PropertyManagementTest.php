<?php

use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Services\AvailabilityService;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 04 (Property Management) — host-side surface.
|
| Coverage: module gating, tenant isolation, per-role permissions,
| property profile CRUD + publish lifecycle, media (photos/videos),
| room types → rooms inventory, rate periods, availability blocks
| and property staff assignments.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    PropertyManagementFixtures::bootstrap();
});

function propertyPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Sunset Villas',
        'tagline' => 'Beachfront suites',
        'description' => 'A quiet stretch of White Beach.',
        'max_guests' => 4,
        'bedrooms' => 2,
        'beds' => 2,
        'bathrooms' => 2,
        'base_price' => 5500,
        'currency' => 'PHP',
        'city' => 'Boracay',
        'check_in_time' => '14:00',
        'check_out_time' => '11:00',
    ], $overrides);
}

it('requires authentication for property management', function () {
    $this->get(route('properties.index'))->assertRedirect(route('login'));
});

it('bounces members without a selected business to business selection', function () {
    [$owner, $tenant] = MarketplaceFixtures::business();
    PropertyManagementFixtures::enableModule($tenant);

    $this->actingAs($owner)
        ->get(route('properties.index'))
        ->assertRedirect(route('tenants.index'));
});

it('refuses the property area while the property module is not active', function () {
    [$owner, $tenant] = MarketplaceFixtures::business();
    PropertyManagementFixtures::login($owner, $tenant);

    $this->get(route('properties.index'))->assertForbidden();

    PropertyManagementFixtures::enableModule($tenant);

    $this->get(route('properties.index'))->assertOk();
});

it('lists only the active business properties', function () {
    [$ownerA, $tenantA] = PropertyManagementFixtures::businessWithModule('Hotel A');
    [$ownerB, $tenantB] = MarketplaceFixtures::business('Hotel B');

    PropertyManagementFixtures::property($tenantA, $ownerA, ['name' => 'Alpha Resort']);
    PropertyManagementFixtures::property($tenantB, $ownerB, ['name' => 'Beta Lodge']);

    $this->get(route('properties.index'))
        ->assertOk()
        ->assertSee('Alpha Resort')
        ->assertDontSee('Beta Lodge');
});

it('hides another tenant property behind a 404 even with permissions', function () {
    [$ownerA, $tenantA] = PropertyManagementFixtures::businessWithModule('Hotel A');
    [$ownerB, $tenantB] = MarketplaceFixtures::business('Hotel B');

    $foreign = PropertyManagementFixtures::property($tenantB, $ownerB, ['name' => 'Foreign']);

    PropertyManagementFixtures::login($ownerA, $tenantA);

    $this->get(route('properties.show', $foreign))->assertNotFound();
    $this->patch(route('properties.update', $foreign), propertyPayload())->assertNotFound();
});

it('lets front desk view but not create', function () {
    [$owner, $tenant] = PropertyManagementFixtures::businessWithModule();
    $desk = MarketplaceFixtures::member($tenant, 'front_desk');

    PropertyManagementFixtures::login($desk, $tenant);

    $this->get(route('properties.index'))->assertOk();

    $this->post(route('properties.store'), propertyPayload())->assertForbidden();
});

it('keeps plain staff members out of property management', function () {
    [$owner, $tenant] = PropertyManagementFixtures::businessWithModule();
    $staff = MarketplaceFixtures::member($tenant, 'staff');

    PropertyManagementFixtures::login($staff, $tenant);

    $this->get(route('properties.index'))->assertForbidden();
});

it('creates a draft property owned by the host and audits it', function () {
    [$owner, $tenant] = PropertyManagementFixtures::businessWithModule();

    $this->post(route('properties.store'), propertyPayload())
        ->assertRedirect();

    $property = Property::query()->where('name', 'Sunset Villas')->firstOrFail();

    expect($property->status)->toBe(Property::STATUS_DRAFT)
        ->and($property->host_id)->toBe($owner->id)
        ->and((int) $property->tenant_id)->toBe((int) $tenant->id)
        ->and($property->slug)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'property.created',
        'auditable_id' => $property->id,
    ]);
});

it('validates property creation input', function () {
    [$owner, $tenant] = PropertyManagementFixtures::businessWithModule();

    $this->post(route('properties.store'), propertyPayload(['name' => '', 'base_price' => -5]))
        ->assertSessionHasErrors(['name', 'base_price']);

    expect(Property::query()->where('name', 'Sunset Villas')->exists())->toBeFalse();
});

it('updates the profile, policies and amenities', function () {
    [$owner, $tenant] = PropertyManagementFixtures::businessWithModule();
    $property = PropertyManagementFixtures::property($tenant, $owner);

    $amenityIds = \App\Modules\Marketplace\Models\Amenity::query()->pluck('id')->take(2)->all();

    $this->patch(route('properties.update', $property), propertyPayload([
        'policy_cancellation' => 'Free cancellation until 3 days before check-in.',
        'amenities' => $amenityIds,
    ]))->assertRedirect(route('properties.show', $property));

    $property->refresh();

    expect($property->policies['cancellation'])->toBe('Free cancellation until 3 days before check-in.')
        ->and($property->amenities()->pluck('amenities.id')->all())->toEqual($amenityIds);

    $this->assertDatabaseHas('audit_logs', ['action' => 'property.updated', 'auditable_id' => $property->id]);
});

it('publishes and unpublishes through the audit trail', function () {
    [$owner, $tenant] = PropertyManagementFixtures::businessWithModule();
    $property = PropertyManagementFixtures::property($tenant, $owner, ['slug' => 'sunset-villas']);

    $this->post(route('properties.publish', $property))->assertRedirect();
    expect($property->refresh()->status)->toBe(Property::STATUS_PUBLISHED)
        ->and($property->published_at)->not->toBeNull();

    // The marketplace now finds it.
    $this->get(route('marketplace.properties.show', 'sunset-villas'))->assertOk();

    $this->post(route('properties.unpublish', $property))->assertRedirect();
    expect($property->refresh()->status)->toBe(Property::STATUS_DRAFT);

    $this->get(route('marketplace.properties.show', 'sunset-villas'))->assertNotFound();

    $this->assertDatabaseHas('audit_logs', ['action' => 'property.published', 'auditable_id' => $property->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'property.unpublished', 'auditable_id' => $property->id]);
});

it('front desk cannot publish', function () {
    [$owner, $tenant] = PropertyManagementFixtures::businessWithModule();
    $property = PropertyManagementFixtures::property($tenant, $owner);
    $desk = MarketplaceFixtures::member($tenant, 'front_desk');

    PropertyManagementFixtures::login($desk, $tenant);

    $this->post(route('properties.publish', $property))->assertForbidden();
    expect($property->refresh()->status)->toBe(Property::STATUS_DRAFT);
});

it('manages photos and videos with a single cover image', function () {
    [$owner, $tenant] = PropertyManagementFixtures::businessWithModule();
    $property = PropertyManagementFixtures::property($tenant, $owner);

    $this->post(route('properties.media.store', $property), [
        'url' => 'https://cdn.example.test/one.jpg',
        'kind' => 'image',
        'alt' => 'Pool',
    ])->assertRedirect()->assertSessionHas('success');

    $this->post(route('properties.media.store', $property), [
        'url' => 'https://cdn.example.test/two.jpg',
        'kind' => 'image',
    ])->assertRedirect();

    $this->post(route('properties.media.store', $property), [
        'url' => 'https://cdn.example.test/tour.mp4',
        'kind' => 'video',
    ])->assertRedirect();

    $photos = $property->media()->where('kind', 'image')->get();
    expect($photos->count())->toBe(2)
        ->and($photos->where('path', 'https://cdn.example.test/one.jpg')->first()->is_cover)->toBeTrue();

    $video = $property->media()->where('kind', 'video')->firstOrFail();
    expect($video->is_cover)->toBeFalse();

    // Gallery exposes only images; the video is listed separately.
    expect($property->galleryUrls())->toEqual(['https://cdn.example.test/one.jpg', 'https://cdn.example.test/two.jpg'])
        ->and($property->videoUrls())->toEqual(['https://cdn.example.test/tour.mp4']);

    // Promote the second photo to cover.
    $second = $photos->firstWhere('path', 'https://cdn.example.test/two.jpg');
    $this->post(route('properties.media.cover', [$property, $second]))->assertRedirect();

    expect($second->refresh()->is_cover)->toBeTrue()
        ->and($photos->firstWhere('path', 'https://cdn.example.test/one.jpg')->refresh()->is_cover)->toBeFalse();

    // Remove a photo.
    $this->delete(route('properties.media.destroy', [$property, $second]))->assertRedirect();
    expect(\App\Modules\Marketplace\Models\Media::query()->whereKey($second->getKey())->exists())->toBeFalse();
});

it('refuses media of another property behind a 404', function () {
    [$ownerA, $tenantA] = PropertyManagementFixtures::businessWithModule('Hotel A');
    [$ownerB, $tenantB] = MarketplaceFixtures::business('Hotel B');

    $own = PropertyManagementFixtures::property($tenantA, $ownerA);
    $foreign = PropertyManagementFixtures::property($tenantB, $ownerB);

    MarketplaceFixtures::photos($foreign, 1);

    $foreignMedia = $foreign->media()->firstOrFail();

    PropertyManagementFixtures::login($ownerA, $tenantA);

    $this->post(route('properties.media.cover', [$own, $foreignMedia]))->assertNotFound();
    $this->delete(route('properties.media.destroy', [$own, $foreignMedia]))->assertNotFound();
});

it('creates, updates and removes room types', function () {
    [$owner, $tenant] = PropertyManagementFixtures::businessWithModule();
    $property = PropertyManagementFixtures::property($tenant, $owner);

    $this->post(route('properties.room-types.store', $property), [
        'name' => 'Deluxe Room',
        'max_guests' => 2,
        'base_price' => 3500,
        'currency' => 'PHP',
    ])->assertRedirect()->assertSessionHas('success');

    $type = $property->roomTypes()->where('name', 'Deluxe Room')->firstOrFail();
    expect($type->base_price)->toBe('3500.00');

    // Duplicate name on the same property is rejected.
    $this->post(route('properties.room-types.store', $property), [
        'name' => 'Deluxe Room',
        'max_guests' => 2,
        'base_price' => 4000,
    ])->assertSessionHasErrors('name');

    $this->patch(route('properties.room-types.update', [$property, $type]), [
        'name' => 'Deluxe Suite',
        'max_guests' => 3,
        'base_price' => 4200,
    ])->assertRedirect();

    expect($type->refresh()->name)->toBe('Deluxe Suite')
        ->and($type->max_guests)->toBe(3);

    $this->delete(route('properties.room-types.destroy', [$property, $type]))->assertRedirect();
    expect($property->roomTypes()->whereKey($type->getKey())->exists())->toBeFalse();
});

it('blocks room type removal while rooms are still assigned', function () {
    [$owner, $tenant] = PropertyManagementFixtures::businessWithModule();
    $property = PropertyManagementFixtures::property($tenant, $owner);
    $type = PropertyManagementFixtures::roomType($property);
    PropertyManagementFixtures::room($type);

    $this->delete(route('properties.room-types.destroy', [$property, $type]))
        ->assertSessionHasErrors();

    expect($property->roomTypes()->whereKey($type->getKey())->exists())->toBeTrue();
});

it('manages the physical room inventory per property', function () {
    [$owner, $tenant] = PropertyManagementFixtures::businessWithModule();
    $property = PropertyManagementFixtures::property($tenant, $owner);
    $type = PropertyManagementFixtures::roomType($property);

    $this->post(route('properties.rooms.store', [$property, $type]), [
        'room_number' => '101',
        'floor' => 1,
    ])->assertRedirect()->assertSessionHas('success');

    $room = $type->rooms()->where('room_number', '101')->firstOrFail();

    // Same number, same property → rejected.
    $this->post(route('properties.rooms.store', [$property, $type]), [
        'room_number' => '101',
    ])->assertSessionHasErrors('room_number');

    // Same number, another property → fine.
    $other = PropertyManagementFixtures::property($tenant, $owner, ['name' => 'Second Stay']);
    $otherType = PropertyManagementFixtures::roomType($other);
    $this->post(route('properties.rooms.store', [$other, $otherType]), [
        'room_number' => '101',
    ])->assertRedirect()->assertSessionHas('success');

    $this->patch(route('properties.rooms.update', [$property, $type, $room]), [
        'room_number' => '101',
        'status' => 'maintenance',
    ])->assertRedirect();

    expect($room->refresh()->status)->toBe('maintenance');

    $this->delete(route('properties.rooms.destroy', [$property, $type, $room]))->assertRedirect();

    expect($type->rooms()->whereKey($room->getKey())->exists())->toBeFalse();
});

it('prevents the manager from deleting inventory but allows updates', function () {
    [$owner, $tenant] = PropertyManagementFixtures::businessWithModule();
    $property = PropertyManagementFixtures::property($tenant, $owner);
    $type = PropertyManagementFixtures::roomType($property);
    $manager = MarketplaceFixtures::member($tenant, 'manager');

    PropertyManagementFixtures::login($manager, $tenant);

    $this->patch(route('properties.room-types.update', [$property, $type]), [
        'name' => 'Deluxe Room',
        'max_guests' => 2,
        'base_price' => 3900,
    ])->assertRedirect();

    expect($type->refresh()->base_price)->toBe('3900.00');

    $this->delete(route('properties.room-types.destroy', [$property, $type]))->assertForbidden();

    expect($property->roomTypes()->whereKey($type->getKey())->exists())->toBeTrue();
});

it('manages rate periods without overlapping ranges', function () {
    [$owner, $tenant] = PropertyManagementFixtures::businessWithModule();
    $property = PropertyManagementFixtures::property($tenant, $owner);
    $type = PropertyManagementFixtures::roomType($property);

    $this->post(route('properties.rates.store', [$property, $type]), [
        'name' => 'High season',
        'start_date' => '2030-08-01',
        'end_date' => '2030-08-10',
        'nightly_price' => 5000,
        'min_stay_nights' => 2,
    ])->assertRedirect()->assertSessionHas('success');

    $rate = $type->ratePeriods()->where('name', 'High season')->firstOrFail();

    // Overlapping → rejected.
    $this->post(route('properties.rates.store', [$property, $type]), [
        'name' => 'Overlap',
        'start_date' => '2030-08-05',
        'end_date' => '2030-08-20',
        'nightly_price' => 6000,
    ])->assertSessionHasErrors('start_date');

    // Touching ranges are fine (the 11th starts after the 10th ends).
    $this->post(route('properties.rates.store', [$property, $type]), [
        'name' => 'Low season',
        'start_date' => '2030-08-11',
        'end_date' => '2030-08-20',
        'nightly_price' => 3000,
    ])->assertRedirect();

    $this->delete(route('properties.rates.destroy', [$property, $type, $rate]))->assertRedirect();

    expect($type->ratePeriods()->whereKey($rate->getKey())->exists())->toBeFalse();
});

it('rejects rate ranges that end before they start', function () {
    [$owner, $tenant] = PropertyManagementFixtures::businessWithModule();
    $property = PropertyManagementFixtures::property($tenant, $owner);
    $type = PropertyManagementFixtures::roomType($property);

    $this->post(route('properties.rates.store', [$property, $type]), [
        'start_date' => '2030-08-10',
        'end_date' => '2030-08-01',
        'nightly_price' => 5000,
    ])->assertSessionHasErrors('end_date');
});

it('resolves availability through the service and reopens after blocks', function () {
    [$owner, $tenant] = PropertyManagementFixtures::businessWithModule();
    $property = PropertyManagementFixtures::property($tenant, $owner);
    $type = PropertyManagementFixtures::roomType($property);
    $roomA = PropertyManagementFixtures::room($type, ['room_number' => '101']);
    PropertyManagementFixtures::room($type, ['room_number' => '102']);

    $service = app(AvailabilityService::class);

    // Nothing blocked yet.
    expect($service->blockedRoomIds($property, '2030-09-10', '2030-09-12'))->toEqual([])
        ->and($service->availableRoomCount($type, '2030-09-10', '2030-09-12'))->toBe(2);

    // Block a single room → one remains sellable.
    $block = PropertyManagementFixtures::block($type, ['room_id' => $roomA->getKey()]);

    expect($service->blockedRoomIds($property, '2030-09-10', '2030-09-12'))->toEqual([$roomA->getKey()])
        ->and($service->availableRoomCount($type, '2030-09-10', '2030-09-12'))->toBe(1)
        // Dates outside the block are unaffected.
        ->and($service->availableRoomCount($type, '2030-10-01', '2030-10-03'))->toBe(2);

    // Whole-type block empties the room type.
    $typeBlock = PropertyManagementFixtures::block($type, ['start_date' => '2030-10-01', 'end_date' => '2030-10-03']);

    expect($service->availableRoomCount($type, '2030-10-01', '2030-10-03'))->toBe(0);

    // Removing blocks reopens the dates.
    $this->delete(route('properties.availability.destroy', [$property, $block]))->assertRedirect();
    $this->delete(route('properties.availability.destroy', [$property, $typeBlock]))->assertRedirect();

    expect($service->availableRoomCount($type, '2030-09-10', '2030-09-12'))->toBe(2)
        ->and($service->availableRoomCount($type, '2030-10-01', '2030-10-03'))->toBe(2);
});

it('blocks availability over the HTTP surface', function () {
    [$owner, $tenant] = PropertyManagementFixtures::businessWithModule();
    $property = PropertyManagementFixtures::property($tenant, $owner);
    $type = PropertyManagementFixtures::roomType($property);

    $this->post(route('properties.availability.store', $property), [
        'room_type_id' => $type->getKey(),
        'start_date' => '2030-09-10',
        'end_date' => '2030-09-12',
        'reason' => 'maintenance',
        'note' => 'AC replacement',
    ])->assertRedirect()->assertSessionHas('success');

    expect($property->availabilityBlocks()->where('room_type_id', $type->getKey())->exists())->toBeTrue();

    // A room from another property cannot be blocked here.
    $other = PropertyManagementFixtures::property($tenant, $owner, ['name' => 'Second Stay']);
    $otherType = PropertyManagementFixtures::roomType($other);
    $otherRoom = PropertyManagementFixtures::room($otherType);

    $this->post(route('properties.availability.store', $property), [
        'room_type_id' => $type->getKey(),
        'room_id' => $otherRoom->getKey(),
        'start_date' => '2030-09-10',
        'end_date' => '2030-09-12',
        'reason' => 'maintenance',
    ])->assertSessionHasErrors('room_id');
});

it('assigns business members as property staff', function () {
    [$owner, $tenant] = PropertyManagementFixtures::businessWithModule();
    $property = PropertyManagementFixtures::property($tenant, $owner);
    $member = MarketplaceFixtures::member($tenant, 'staff');

    $this->post(route('properties.staff.store', $property), [
        'email' => $member->email,
        'role' => 'front_desk',
    ])->assertRedirect()->assertSessionHas('success');

    $assignment = $property->staff()->where('user_id', $member->id)->firstOrFail();
    expect($assignment->role)->toBe('front_desk');

    $this->assertDatabaseHas('audit_logs', ['action' => 'property.staff.assigned']);

    // Re-assigning updates instead of duplicating.
    $this->post(route('properties.staff.store', $property), [
        'email' => $member->email,
        'role' => 'housekeeping',
    ])->assertRedirect();

    expect($property->staff()->where('user_id', $member->id)->count())->toBe(1)
        ->and($assignment->refresh()->role)->toBe('housekeeping');

    // Role change via PATCH.
    $this->patch(route('properties.staff.update', [$property, $assignment]), ['role' => 'maintenance'])
        ->assertRedirect();
    expect($assignment->refresh()->role)->toBe('maintenance');

    // Removal.
    $this->delete(route('properties.staff.destroy', [$property, $assignment]))->assertRedirect();
    expect($property->staff()->where('user_id', $member->id)->exists())->toBeFalse();
});

it('refuses staff who are not active members of the business', function () {
    [$owner, $tenant] = PropertyManagementFixtures::businessWithModule();
    $property = PropertyManagementFixtures::property($tenant, $owner);

    $outsider = \App\Models\User::factory()->create();

    $this->post(route('properties.staff.store', $property), [
        'email' => $outsider->email,
        'role' => 'front_desk',
    ])->assertSessionHasErrors('email');

    expect($property->staff()->count())->toBe(0);
});







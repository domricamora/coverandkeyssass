<?php

namespace Tests\Support;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Models\AvailabilityBlock;
use App\Modules\PropertyManagement\Models\PropertyStaff;
use App\Modules\PropertyManagement\Models\RatePeriod;
use App\Modules\PropertyManagement\Models\Room;
use App\Modules\PropertyManagement\Models\RoomType;
use App\Support\ModuleService;
use Database\Seeders\MarketplaceReferenceSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

/**
 * Shared fixtures for the Phase 04 (Property Management) feature tests.
 *
 * Builds on MarketplaceFixtures (business provisioning, properties) and
 * adds the module-engine state the host area depends on: the `property`
 * module must be enabled for a tenant before its management routes open.
 */
final class PropertyManagementFixtures
{
    /** RBAC + module catalogue + marketplace reference data. */
    public static function bootstrap(): void
    {
        test()->seed(PermissionSeeder::class);
        test()->seed(RoleSeeder::class);
        test()->seed(ModuleSeeder::class);
        test()->seed(MarketplaceReferenceSeeder::class);
    }

    /**
     * A business whose owner is logged in with the tenant session set and
     * the `property` module enabled — the state every host-area test starts
     * from unless it is specifically testing gating.
     *
     * @return array{0: User, 1: Tenant}
     */
    public static function businessWithModule(string $name = 'Hotel A'): array
    {
        [$owner, $tenant] = MarketplaceFixtures::business($name);

        self::enableModule($tenant);
        self::login($owner, $tenant);

        return [$owner, $tenant];
    }

    public static function enableModule(Tenant $tenant): void
    {
        app(ModuleService::class)->enableForTenant(
            Module::query()->where('slug', 'property')->firstOrFail(),
            $tenant,
        );
    }

    public static function disableModule(Tenant $tenant): void
    {
        app(ModuleService::class)->disableForTenant(
            Module::query()->where('slug', 'property')->firstOrFail(),
            $tenant,
        );
    }

    /** Point the authenticated session at a business (host area). */
    public static function login(User $user, Tenant $tenant): void
    {
        test()->actingAs($user);
        session(['tenant_id' => $tenant->id]);
    }

    public static function property(Tenant $tenant, User $owner, array $attributes = []): Property
    {
        return MarketplaceFixtures::property($tenant, $owner, $attributes);
    }

    public static function roomType(Property $property, array $attributes = []): RoomType
    {
        MarketplaceFixtures::asListing($property);

        return RoomType::create(array_merge([
            'property_id' => $property->getKey(),
            'name' => 'Deluxe Room',
            'max_guests' => 2,
            'base_price' => 3500,
            'currency' => 'PHP',
            'status' => 'active',
        ], $attributes));
    }

    public static function room(RoomType $roomType, array $attributes = []): Room
    {
        MarketplaceFixtures::asListing($roomType->property);

        return Room::create(array_merge([
            'property_id' => $roomType->property_id,
            'room_type_id' => $roomType->getKey(),
            'room_number' => '101',
            'status' => 'active',
        ], $attributes));
    }

    public static function ratePeriod(RoomType $roomType, array $attributes = []): RatePeriod
    {
        MarketplaceFixtures::asListing($roomType->property);

        return RatePeriod::create(array_merge([
            'room_type_id' => $roomType->getKey(),
            'name' => 'High season',
            'start_date' => '2030-08-01',
            'end_date' => '2030-08-10',
            'nightly_price' => 5000,
        ], $attributes));
    }

    public static function block(RoomType $roomType, array $attributes = []): AvailabilityBlock
    {
        MarketplaceFixtures::asListing($roomType->property);

        return AvailabilityBlock::create(array_merge([
            'property_id' => $roomType->property_id,
            'room_type_id' => $roomType->getKey(),
            'start_date' => '2030-09-10',
            'end_date' => '2030-09-12',
            'reason' => 'maintenance',
        ], $attributes));
    }

    public static function staff(Property $property, User $user, array $attributes = []): PropertyStaff
    {
        MarketplaceFixtures::asListing($property);

        return PropertyStaff::create(array_merge([
            'user_id' => $user->getKey(),
            'role' => 'front_desk',
        ], $attributes));
    }
}

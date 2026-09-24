<?php

namespace Tests\Support;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Models\RoomType;
use App\Support\ModuleService;

/**
 * Shared fixtures for the Phase 05 (Booking Engine) and Phase 06
 * (Customer Portal) tests, layered on PropertyManagementFixtures.
 */
final class BookingFixtures
{
    public static function bootstrap(): void
    {
        PropertyManagementFixtures::bootstrap();
    }

    public static function enableModule(Tenant $tenant): void
    {
        // `booking` depends on `property`; the module engine enables both.
        app(ModuleService::class)->enableForTenant(
            Module::query()->where('slug', 'booking')->firstOrFail(),
            $tenant,
        );
    }

    /**
     * Logged-in owner of a business running the booking module, with one
     * property holding a "Deluxe Room" type (3500 weekday / 4500 weekend).
     *
     * @return array{0: User, 1: Tenant, 2: Property, 3: RoomType}
     */
    public static function hotel(int $rooms = 1, string $name = 'Hotel A'): array
    {
        [$owner, $tenant] = MarketplaceFixtures::business($name);
        self::enableModule($tenant);
        PropertyManagementFixtures::login($owner, $tenant);

        $property = PropertyManagementFixtures::property($tenant, $owner, ['name' => $name.' Resort']);
        $type = PropertyManagementFixtures::roomType($property, ['weekend_price' => 4500]);

        for ($i = 1; $i <= $rooms; $i++) {
            PropertyManagementFixtures::room($type, ['room_number' => (string) (100 + $i)]);
        }

        return [$owner, $tenant, $property, $type];
    }

    /** Reserve straight through the service (inside the property's tenant). */
    public static function reserve(Property $property, RoomType $type, array $attributes = [], string $source = Booking::SOURCE_MANUAL, ?User $customer = null): Booking
    {
        $service = app(BookingService::class);

        return $service->asTenantOf($property, fn () => $service->reserve($property, array_merge([
            'check_in' => '2030-09-05',
            'check_out' => '2030-09-08',
            'rooms' => [['room_type_id' => $type->id, 'quantity' => 1]],
            'adults' => 2,
            'guest_name' => 'Juan Dela Cruz',
        ], $attributes), $source, $customer));
    }

    public static function payload(RoomType $type, array $overrides = []): array
    {
        return array_merge([
            'property' => $type->property->slug,
            'source' => Booking::SOURCE_MANUAL,
            'check_in' => '2030-09-05',
            'check_out' => '2030-09-08',
            'rooms' => [['room_type_id' => $type->id, 'quantity' => 1]],
            'adults' => 2,
            'guest_name' => 'Juan Dela Cruz',
        ], $overrides);
    }
}

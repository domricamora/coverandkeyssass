<?php

namespace Database\Seeders;

use App\Modules\Marketplace\Models\Location;
use App\Modules\Marketplace\Models\PropertyType;
use App\Modules\Marketplace\Models\Amenity;
use App\Modules\Marketplace\Models\Cuisine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Marketplace reference data: destinations, property categories, amenities
 * and cuisines. Environment-agnostic and idempotent — safe to run in
 * production because it never touches tenant-owned rows.
 */
class MarketplaceReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedLocations();
        $this->seedPropertyTypes();
        $this->seedAmenities();
        $this->seedCuisines();
    }

    private function seedLocations(): void
    {
        $locations = [
            ['Boracay', 'Aklan', 'Western Visayas', 11.9674, 121.9248, true, 1, 'White Beach, Diniwid and Bulabog — the island resort strip and its quiet coves.'],
            ['Cebu City', 'Cebu', 'Central Visayas', 10.3157, 123.8854, true, 2, 'The metro gateway: business hotels, heritage streets and island hopping.'],
            ['El Nido', 'Palawan', 'Mimaropa', 11.1949, 119.4113, true, 3, 'Limestone cliffs, lagoons and beachfront resorts on Bacuit Bay.'],
            ['Siargao', 'Surigao del Norte', 'Caraga', 9.8482, 126.0460, true, 4, 'Cloud 9 surf breaks, island hopping and slow-living guesthouses.'],
            ['Baguio', 'Benguet', 'Cordillera', 16.4023, 120.5960, false, 5, 'Cool highland air: pine cabins, B&Bs and long-stay apartments.'],
            ['Tagaytay', 'Cavite', 'Calabarzon', 14.1153, 120.9621, false, 6, 'Taal views, weekend villas and garden restaurants an hour from Manila.'],
            ['Panglao', 'Bohol', 'Central Visayas', 9.5769, 123.7600, false, 7, 'Dive resorts and beachfront villas on Bohol\'s southwest coast.'],
            ['Laiya', 'Batangas', 'Calabarzon', 13.6720, 121.2960, false, 8, 'Weekend beach houses and dive camps on Batangas\' San Juan coast.'],
        ];

        foreach ($locations as [$name, $region, $country, $lat, $lng, $featured, $sort, $description]) {
            Location::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'region' => $region,
                    'country_code' => 'PH',
                    'country' => 'Philippines',
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'is_featured' => $featured,
                    'sort_order' => $sort,
                    'description' => $description,
                ],
            );
        }
    }

    private function seedPropertyTypes(): void
    {
        $types = [
            ['Hotel', 'hotel', 'Serviced rooms with a front desk and daily housekeeping.', 'building-2', 1],
            ['Resort', 'resort', 'Full-service leisure property with facilities and grounds.', 'sun', 2],
            ['Bed & Breakfast', 'bed_and_breakfast', 'Rooms plus breakfast, usually owner-run and small.', 'coffee', 3],
            ['Guesthouse', 'guesthouse', 'Simple private rooms with shared common areas.', 'home', 4],
            ['Apartment', 'apartment', 'Self-contained unit with kitchen and living space.', 'key', 5],
            ['Condo', 'condo', 'Managed unit in a residential building, often with amenities.', 'layers', 6],
            ['Villa', 'villa', 'Private house with exclusive use of the whole property.', 'sparkles', 7],
            ['Hostel', 'hostel', 'Dorm or private rooms with a communal vibe.', 'users', 8],
            ['Private Room', 'private_room', 'One room inside a larger property.', 'door-open', 9],
            ['Entire Property', 'entire_property', 'The whole home or estate, bookable with no shared spaces.', 'house', 10],
        ];

        foreach ($types as [$name, $category, $description, $icon, $sort]) {
            PropertyType::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'category' => $category,
                    'description' => $description,
                    'icon' => $icon,
                    'sort_order' => $sort,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedAmenities(): void
    {
        $amenities = [
            ['Wi-Fi', 'connectivity', 'wifi'],
            ['Air conditioning', 'comfort', 'wind'],
            ['Hot shower', 'comfort', 'droplet'],
            ['Breakfast included', 'food', 'coffee'],
            ['Swimming pool', 'facilities', 'waves'],
            ['Beachfront', 'location', 'sun'],
            ['Sea view', 'location', 'eye'],
            ['Free parking', 'transport', 'car'],
            ['Airport transfer', 'transport', 'plane'],
            ['Fitness centre', 'facilities', 'dumbbell'],
            ['Spa', 'facilities', 'flower'],
            ['Restaurant on site', 'food', 'utensils'],
            ['Bar', 'food', 'glass'],
            ['Kitchen', 'comfort', 'chef-hat'],
            ['Workspace', 'comfort', 'laptop'],
            ['Pet friendly', 'policy', 'paw-print'],
            ['Laundry service', 'services', 'shirt'],
            ['24-hour front desk', 'services', 'bell'],
        ];

        foreach ($amenities as $index => [$name, $category, $icon]) {
            Amenity::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'category' => $category,
                    'icon' => $icon,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedCuisines(): void
    {
        $cuisines = ['Filipino', 'Seafood', 'Grill & BBQ', 'Italian', 'Japanese', 'Chinese', 'Spanish', 'Vegetarian', 'Cafe & Bakery'];

        foreach ($cuisines as $index => $name) {
            Cuisine::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $index + 1, 'is_active' => true],
            );
        }
    }
}
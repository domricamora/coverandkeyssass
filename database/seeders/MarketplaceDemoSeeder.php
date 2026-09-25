<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Marketplace\Models\Amenity;
use App\Modules\Marketplace\Models\Cuisine;
use App\Modules\Marketplace\Models\Favorite;
use App\Modules\Marketplace\Models\Location;
use App\Modules\Marketplace\Models\Media;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Models\PropertyType;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Marketplace\Models\Review;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo catalogue for local development and screenshots.
 *
 * Creates three businesses with published properties and restaurants, guest
 * accounts, reviews and wish lists so the marketplace has something real to
 * render. It REFUSES to run in production and uses a well-known demo
 * password — never run it against a live system.
 *
 * Requires the reference catalogue (locations, types, amenities, cuisines),
 * which it seeds first.
 */
class MarketplaceDemoSeeder extends Seeder
{
    /** Fallback illustrations for listings without photos in public/img/demo. */
    private const SAMPLE_IMAGES = [
        'img/sample/coast.svg',
        'img/sample/ridge.svg',
        'img/sample/skyline.svg',
        'img/sample/garden.svg',
    ];

    /** Demo accounts only — documented in README/DEPLOYMENT. */
    private const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('MarketplaceDemoSeeder is for development only.');
        }

        $this->call(MarketplaceReferenceSeeder::class);

        $context = app(TenantContext::class);
        $guests = $this->seedGuests();

        foreach ($this->businesses() as $business) {
            $context->forget();

            $tenant = $this->tenant($business);
            $owner = $this->owner($business, $tenant);
            $this->enableAllModules($tenant);

            $context->set($tenant);

            foreach ($business['properties'] as $index => $definition) {
                $this->property($owner, $definition, $index);
            }

            foreach ($business['restaurants'] as $index => $definition) {
                $this->restaurant($owner, $definition, $index);
            }
        }

        $context->forget();

        $this->seedReviews($guests);
        $this->seedFavorites($guests);
        $this->call(DemoOperationsSeeder::class);

        $this->command?->info(sprintf(
            'Demo marketplace seeded: %d properties, %d restaurants, %d users.',
            Property::withoutGlobalScope('tenant')->count(),
            Restaurant::withoutGlobalScope('tenant')->count(),
            User::count(),
        ));
    }

    private function businesses(): array
    {
        return [
            [
                'slug' => 'aplaya-beach-resort',
                'name' => 'Aplaya Beach Resort',
                'business_type' => 'resort',
                'owner_name' => 'Maria Consuelo Tan',
                'owner_email' => 'owner@aplaya.example.test',
                'properties' => [
                    [
                        'name' => 'Aplaya Beachfront Suites',
                        'location' => 'Boracay',
                        'type' => 'Resort',
                        'tagline' => 'Two-bedroom suites opening straight onto White Beach.',
                        'description' => 'Airy island suites with a shaded veranda, daily breakfast and a beach attendant who sets up your loungers before you wake. The dive shop, spa and pool bar are on the same grounds.',
                        'address' => 'Station 2, White Beach Path',
                        'city' => 'Boracay',
                        'region' => 'Aklan',
                        'max_guests' => 4,
                        'bedrooms' => 2,
                        'beds' => 2,
                        'bathrooms' => 1,
                        'base_price' => 8500,
                        'weekend_price' => 9800,
                        'cleaning_fee' => 1200,
                        'highlights' => [
                            'Thirty steps from the White Beach path',
                            'Breakfast served on your own veranda',
                            'Free airport transfer from Caticlan',
                            'PADI dive shop on the property',
                        ],
                        'amenities' => ['Wi-Fi', 'Air conditioning', 'Hot shower', 'Breakfast included', 'Swimming pool', 'Beachfront', 'Airport transfer', 'Restaurant on site'],
                        'featured' => true,
                    ],
                    [
                        'name' => 'Diniwid Garden Villa',
                        'location' => 'Boracay',
                        'type' => 'Villa',
                        'tagline' => 'A whole villa above Diniwid, with a private pool deck.',
                        'description' => 'Three bedrooms, an open kitchen and a plunge pool framed by palms. Diniwid beach is a three-minute walk downhill; the villa comes with a driver on call and a cook on request.',
                        'address' => 'Diniwid Road, Barangay Balabag',
                        'city' => 'Boracay',
                        'region' => 'Aklan',
                        'max_guests' => 6,
                        'bedrooms' => 3,
                        'beds' => 4,
                        'bathrooms' => 2,
                        'base_price' => 14500,
                        'weekend_price' => 16800,
                        'cleaning_fee' => 2000,
                        'highlights' => [
                            'Private plunge pool and sun deck',
                            'Full kitchen with a market shopping service',
                            'Sunset views over Diniwid from the terrace',
                            'Sleeps six in three separate bedrooms',
                        ],
                        'amenities' => ['Wi-Fi', 'Air conditioning', 'Hot shower', 'Kitchen', 'Sea view', 'Free parking', 'Breakfast included', 'Pet friendly'],
                        'featured' => false,
                    ],
[
                        'name' => 'Bulabog Sunset Loft',
                        'location' => 'Boracay',
                        'type' => 'Apartment',
                        'tagline' => 'Loft above Bulabog beach — draft listing, not published yet.',
                        'description' => 'The kitesurf-side loft is still being photographed and priced. It stays invisible on the public marketplace until the owner publishes it.',
                        'address' => 'Bulabog Beach Road',
                        'city' => 'Boracay',
                        'region' => 'Aklan',
                        'max_guests' => 2,
                        'bedrooms' => 1,
                        'beds' => 1,
                        'bathrooms' => 1,
                        'base_price' => 4200,
                        'weekend_price' => 4600,
                        'cleaning_fee' => 600,
                        'highlights' => [
                            'Kitesurf storage on the ground floor',
                            'Two minutes from the Bulabog launch area',
                        ],
                        'amenities' => ['Wi-Fi', 'Air conditioning', 'Kitchen', 'Workspace'],
                        'status' => Property::STATUS_DRAFT,
                        'featured' => false,
                    ],
                ],
                'restaurants' => [
                    [
                        'name' => 'Salt & Ember Grill',
                        'location' => 'Boracay',
                        'tagline' => 'Charcoal grill and line-caught seafood, feet in the sand.',
                        'description' => 'A beachfront grill where the catch is iced on the counter and cooked over coconut charcoal. Local pork belly, whole snapper and kinilaw share the menu with a short list of island wines.',
                        'address' => 'Station 3 Beachfront',
                        'city' => 'Boracay',
                        'region' => 'Aklan',
                        'phone' => '+63 36 288 4411',
                        'price_level' => 3,
                        'hours' => [
                            'monday' => '11:00–22:00',
                            'tuesday' => '11:00–22:00',
                            'wednesday' => '11:00–22:00',
                            'thursday' => '11:00–22:00',
                            'friday' => '11:00–23:30',
                            'saturday' => '11:00–23:30',
                            'sunday' => '11:00–21:00',
                        ],
                        'reservations_enabled' => true,
                        'delivery_enabled' => false,
                        'cuisines' => ['Grill & BBQ', 'Seafood', 'Filipino'],
                        'featured' => true,
                    ],
                ],
            ],
            [
                'slug' => 'kalye-suite-company',
                'name' => 'Kalye Suites & Coffee',
                'business_type' => 'hotel',
                'owner_name' => 'Diego Ramos',
                'owner_email' => 'owner@kalyecoffee.example.test',
                'properties' => [
                    [
                        'name' => 'Kalye Loft Suites',
                        'location' => 'Cebu City',
                        'type' => 'Apartment',
                        'tagline' => 'Compact lofts a block from the Cebu business district.',
                        'description' => 'Designed for two guests on a work trip: a proper desk, blackout curtains, a kitchenette and a laundry service that returns shirts the next morning.',
                        'address' => '18 F. Ramos Street',
                        'city' => 'Cebu City',
                        'region' => 'Cebu',
                        'max_guests' => 2,
                        'bedrooms' => 1,
                        'beds' => 1,
                        'bathrooms' => 1,
                        'base_price' => 3200,
                        'weekend_price' => 3800,
                        'cleaning_fee' => 600,
                        'highlights' => [
                            'Walk to Fuente Osmeña and the IT Park link',
                            'Kitchenette, washer and a real work desk',
                            '24-hour front desk and secure parking',
                            'Self check-in for late arrivals',
                        ],
                        'amenities' => ['Wi-Fi', 'Air conditioning', 'Hot shower', 'Kitchen', 'Workspace', 'Laundry service', '24-hour front desk'],
                        'featured' => false,
                    ],
                    [
                        'name' => 'Baguio Pine Cabin',
                        'location' => 'Baguio',
                        'type' => 'Guesthouse',
                        'tagline' => 'A pine-clad cabin with a fireplace above Session Road.',
                        'description' => 'Two bedrooms and a loft tucked into the pines of Mines View. The fireplace is lit for you at check-in, and the balcony looks straight down the valley on clear mornings.',
                        'address' => '7 Outlook Drive, Mines View',
                        'city' => 'Baguio',
                        'region' => 'Benguet',
                        'max_guests' => 5,
                        'bedrooms' => 2,
                        'beds' => 3,
                        'bathrooms' => 1,
                        'base_price' => 4800,
                        'weekend_price' => 5600,
                        'cleaning_fee' => 800,
                        'highlights' => [
                            'Working fireplace stocked with pine wood',
                            'Valley views from the balcony and loft',
                            'Five minutes from Mines View Park',
                            'Pet friendly with a fenced garden',
                        ],
                        'amenities' => ['Wi-Fi', 'Hot shower', 'Free parking', 'Kitchen', 'Pet friendly', 'Workspace'],
                        'featured' => true,
                    ],
                ],
                'restaurants' => [
                    [
                        'name' => 'Kalye Coffee & Kitchen',
                        'location' => 'Cebu City',
                        'tagline' => 'Single-origin brews and all-day Filipino plates.',
                        'description' => 'A corner café roasting Cebu and Benguet beans, with a kitchen running from tapsilog at 7am to sinigang until close. Plenty of plugs, long tables and a plant-filled courtyard.',
                        'address' => '18 F. Ramos Street',
                        'city' => 'Cebu City',
                        'region' => 'Cebu',
                        'phone' => '+63 32 412 7788',
                        'price_level' => 2,
                        'hours' => [
                            'monday' => '07:00–21:00',
                            'tuesday' => '07:00–21:00',
                            'wednesday' => '07:00–21:00',
                            'thursday' => '07:00–21:00',
                            'friday' => '07:00–22:00',
                            'saturday' => '08:00–22:00',
                            'sunday' => '08:00–20:00',
                        ],
                        'reservations_enabled' => false,
                        'delivery_enabled' => true,
                        'cuisines' => ['Cafe & Bakery', 'Filipino', 'Vegetarian'],
                        'featured' => true,
                    ],
                ],
            ],
            [
                'slug' => 'nido-cove-escapes',
                'name' => 'Nido Cove Escapes',
                'business_type' => 'bnb',
                'owner_name' => 'Ana Villareal',
                'owner_email' => 'owner@nidocove.example.test',
                'properties' => [
                    [
                        'name' => 'El Nido Cliffside Bungalows',
                        'location' => 'El Nido',
                        'type' => 'Bed & Breakfast',
                        'tagline' => 'Breakfast on a cliff deck above Bacuit Bay.',
                        'description' => 'Thatched bungalows stepped down a limestone cliff, each with a sea-facing deck. Island-hopping boats collect guests from the private pier below the breakfast deck.',
                        'address' => 'Sitio Caalan, Barangay Masagana',
                        'city' => 'El Nido',
                        'region' => 'Palawan',
                        'max_guests' => 3,
                        'bedrooms' => 1,
                        'beds' => 2,
                        'bathrooms' => 1,
                        'base_price' => 6900,
                        'weekend_price' => 7400,
                        'cleaning_fee' => 900,
                        'highlights' => [
                            'Sunrise over Bacuit Bay from your deck',
                            'Breakfast deck with homemade coconut jam',
                            'Private pier for island-hopping tours',
                            'Kayaks and snorkel gear included',
                        ],
                        'amenities' => ['Wi-Fi', 'Breakfast included', 'Sea view', 'Airport transfer', 'Hot shower', 'Swimming pool'],
                        'featured' => true,
                    ],
                    [
                        'name' => 'Siargao Surf Shack',
                        'location' => 'Siargao',
                        'type' => 'Entire Property',
                        'tagline' => 'The whole shack, two minutes from Cloud 9.',
                        'description' => 'A whole-house rental for surf crews: board racks, an outdoor shower, hammocks under the palms and a kitchen that copes with six hungry people.',
                        'address' => 'Cloud 9 Access Road, General Luna',
                        'city' => 'General Luna',
                        'region' => 'Surigao del Norte',
                        'max_guests' => 4,
                        'bedrooms' => 2,
                        'beds' => 3,
                        'bathrooms' => 1,
                        'base_price' => 3900,
                        'weekend_price' => 4500,
                        'cleaning_fee' => 500,
                        'highlights' => [
                            'Board racks and an outdoor rinse shower',
                            'Two minutes on foot to Cloud 9 tower',
                            'Scooter rental arranged on arrival',
                            'Hammocks, BBQ pit and a fenced yard',
                        ],
                        'amenities' => ['Wi-Fi', 'Air conditioning', 'Kitchen', 'Free parking', 'Pet friendly', '24-hour front desk'],
                        'featured' => false,
                    ],
                ],
                'restaurants' => [
                    [
                        'name' => 'Cove & Catch Seafood House',
                        'location' => 'El Nido',
                        'tagline' => 'Whatever the boats bring in, cooked Spanish-Filipino style.',
                        'description' => 'A twelve-table house above the water where the menu is written each morning after the fishing boats land. Grilled lapu-lapu, garlic prawns, and a paella that needs an order the day before.',
                        'address' => 'Calle Hama, Barangay Buena Suerte',
                        'city' => 'El Nido',
                        'region' => 'Palawan',
                        'phone' => '+63 917 884 2210',
                        'price_level' => 4,
                        'hours' => [
                            'monday' => '17:00–22:00',
                            'tuesday' => '17:00–22:00',
                            'wednesday' => '17:00–22:00',
                            'thursday' => '17:00–22:00',
                            'friday' => '17:00–23:00',
                            'saturday' => '12:00–23:00',
                            'sunday' => '12:00–22:00',
                        ],
                        'reservations_enabled' => true,
                        'delivery_enabled' => false,
                        'cuisines' => ['Seafood', 'Filipino', 'Spanish'],
                        'featured' => false,
                    ],
                ],
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Accounts
    // ------------------------------------------------------------------

    /** Guests have no tenant — they only browse, save and review. */
    private function seedGuests(): array
    {
        $guests = [
            ['Andrea Lim', 'andrea.lim@example.test'],
            ['Miguel Santos', 'miguel.santos@example.test'],
            ['Hannah Reyes', 'hannah.reyes@example.test'],
            ['Kenji Nakamura', 'kenji.nakamura@example.test'],
            ['Sofia Delgado', 'sofia.delgado@example.test'],
            ['Paul Villanueva', 'paul.villanueva@example.test'],
        ];

        return array_map(fn (array $guest) => User::query()->updateOrCreate(
            ['email' => $guest[1]],
            [
                'name' => $guest[0],
                'password' => self::DEMO_PASSWORD,
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        ), $guests);
    }

    private function tenant(array $business): Tenant
    {
        return Tenant::query()->updateOrCreate(
            ['slug' => $business['slug']],
            [
                'name' => $business['name'],
                'business_type' => $business['business_type'],
                'status' => 'active',
                'country_code' => 'PH',
                'currency' => 'PHP',
            ],
        );
    }

    private function owner(array $business, Tenant $tenant): User
    {
        $owner = User::query()->updateOrCreate(
            ['email' => $business['owner_email']],
            [
                'name' => $business['owner_name'],
                'password' => self::DEMO_PASSWORD,
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        // System roles must exist before the owner role can be attached.
        app(TenantContext::class)->set($tenant);
        RoleSeeder::ensureTenantRoles($tenant->id);

        $tenant->users()->syncWithoutDetaching([
            $owner->id => ['status' => 'active', 'joined_at' => now()],
        ]);

        $ownerRole = $tenant->roles()->where('slug', 'owner')->firstOrFail();
        $owner->assignTenantRole($tenant, $ownerRole);

        return $owner;
    }

    // ------------------------------------------------------------------
    // Listings
    // ------------------------------------------------------------------

    private function property(User $owner, array $definition, int $index): void
    {
        $published = ($definition['status'] ?? Property::STATUS_PUBLISHED) === Property::STATUS_PUBLISHED;

        $property = Property::query()->updateOrCreate(
            ['slug' => Str::slug($definition['name'])],
            [
                'tenant_id' => app(TenantContext::class)->id(),
                'host_id' => $owner->id,
                'location_id' => $this->referenceId(Location::class, $definition['location']),
                'property_type_id' => $this->referenceId(PropertyType::class, $definition['type']),
                'name' => $definition['name'],
                'tagline' => $definition['tagline'],
                'description' => $definition['description'],
                'address_line' => $definition['address'],
                'city' => $definition['city'],
                'region' => $definition['region'],
                'country_code' => 'PH',
                'max_guests' => $definition['max_guests'],
                'bedrooms' => $definition['bedrooms'],
                'beds' => $definition['beds'],
                'bathrooms' => $definition['bathrooms'],
                'base_price' => $definition['base_price'],
                'weekend_price' => $definition['weekend_price'],
                'cleaning_fee' => $definition['cleaning_fee'],
                'currency' => 'PHP',
                'highlights' => $definition['highlights'],
                'policies' => [
                    'children_welcome' => true,
                    'pets' => in_array('Pet friendly', $definition['amenities'], true),
                    'smoking' => false,
                    'cancellation' => 'Free cancellation up to 7 days before check-in.',
                ],
                'status' => $definition['status'] ?? Property::STATUS_PUBLISHED,
                'published_at' => $published ? now() : null,
                'is_featured' => $definition['featured'],
            ],
        );

        $property->amenities()->sync(
            Amenity::query()
                ->whereIn('slug', collect($definition['amenities'])->map(fn ($name) => Str::slug($name))->all())
                ->pluck('id')
                ->all()
        );

        $this->attachMedia($property, $index);
        $this->seedRooms($property);
    }

    private function restaurant(User $owner, array $definition, int $index): void
    {
        $restaurant = Restaurant::query()->updateOrCreate(
            ['slug' => Str::slug($definition['name'])],
            [
                'tenant_id' => app(TenantContext::class)->id(),
                'host_id' => $owner->id,
                'location_id' => $this->referenceId(Location::class, $definition['location']),
                'name' => $definition['name'],
                'tagline' => $definition['tagline'],
                'description' => $definition['description'],
                'address_line' => $definition['address'],
                'city' => $definition['city'],
                'region' => $definition['region'],
                'country_code' => 'PH',
                'phone' => $definition['phone'],
                'price_level' => $definition['price_level'],
                'opening_hours' => $definition['hours'],
                'reservations_enabled' => $definition['reservations_enabled'],
                'delivery_enabled' => $definition['delivery_enabled'],
                'status' => Restaurant::STATUS_PUBLISHED,
                'published_at' => now(),
                'is_featured' => $definition['featured'],
            ],
        );

        $restaurant->cuisines()->sync(
            Cuisine::query()
                ->whereIn('slug', collect($definition['cuisines'])->map(fn ($name) => Str::slug($name))->all())
                ->pluck('id')
                ->all()
        );

        $this->attachMedia($restaurant, $index + 1);
    }

    // ------------------------------------------------------------------
    // Social proof
    // ------------------------------------------------------------------

    /** Deterministic review bodies so repeat seeds produce identical pages. */
    private const REVIEW_SAMPLES = [
        [5, 'Everything the photos promised', 'Check-in was quick and the staff remembered our names by the second day. We would book this again without hesitating.'],
        [4, 'Comfortable and well placed', 'Clean rooms and a great location. The air conditioning was a little loud at night, but everything else was exactly as described.'],
        [5, 'Worth the trip', 'Booked on a whim and it turned out to be the highlight of our holiday. The included breakfast alone was worth it.'],
        [4, 'Great value for the area', 'Honest pricing for what you get, and the team answered every message within an hour.'],
        [3, 'Good, with small rough edges', 'The space is lovely but the Wi-Fi dropped in the evenings. Staff were quick to help when we mentioned it.'],
        [5, 'Faultless from start to finish', 'Spotless, quiet, and the little touches — fresh fruit, local coffee — made it feel personal.'],
    ];

    /**
     * Published guest reviews for every live listing.
     *
     * The tenant context is restored per business because ReviewObserver
     * recalculates avg_rating / reviews_count through the tenant-scoped
     * reviewable relation.
     */
    private function seedReviews(array $guests): void
    {
        $context = app(TenantContext::class);

        foreach ($this->publishedListingsByTenant() as $tenantId => $listings) {
            $context->forget();
            $context->set(Tenant::query()->findOrFail($tenantId));

            foreach ($listings as $listing) {
                foreach (array_slice($guests, 0, 4) as $offset => $guest) {
                    [$rating, $title, $comment] = self::REVIEW_SAMPLES[($listing->id + $offset) % count(self::REVIEW_SAMPLES)];

                    Review::query()->updateOrCreate(
                        [
                            'user_id' => $guest->id,
                            'reviewable_type' => $listing->getMorphClass(),
                            'reviewable_id' => $listing->getKey(),
                        ],
                        [
                            'rating' => $rating,
                            'title' => $title,
                            'comment' => $comment,
                            'status' => Review::STATUS_PUBLISHED,
                            'published_at' => now()->subDays(($listing->id + $offset) % 30),
                        ],
                    );
                }
            }
        }

        $context->forget();
    }

    /** A light wish list per guest so saved-listing pages are not empty. */
    private function seedFavorites(array $guests): void
    {
        $context = app(TenantContext::class);

        foreach ($this->publishedListingsByTenant() as $tenantId => $listings) {
            $context->forget();
            $context->set(Tenant::query()->findOrFail($tenantId));

            foreach ($listings as $index => $listing) {
                foreach (array_slice($guests, 0, 3) as $offset => $guest) {
                    if (($index + $offset) % 3 !== 0) {
                        continue;
                    }

                    Favorite::query()->firstOrCreate([
                        'user_id' => $guest->id,
                        'favoritable_type' => $listing->getMorphClass(),
                        'favoritable_id' => $listing->getKey(),
                    ]);
                }
            }
        }

        $context->forget();
    }

    /**
     * Live (published) properties and restaurants grouped by tenant.
     *
     * @return array<int, \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>>
     */
    private function publishedListingsByTenant(): array
    {
        return collect([Property::class, Restaurant::class])
            ->flatMap(fn (string $class) => $class::withoutGlobalScope('tenant')->published()->get())
            ->groupBy('tenant_id')
            ->all();
    }

    /**
     * Reference rows are seeded from human names slugged with Str::slug
     * (see MarketplaceReferenceSeeder), so demo definitions use the same
     * display names. A missing row is a hard error — a listing silently
     * losing its destination or category is worse than a failed seed.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function referenceId(string $model, string $name): int
    {
        $id = $model::query()->where('slug', Str::slug($name))->value('id');

        if ($id === null) {
            throw new \RuntimeException(sprintf(
                'Reference data missing: no %s for "%s". Run MarketplaceReferenceSeeder first.',
                class_basename($model),
                $name,
            ));
        }

        return (int) $id;
    }

    /**
     * Cover + gallery. Uses the royalty-free photos in public/img/demo
     * (`{listing-slug}-{n}.jpg`, see public/img/demo/CREDITS.md) and falls
     * back to the bundled illustrations for listings without photos.
     * Re-running replaces earlier illustration-only galleries with photos.
     */
    private function attachMedia(Model $listing, int $index): void
    {
        $photos = glob(public_path('img/demo/'.Str::slug($listing->name).'-*.jpg')) ?: [];
        sort($photos);

        $existing = $listing->media()->get();
        $onlyIllustrations = $existing->isNotEmpty() && $existing->every(fn ($m) => str_starts_with($m->path, 'img/sample/'));

        if ($existing->isNotEmpty() && ! ($photos !== [] && $onlyIllustrations)) {
            return;
        }

        $listing->media()->delete();

        $paths = $photos !== []
            ? array_map(fn ($file) => 'img/demo/'.basename($file), $photos)
            : array_map(fn ($slot) => self::SAMPLE_IMAGES[($index + $slot) % count(self::SAMPLE_IMAGES)], [0, 1, 2]);

        foreach (array_values($paths) as $slot => $path) {
            $listing->media()->create([
                'disk' => 'public',
                'path' => $path,
                'alt' => $listing->name.($slot === 0 ? '' : ' photo '.($slot + 1)),
                'is_cover' => $slot === 0,
                'sort_order' => $slot,
            ]);
        }
    }

    /**
     * Two bookable room types with a few rooms each, priced from the listing,
     * so the marketplace "Request to book" flow and the front desk work live.
     */
    private function seedRooms(Property $property): void
    {
        $base = (float) $property->base_price;
        $types = [
            ['Standard '.($property->bedrooms > 1 ? 'Suite' : 'Room'), 'Queen bed, rain shower and a private terrace.', 2, 1, 'Queen', 28, $base, 3],
            ['Deluxe '.($property->bedrooms > 1 ? 'Family Suite' : 'Room'), 'King bed, lounge corner and the best view on the property.', max(3, (int) $property->max_guests), 2, 'King + sofa bed', 42, round($base * 1.45, -2), 2],
        ];

        foreach ($types as $sort => [$name, $description, $guests, $beds, $config, $size, $price, $count]) {
            $type = \App\Modules\PropertyManagement\Models\RoomType::query()->updateOrCreate(
                ['property_id' => $property->id, 'name' => $name],
                [
                    'tenant_id' => $property->tenant_id,
                    'description' => $description,
                    'max_guests' => $guests,
                    'beds' => $beds,
                    'bed_configuration' => $config,
                    'size_sqm' => $size,
                    'base_price' => $price,
                    'weekend_price' => $property->weekend_price ? round($price * 1.15, -2) : null,
                    'currency' => $property->currency,
                    'min_stay_nights' => 1,
                    'status' => \App\Modules\PropertyManagement\Models\RoomType::STATUS_ACTIVE,
                    'sort_order' => $sort,
                ],
            );

            for ($n = 1; $n <= $count; $n++) {
                // firstOrCreate: re-seeding must not reset live housekeeping status.
                \App\Modules\PropertyManagement\Models\Room::query()->firstOrCreate(
                    ['property_id' => $property->id, 'room_number' => (string) (($sort + 1) * 100 + $n)],
                    [
                        'tenant_id' => $property->tenant_id,
                        'room_type_id' => $type->id,
                        'floor' => $sort + 1,
                        'status' => \App\Modules\PropertyManagement\Models\Room::STATUS_ACTIVE,
                        'housekeeping_status' => \App\Modules\PropertyManagement\Models\Room::HK_CLEAN,
                    ],
                );
            }
        }
    }

    /** Demo businesses get every module, paid-up and without a trial clock, so every screen is explorable. */
    private function enableAllModules(Tenant $tenant): void
    {
        $modules = app(\App\Support\ModuleService::class);

        foreach (\App\Models\Module::query()->where('is_core', false)->get() as $module) {
            $modules->enableForTenant($module, $tenant, ['trial_days' => 0]);
        }
    }
}

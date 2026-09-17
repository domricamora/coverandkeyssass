<?php

namespace Tests\Support;

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
use Database\Seeders\MarketplaceReferenceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Shared fixtures for the Phase 03 (Marketplace) feature tests.
 *
 * Everything a test needs to reach a realistic state is assembled here so the
 * test files only describe behaviour:
 *
 *  - reference rows (destinations, types, amenities, cuisines) come from the
 *    real seeder, never from hand-written rows,
 *  - tenant-owned rows (properties, restaurants, reviews, favourites) are
 *    always written with an explicit TenantContext, because BelongsToTenant is
 *    deny-by-default and the Review/Favorite observers resolve tenant-scoped
 *    relations while saving.
 */
final class MarketplaceFixtures
{
    /**
     * RBAC catalogue + marketplace reference data. Runs before each test's
     * own fixtures so slugs used here always exist.
     */
    public static function bootstrap(): void
    {
        test()->seed(PermissionSeeder::class);
        test()->seed(RoleSeeder::class);
        test()->seed(MarketplaceReferenceSeeder::class);
    }

    /**
     * A business account with one confirmed owner, provisioned exactly like
     * TenantController@store does.
     *
     * @return array{0: User, 1: Tenant}
     */
    public static function business(string $name = 'Hotel A', array $attributes = []): array
    {
        $owner = User::factory()->create();

        $tenant = Tenant::create(array_merge([
            'name' => $name,
            'business_type' => 'resort',
            'status' => 'active',
        ], $attributes));

        RoleSeeder::ensureTenantRoles($tenant->id);

        $tenant->users()->attach($owner->id, ['status' => 'active', 'joined_at' => now()]);
        $owner->assignTenantRole($tenant, $tenant->roles()->where('slug', 'owner')->firstOrFail());

        return [$owner, $tenant];
    }

    /** A second member of an existing tenant, holding one of the seeded roles. */
    public static function member(Tenant $tenant, string $role = 'manager', array $attributes = []): User
    {
        $user = User::factory()->create($attributes);

        $tenant->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);
        $user->assignTenantRole($tenant, $tenant->roles()->where('slug', $role)->firstOrFail());

        return $user;
    }

    /** Point TenantContext at a business (or clear it with null). */
    public static function asTenant(?Tenant $tenant): void
    {
        $context = app(TenantContext::class);

        if ($tenant === null) {
            $context->forget();

            return;
        }

        $context->set($tenant);
    }

    /** Point TenantContext at the business owning a listing. */
    public static function asListing(?Model $listing): void
    {
        self::asTenant($listing && $listing->tenant_id
            ? Tenant::query()->findOrFail($listing->tenant_id)
            : null);
    }

    // ------------------------------------------------------------------
    // Reference data lookups (seeded by bootstrap())
    // ------------------------------------------------------------------

    public static function location(string $name): Location
    {
        return Location::query()->where('slug', Str::slug($name))->firstOrFail();
    }

    public static function typeId(string $name): int
    {
        return (int) PropertyType::query()->where('slug', Str::slug($name))->value('id');
    }

    /** @return list<string> */
    public static function amenitySlugs(array $names): array
    {
        return array_map(fn (string $name) => Str::slug($name), $names);
    }

    /** @return list<int> */
    public static function amenityIds(array $names): array
    {
        return Amenity::query()
            ->whereIn('slug', self::amenitySlugs($names))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return list<string> */
    public static function cuisineSlugs(array $names): array
    {
        return array_map(fn (string $name) => Str::slug($name), $names);
    }

    /** @return list<int> */
    public static function cuisineIds(array $names): array
    {
        return Cuisine::query()
            ->whereIn('slug', self::cuisineSlugs($names))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    // ------------------------------------------------------------------
    // Listings
    // ------------------------------------------------------------------

    /**
     * A stay owned by $tenant. Defaults to a draft in Boracay so tests only
     * spell out the attributes they actually care about.
     *
     * @param  array{amenities?: list<string>}  $attributes
     */
    public static function property(Tenant $tenant, User $host, array $attributes = []): Property
    {
        self::asTenant($tenant);

        $amenities = $attributes['amenities'] ?? [];
        unset($attributes['amenities']);

        $property = Property::create(array_merge([
            'tenant_id' => $tenant->id,
            'host_id' => $host->id,
            'location_id' => self::location('Boracay')->id,
            'property_type_id' => self::typeId('Resort'),
            'name' => 'Test Stay',
            'tagline' => 'Quiet island rooms a minute from the sand.',
            'description' => 'A calm place to stay with everything you need.',
            'city' => 'Boracay',
            'region' => 'Western Visayas',
            'country_code' => 'PH',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'bathrooms' => 1,
            'base_price' => 5000,
            'currency' => 'PHP',
            'status' => Property::STATUS_DRAFT,
        ], $attributes));

        if ($amenities !== []) {
            $property->amenities()->sync(self::amenityIds($amenities));
        }

        return $property->refresh();
    }

    /**
     * A restaurant owned by $tenant.
     *
     * @param  array{cuisines?: list<string>}  $attributes
     */
    public static function restaurant(Tenant $tenant, User $host, array $attributes = []): Restaurant
    {
        self::asTenant($tenant);

        $cuisines = $attributes['cuisines'] ?? [];
        unset($attributes['cuisines']);

        $restaurant = Restaurant::create(array_merge([
            'tenant_id' => $tenant->id,
            'host_id' => $host->id,
            'location_id' => self::location('Boracay')->id,
            'name' => 'Test Kitchen',
            'tagline' => 'Coastal plates and cold drinks.',
            'description' => 'A small kitchen serving the catch of the day.',
            'city' => 'Boracay',
            'region' => 'Western Visayas',
            'country_code' => 'PH',
            'price_level' => 2,
            'status' => Restaurant::STATUS_DRAFT,
        ], $attributes));

        if ($cuisines !== []) {
            $restaurant->cuisines()->sync(self::cuisineIds($cuisines));
        }

        return $restaurant->refresh();
    }

    /**
     * Fresh business plus one published stay.
     *
     * @return array{0: Property, 1: User, 2: Tenant}
     */
    public static function stay(array $attributes = [], string $business = 'Hotel A'): array
    {
        [$owner, $tenant] = self::business($business);

        $property = self::property($tenant, $owner, array_merge([
            'status' => Property::STATUS_PUBLISHED,
            'published_at' => now(),
        ], $attributes));

        return [$property, $owner, $tenant];
    }

    /**
     * Fresh business plus one published restaurant.
     *
     * @return array{0: Restaurant, 1: User, 2: Tenant}
     */
    public static function dining(array $attributes = [], string $business = 'Kitchen Co'): array
    {
        [$owner, $tenant] = self::business($business);

        $restaurant = self::restaurant($tenant, $owner, array_merge([
            'status' => Restaurant::STATUS_PUBLISHED,
            'published_at' => now(),
        ], $attributes));

        return [$restaurant, $owner, $tenant];
    }

    /** Flip a listing live (Property has publish(); Restaurant does not). */
    public static function publish(Property|Restaurant $listing): Property|Restaurant
    {
        self::asListing($listing);

        $listing->forceFill([
            'status' => Property::STATUS_PUBLISHED,
            'published_at' => $listing->published_at ?? now(),
        ])->save();

        return $listing->refresh();
    }

    // ------------------------------------------------------------------
    // Related rows
    // ------------------------------------------------------------------

    /** A guest review; published by default because only those are visible. */
    public static function review(User $guest, Model $listing, array $attributes = []): Review
    {
        self::asListing($listing);

        return Review::create(array_merge([
            'user_id' => $guest->id,
            'reviewable_type' => $listing->getMorphClass(),
            'reviewable_id' => $listing->getKey(),
            'rating' => 5,
            'title' => 'Lovely stay',
            'comment' => 'Spotless rooms and a very helpful host.',
            'status' => Review::STATUS_PUBLISHED,
            'published_at' => now(),
        ], $attributes));
    }

    /** Save a listing to a guest's wish list. */
    public static function favorite(User $guest, Model $listing): Favorite
    {
        self::asListing($listing);

        return Favorite::firstOrCreate([
            'user_id' => $guest->id,
            'favoritable_type' => $listing->getMorphClass(),
            'favoritable_id' => $listing->getKey(),
        ]);
    }

    /**
     * Attach gallery photos (the first one is the cover). Paths are absolute
     * CDN URLs so Media::url() returns them unchanged and assertions are stable.
     *
     * @return list<Media>
     */
    public static function photos(Model $listing, int $count = 3): array
    {
        self::asListing($listing);

        $media = [];

        for ($index = 1; $index <= $count; $index++) {
            $media[] = $listing->media()->create([
                'disk' => 'public',
                'path' => sprintf(
                    'https://cdn.example.test/%s-%d-%d.jpg',
                    $listing->getMorphClass(),
                    $listing->getKey(),
                    $index,
                ),
                'alt' => $listing->name.' photo '.$index,
                'is_cover' => $index === 1,
                'sort_order' => $index,
            ]);
        }

        return $media;
    }
}
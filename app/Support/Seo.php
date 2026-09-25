<?php

namespace App\Support;

use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Marketplace\Models\Review;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Schema.org JSON-LD builders for the public site.
 *
 * Every builder returns a plain array node; the public layout wraps the nodes
 * in one `@graph` script. Only facts already visible on the page go in
 * (Google penalises structured data that the page does not show).
 */
class Seo
{
    /** Property type slug → the most specific schema.org LodgingBusiness type. */
    private const LODGING_TYPES = [
        'hotel' => 'Hotel',
        'resort' => 'Resort',
        'bed-breakfast' => 'BedAndBreakfast',
        'guesthouse' => 'BedAndBreakfast',
        'hostel' => 'Hostel',
        'apartment' => 'Apartment',
        'condo' => 'Apartment',
        'villa' => 'House',
        'entire-property' => 'House',
    ];

    /** @param  array<string, string|null>  $crumbs  label => url (null = current page) */
    public static function breadcrumbs(array $crumbs): array
    {
        $position = 0;

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)->map(fn ($url, $label) => array_filter([
                '@type' => 'ListItem',
                'position' => ++$position,
                'name' => $label,
                'item' => $url ?? url()->current(),
            ]))->values()->all(),
        ];
    }

    public static function organization(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => config('app.name'),
            'url' => url('/'),
            'logo' => asset('favicon.ico'),
        ];
    }

    /** WebSite node with the stay search as a sitelinks search box. */
    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'name' => config('app.name'),
            'url' => url('/'),
            'publisher' => ['@id' => url('/').'#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('marketplace.hotels').'?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public static function property(Property $p): array
    {
        $type = self::LODGING_TYPES[$p->propertyType?->slug] ?? 'LodgingBusiness';

        // Apartment / House are Accommodation, not LocalBusiness: pair them with
        // LodgingBusiness so ratings and offers stay valid on the node.
        $node = [
            '@type' => in_array($type, ['Apartment', 'House'], true) ? ['LodgingBusiness', $type] : $type,
            '@id' => route('marketplace.properties.show', $p->slug).'#listing',
            'name' => $p->name,
            'description' => Str::limit(strip_tags((string) ($p->description ?: $p->tagline)), 300),
            'url' => route('marketplace.properties.show', $p->slug),
            'image' => $p->galleryUrls() ?: null,
            'address' => self::address($p->address_line, $p->city, $p->region, $p->country_code),
            'geo' => self::geo($p->latitude, $p->longitude),
            'checkinTime' => $p->check_in_time,
            'checkoutTime' => $p->check_out_time,
            'numberOfRooms' => $p->bedrooms ?: null,
            'amenityFeature' => $p->relationLoaded('amenities') && $p->amenities->isNotEmpty()
                ? $p->amenities->map(fn ($a) => ['@type' => 'LocationFeatureSpecification', 'name' => $a->name, 'value' => true])->values()->all()
                : null,
            'makesOffer' => (float) $p->base_price > 0 ? [
                '@type' => 'Offer',
                'price' => number_format((float) $p->base_price, 2, '.', ''),
                'priceCurrency' => $p->currency,
                'availability' => 'https://schema.org/InStock',
                'url' => route('marketplace.properties.show', $p->slug),
                'itemOffered' => ['@type' => 'Product', 'name' => 'One night at '.$p->name],
            ] : null,
        ];

        return array_filter($node + self::ratings($p), fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    public static function restaurant(Restaurant $r): array
    {
        $cuisines = $r->relationLoaded('cuisines') ? $r->cuisines->pluck('name')->all() : [];

        $node = [
            '@type' => 'Restaurant',
            '@id' => route('marketplace.restaurants.show', $r->slug).'#listing',
            'name' => $r->name,
            'description' => Str::limit(strip_tags((string) ($r->description ?: $r->tagline)), 300),
            'url' => route('marketplace.restaurants.show', $r->slug),
            'image' => $r->galleryUrls() ?: null,
            'telephone' => $r->phone,
            'address' => self::address($r->address_line, $r->city, $r->region, $r->country_code),
            'geo' => self::geo($r->latitude, $r->longitude),
            'servesCuisine' => $cuisines ?: null,
            'priceRange' => $r->price_level ? str_repeat('₱', $r->price_level) : null,
            'acceptsReservations' => (bool) $r->reservations_enabled,
            'hasMenu' => self::menu($r),
        ];

        return array_filter($node + self::ratings($r), fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /** @param  Collection<int, object>|iterable  $items  objects with name + monthly_price_cents */
    public static function productOffers(iterable $items, string $currency = 'PHP'): array
    {
        return collect($items)->map(fn ($m) => [
            '@type' => 'Product',
            'name' => config('app.name').' '.$m->name,
            'description' => $m->description,
            'offers' => [
                '@type' => 'Offer',
                'price' => number_format($m->monthly_price_cents / 100, 2, '.', ''),
                'priceCurrency' => $currency,
                'availability' => 'https://schema.org/InStock',
            ],
        ])->values()->all();
    }

    /** @param  array<string, string>  $faqs  question => answer (plain text, shown on the page) */
    public static function faq(array $faqs): array
    {
        return [
            '@type' => 'FAQPage',
            'mainEntity' => collect($faqs)->map(fn ($answer, $question) => [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
            ])->values()->all(),
        ];
    }

    /** AggregateRating + up to five Review nodes from the published reviews already loaded. */
    private static function ratings(Property|Restaurant $listing): array
    {
        if ((int) $listing->reviews_count < 1 || (float) $listing->avg_rating <= 0) {
            return [];
        }

        $reviews = $listing->relationLoaded('reviews') ? $listing->reviews->take(5) : collect();

        return [
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format((float) $listing->avg_rating, 1, '.', ''),
                'reviewCount' => (int) $listing->reviews_count,
                'bestRating' => 5,
                'worstRating' => 1,
            ],
            'review' => $reviews->map(fn (Review $review) => array_filter([
                '@type' => 'Review',
                'author' => ['@type' => 'Person', 'name' => $review->user?->name ? Str::before($review->user->name, ' ') : 'Guest'],
                'datePublished' => ($review->published_at ?? $review->created_at)?->toDateString(),
                'name' => $review->title,
                'reviewBody' => $review->comment,
                'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (int) $review->rating, 'bestRating' => 5, 'worstRating' => 1],
            ]))->values()->all() ?: null,
        ];
    }

    private static function menu(Restaurant $r): ?array
    {
        if (! $r->relationLoaded('menuCategories') || $r->menuCategories->isEmpty()) {
            return null;
        }

        return [
            '@type' => 'Menu',
            'hasMenuSection' => $r->menuCategories->map(fn ($category) => [
                '@type' => 'MenuSection',
                'name' => $category->name,
                'hasMenuItem' => $category->items->where('is_available', true)->map(fn ($item) => array_filter([
                    '@type' => 'MenuItem',
                    'name' => $item->name,
                    'description' => $item->description,
                    'offers' => ['@type' => 'Offer', 'price' => number_format((float) $item->price, 2, '.', ''), 'priceCurrency' => $item->currency ?: 'PHP'],
                ]))->values()->all(),
            ])->values()->all(),
        ];
    }

    private static function address(?string $street, ?string $city, ?string $region, ?string $country): ?array
    {
        $address = array_filter([
            'streetAddress' => $street,
            'addressLocality' => $city,
            'addressRegion' => $region,
            'addressCountry' => $country,
        ]);

        return $address ? ['@type' => 'PostalAddress'] + $address : null;
    }

    private static function geo($lat, $lng): ?array
    {
        return $lat !== null && $lng !== null
            ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $lat, 'longitude' => (float) $lng]
            : null;
    }
}

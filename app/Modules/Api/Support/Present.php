<?php

namespace App\Modules\Api\Support;

use App\Modules\Booking\Models\Booking;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Marketplace\Models\Review;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\PropertyManagement\Models\RoomType;
use Illuminate\Support\Str;

/**
 * JSON shapes for API v1. Whitelists fields explicitly: internal columns
 * (tenant ids, provider secrets, hold timers) never leak through toArray().
 * Money is a decimal string plus currency; dates are ISO-8601.
 */
final class Present
{
    public static function property(Property $p): array
    {
        return [
            'slug' => $p->slug,
            'name' => $p->name,
            'tagline' => $p->tagline,
            'type' => $p->propertyType?->name,
            'location' => ['city' => $p->city, 'region' => $p->region, 'country' => $p->country_code],
            'max_guests' => $p->max_guests,
            'bedrooms' => $p->bedrooms,
            'price_from' => self::money($p->base_price, $p->currency),
            'rating' => ['average' => $p->avg_rating !== null ? (float) $p->avg_rating : null, 'count' => (int) $p->reviews_count],
            'cover_url' => self::absolute($p->coverUrl()),
            'url' => route('marketplace.properties.show', $p->slug),
        ];
    }

    public static function restaurant(Restaurant $r): array
    {
        return [
            'slug' => $r->slug,
            'name' => $r->name,
            'tagline' => $r->tagline,
            'cuisines' => $r->relationLoaded('cuisines') ? $r->cuisines->pluck('name')->all() : [],
            'location' => ['city' => $r->city, 'region' => $r->region, 'country' => $r->country_code],
            'price_level' => $r->price_level,
            'accepts' => ['reservations' => (bool) $r->reservations_enabled, 'orders' => (bool) $r->ordering_enabled, 'delivery' => (bool) $r->delivery_enabled],
            'rating' => ['average' => $r->avg_rating !== null ? (float) $r->avg_rating : null, 'count' => (int) $r->reviews_count],
            'cover_url' => self::absolute($r->coverUrl()),
            'url' => route('marketplace.restaurants.show', $r->slug),
        ];
    }

    public static function roomType(RoomType $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'description' => $t->description,
            'max_guests' => $t->max_guests,
            'beds' => $t->beds,
            'bed_configuration' => $t->bed_configuration,
            'size_sqm' => $t->size_sqm,
            'min_stay_nights' => $t->min_stay_nights,
            'price' => self::money($t->base_price, $t->currency),
            'weekend_price' => $t->weekend_price !== null ? self::money($t->weekend_price, $t->currency) : null,
        ];
    }

    public static function booking(Booking $b): array
    {
        return [
            'reference' => $b->reference,
            'status' => $b->status,
            'source' => $b->source,
            'property' => $b->relationLoaded('property') && $b->property ? ['slug' => $b->property->slug, 'name' => $b->property->name] : null,
            'check_in' => $b->check_in?->toDateString(),
            'check_out' => $b->check_out?->toDateString(),
            'guests' => ['adults' => $b->adults, 'children' => $b->children],
            'guest_name' => $b->guest_name,
            'total' => self::money($b->total, $b->currency),
            'created_at' => $b->created_at?->toIso8601String(),
        ];
    }

    public static function order(Order $o): array
    {
        return [
            'reference' => $o->reference,
            'status' => $o->status,
            'channel' => $o->channel,
            'restaurant' => $o->relationLoaded('restaurant') && $o->restaurant ? ['slug' => $o->restaurant->slug, 'name' => $o->restaurant->name] : null,
            'fulfillment' => $o->fulfillment,
            'payment' => ['method' => $o->payment_method, 'status' => $o->payment_status],
            'customer_name' => $o->customer_name,
            'items' => $o->relationLoaded('items') ? $o->items->map(fn ($i) => ['name' => $i->name, 'quantity' => (int) $i->quantity, 'total' => self::money($i->line_total, $o->currency)])->all() : null,
            'total' => self::money($o->total, $o->currency),
            'created_at' => $o->created_at?->toIso8601String(),
        ];
    }

    public static function payment(Payment $p): array
    {
        return [
            'id' => $p->id,
            'status' => $p->status,
            'provider' => $p->provider,
            'amount' => self::money($p->amount, $p->currency),
            'booking' => $p->booking?->reference,
            'order' => $p->order?->reference,
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }

    public static function review(Review $r): array
    {
        return [
            'rating' => (int) $r->rating,
            'title' => $r->title,
            'comment' => $r->comment,
            'author' => $r->user ? Str::before($r->user->name, ' ') : 'Guest',
            'host_response' => $r->host_response,
            'published_at' => ($r->published_at ?? $r->created_at)?->toIso8601String(),
        ];
    }

    public static function money($amount, ?string $currency): array
    {
        return ['amount' => number_format((float) $amount, 2, '.', ''), 'currency' => $currency ?: 'PHP'];
    }

    private static function absolute(?string $url): ?string
    {
        return $url === null || str_starts_with($url, 'http') ? $url : url($url);
    }
}

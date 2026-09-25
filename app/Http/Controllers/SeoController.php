<?php

namespace App\Http\Controllers;

use App\Modules\Marketplace\Models\Location;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\PlatformAdmin\Models\Page;
use Illuminate\Http\Response;

/** robots.txt and sitemap.xml for the public site. */
class SeoController extends Controller
{
    /** Private areas that must never be crawled. */
    private const DISALLOW = [
        '/dashboard', '/admin', '/account', '/favorites', '/tenants', '/profile',
        '/cart', '/checkout', '/login', '/register', '/password', '/unsubscribe',
    ];

    public function robots(): Response
    {
        // Only production may be indexed: staging / local copies would compete
        // with the real site as duplicate content.
        $lines = app()->isProduction()
            ? array_merge(['User-agent: *'], array_map(fn ($path) => 'Disallow: '.$path, self::DISALLOW), ['', 'Sitemap: '.route('seo.sitemap')])
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    // ponytail: one uncapped sitemap; split into a sitemap index past ~40k URLs.
    public function sitemap(): Response
    {
        $urls = collect([
            ['loc' => route('home'), 'priority' => '1.0'],
            ['loc' => route('marketplace.home'), 'priority' => '0.9'],
            ['loc' => route('marketplace.hotels'), 'priority' => '0.9'],
            ['loc' => route('marketplace.restaurants.index'), 'priority' => '0.8'],
            ['loc' => route('marketing.features'), 'priority' => '0.6'],
            ['loc' => route('marketing.pricing'), 'priority' => '0.6'],
            ['loc' => route('marketing.contact'), 'priority' => '0.4'],
        ]);

        $locations = Location::query()->withListings()->get(['slug', 'updated_at'])
            ->map(fn ($l) => ['loc' => route('marketplace.locations.show', $l->slug), 'lastmod' => $l->updated_at, 'priority' => '0.8']);

        $properties = Property::publicQuery()->without(['location', 'propertyType', 'media'])->get(['slug', 'updated_at'])
            ->map(fn ($p) => ['loc' => route('marketplace.properties.show', $p->slug), 'lastmod' => $p->updated_at, 'priority' => '0.7']);

        $restaurants = Restaurant::publicQuery()->without(['location', 'cuisines', 'media'])->get(['slug', 'updated_at'])
            ->map(fn ($r) => ['loc' => route('marketplace.restaurants.show', $r->slug), 'lastmod' => $r->updated_at, 'priority' => '0.7']);

        $pages = Page::query()->where('is_published', true)->get(['slug', 'updated_at'])
            ->map(fn ($p) => ['loc' => route('pages.show', $p->slug), 'lastmod' => $p->updated_at, 'priority' => '0.3']);

        return response()
            ->view('seo.sitemap', ['urls' => $urls->concat($locations)->concat($properties)->concat($restaurants)->concat($pages)])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}

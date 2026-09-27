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

    /**
     * /llms.txt (llmstxt.org): a plain-Markdown brief for AI assistants and
     * answer engines, generated from live data so it can't drift.
     */
    public function llms(): Response
    {
        $properties = Property::publicQuery()->ranked()->limit(50)->get();
        $restaurants = Restaurant::publicQuery()->ranked()->limit(30)->get();
        $modules = \App\Models\Module::query()->active()->ordered()->with('activePlans')->get();

        $lines = [
            '# '.config('app.name'),
            '',
            '> '.config('app.name').' is a hospitality platform for the Philippines. Guests book hotels, resorts, villas, B&Bs and restaurant tables directly with the people who run them, paying by card, GCash or Maya. Hotels and restaurants run their business on the same platform: property management (PMS), booking engine, front desk and room chart, housekeeping, restaurant POS, accounting, staff rota and guest CRM.',
            '',
            '## Book',
            '- [All stays]('.route('marketplace.hotels').'): search hotels, resorts and villas by destination, stay type and guests',
            '- [Restaurants]('.route('marketplace.restaurants.index').'): reserve a table or order online',
        ];

        foreach ($properties as $p) {
            $lines[] = '- ['.$p->name.']('.route('marketplace.properties.show', $p->slug).'): '.trim(($p->propertyType?->name ? $p->propertyType->name.' in ' : '').$p->locationLabel()).', from '.$p->priceLabel().' per night, sleeps '.$p->max_guests.((float) $p->avg_rating > 0 ? ', rated '.number_format((float) $p->avg_rating, 1).'/5 from '.$p->reviews_count.' reviews' : '');
        }

        foreach ($restaurants as $r) {
            $lines[] = '- ['.$r->name.']('.route('marketplace.restaurants.show', $r->slug).'): '.$r->cuisines->pluck('name')->join(', ').' restaurant in '.$r->locationLabel().($r->reservations_enabled ? ', takes table reservations' : '');
        }

        $lines = array_merge($lines, ['', '## For hotels and restaurants', '- [Features]('.route('marketing.features').'): what the platform does, module by module', '- [Pricing]('.route('marketing.pricing').'): free foundation; modules priced per month with free trials', '- [Contact]('.route('marketing.contact').')', '', '## Module prices (PHP per month)']);

        foreach ($modules as $m) {
            $cents = (int) ($m->activePlans->firstWhere('billing_interval', 'monthly')->price_cents ?? 0);
            $lines[] = '- '.$m->name.': '.($cents === 0 ? 'free' : \App\Support\Currency::symbol().number_format($cents / 100, 0)).($m->trial_days > 0 ? ', '.$m->trial_days.'-day free trial' : '').' — '.$m->description;
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}

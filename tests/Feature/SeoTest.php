<?php

use App\Models\User;
use App\Modules\Marketplace\Models\Property;
use Tests\Support\MarketplaceFixtures;

/*
| Phase 30 (SEO) — meta / Open Graph / Twitter / canonical tags, schema.org
| JSON-LD (lodging, restaurant, offers, ratings, reviews, breadcrumbs, FAQ,
| products), visible breadcrumbs, sitemap.xml and robots.txt.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(fn () => MarketplaceFixtures::bootstrap());

/** Decode the page's JSON-LD graph into [@type => node]. */
function jsonLd(string $html): array
{
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
    expect($m)->not->toBeEmpty();

    return collect(json_decode($m[1], true)['@graph'])
        ->keyBy(fn ($node) => is_array($node['@type']) ? end($node['@type']) : $node['@type'])
        ->all();
}

it('gives a stay page full meta tags, lodging schema with offer, rating, reviews and breadcrumbs', function () {
    [$property] = MarketplaceFixtures::stay(['name' => 'Harbour View', 'tagline' => 'Rooms over the bay', 'base_price' => 4200]);
    MarketplaceFixtures::asTenant(null);
    MarketplaceFixtures::review(User::factory()->create(['name' => 'Marisol Reyes']), $property, ['rating' => 4, 'comment' => 'Great view']);
    $property = Property::publicQuery()->find($property->id);
    $url = route('marketplace.properties.show', $property->slug);

    $html = $this->get($url)->assertOk()
        ->assertSee('<link rel="canonical" href="'.$url.'">', false)
        ->assertSee('<meta property="og:description" content="Rooms over the bay">', false)
        ->assertSee('<meta name="twitter:title" content="Harbour View">', false)
        ->assertSee('aria-label="Breadcrumb"', false)
        ->getContent();

    $graph = jsonLd($html);
    $listing = collect($graph)->first(fn ($n) => ($n['name'] ?? null) === 'Harbour View');

    expect($listing['makesOffer']['price'])->toBe('4200.00')
        ->and($listing['makesOffer']['itemOffered']['@type'])->toBe('Product')
        ->and($listing['aggregateRating']['reviewCount'])->toBe(1)
        ->and($listing['review'][0]['author']['name'])->toBe('Marisol')   // first name only
        ->and($graph['BreadcrumbList']['itemListElement'])->toHaveCount(4)  // Home / Stays / location / stay
        ->and(end($graph['BreadcrumbList']['itemListElement'])['name'])->toBe('Harbour View');
});

it('emits restaurant schema with cuisine and escapes script breakouts in the JSON-LD', function () {
    [$restaurant] = MarketplaceFixtures::dining(['name' => 'Kusina </script><script>alert(1)</script>']);
    MarketplaceFixtures::asTenant(null);

    $html = $this->get(route('marketplace.restaurants.show', $restaurant->slug))->assertOk()->getContent();

    expect($html)->not->toContain('</script><script>alert(1)')
        ->and(jsonLd($html)['Restaurant']['name'])->toBe('Kusina </script><script>alert(1)</script>');
});

it('noindexes filtered search results and canonicalises /search to /hotels', function () {
    $this->get(route('marketplace.search'))->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('marketplace.hotels').'">', false)
        ->assertDontSee('noindex');

    $this->get(route('marketplace.hotels', ['guests' => 3]))->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false);
});

it('shows the pricing FAQ and emits FAQPage + Product offers from the same data', function () {
    $this->seed(\Database\Seeders\ModuleSeeder::class);

    $graph = jsonLd($this->get(route('marketing.pricing'))->assertOk()
        ->assertSee('Is yearly billing cheaper?')->getContent());

    expect($graph['FAQPage']['mainEntity'])->toHaveCount(count(\App\Http\Controllers\Marketing\PageController::PRICING_FAQ))
        ->and($graph)->toHaveKey('Product')
        ->and(jsonLd($this->get(route('home'))->getContent())['WebSite']['potentialAction']['@type'])->toBe('SearchAction');
});

it('lists only public listings in the sitemap', function () {
    [$live] = MarketplaceFixtures::stay(['name' => 'Live Stay']);
    [$draft] = MarketplaceFixtures::stay(['name' => 'Draft Stay', 'status' => Property::STATUS_DRAFT], 'Other Biz');
    [$restaurant] = MarketplaceFixtures::dining(['name' => 'Open Kitchen']);
    MarketplaceFixtures::asTenant(null);

    $this->get(route('seo.sitemap'))->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee(route('marketplace.properties.show', $live->slug), false)
        ->assertSee(route('marketplace.restaurants.show', $restaurant->slug), false)
        ->assertSee(route('marketing.pricing'), false)
        ->assertDontSee(route('marketplace.properties.show', $draft->slug), false);
});

it('blocks crawlers outside production and fences private areas in production', function () {
    $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false);

    app()->detectEnvironment(fn () => 'production');

    $this->get('/robots.txt')->assertOk()
        ->assertSee('Disallow: /admin')
        ->assertSee('Disallow: /account')
        ->assertSee('Sitemap: '.route('seo.sitemap'));
});

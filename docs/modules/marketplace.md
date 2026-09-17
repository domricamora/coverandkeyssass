# marketplace

STATUS: COMPLETE — Phase 03 (Public marketplace). Verified 2026-09-17: full Pest suite green (74 tests / 200 assertions, MySQL `hospitality_os_testing`).

## Purpose

The public, cross-tenant marketplace surface: guests browse published stays
(hotels, resorts, B&Bs, rentals) and dining venues without an account, keep a
personal wish list, and see guest reviews on listing pages. Host-side editing
of these listings belongs to the Property Management module (Phase 04).

## Models (`App\Modules\Marketplace\Models`)

| Model | Table | Notes |
|---|---|---|
| `Property` | `properties` | Tenant-owned listing. Status lifecycle `draft → pending → published → suspended` (+ soft deletes). BelongsToTenant deny-by-default; `publicQuery()` is the single cross-tenant read path (published rows only). Pricing (base/weekend/cleaning fee), capacity, geo, check-in/out times, `highlights` + `policies` JSON, rating aggregates. |
| `Restaurant` | `restaurants` | Tenant-owned dining listing, same lifecycle/scope rules as Property. |
| `Location` | `locations` | Reference data (slug, name, country) powering `/hotels/{location}` SEO destination pages. |
| `PropertyType` | `property_types` | Reference data (hotel, resort, B&B, villa, …). |
| `Amenity` | `amenities` | Reference data; `property_amenity` pivot. |
| `Cuisine` | `cuisines` | Reference data; `cuisine_restaurant` pivot. |
| `Media` | `media` | Polymorphic gallery rows (cover + ordered images). No tenant column: reached only through its parent listing, so publication rules are inherited. |
| `Favorite` | `favorites` | Wish-list pivot (user ↔ morphed listing), unique per pair. |
| `Review` | `reviews` | Polymorphic guest reviews (1–5 rating, title, comment) with status lifecycle; observers keep `avg_rating`/`reviews_count` aggregates on the listing fresh. |

Concerns: `HasMedia` (ordered gallery + cover fallback), `HasPublicIdentity`
(stable UUID + unique SEO slug; reference tables opt out of the UUID).

## Services

| Service | Responsibility |
|---|---|
| `MarketplaceSearchService` | Published-stay search: keyword, destination, price ceiling, party size, property type, sorting (validated through `PropertySearchRequest`). |
| `FavoriteService` | Wish-list add/remove with a favoritable-type whitelist; personal-scope reads only. |
| `ListingMetricsService` | Denormalised counters (rating averages, review counts, favorites) maintained via observers. |

## Routes & controllers (public, `web` group)

| URL | Controller | Behaviour |
|---|---|---|
| `GET /stays` | `HomeController@index` | Marketplace landing page (`marketplace.home`). |
| `GET /hotels` (`/search`) | `PropertySearchController@index` | Stays search + filters/sorting. |
| `GET /hotels/{location}` | `PropertySearchController@location` | Destination landing page (SEO). |
| `GET /property/{slug}` | `PropertyController@show` | Stay detail; drafts/suspended/deleted → plain 404. |
| `GET /restaurants` | `RestaurantController@index` | Dining search. |
| `GET /restaurant/{slug}` | `RestaurantController@show` | Dining detail; same 404 rule. |
| `GET /favorites` | `FavoriteController@index` | Personal wish list (auth required). |
| `POST/DELETE /favorites/{type}/{id}` | `FavoriteController@store/destroy` | Toggle wish-list membership; `type` whitelisted. |

Binding note: public lookups resolve by slug through `publicQuery()` instead
of implicit route-model binding, because the tenant scope would deny every
public read.

## Authorization & tenancy

- Public pages are intentionally cross-tenant but strictly read-only and
  limited to `status = published`.
- `PropertyPolicy` (registered in the module provider) already guards
  host-side abilities (`view/viewAny/create/update/delete/publish`) for the
  Phase 04 management UI; it denies cross-tenant access even with permission.
- The morph map (`property`, `restaurant`) is additive — media, favorites and
  reviews store short type names without forcing a global morph map.

## Seeders

- `MarketplaceReferenceSeeder` — locations, property types, amenities, cuisines (idempotent).
- `MarketplaceDemoSeeder` — demo stays/restaurants with media, reviews and favorites for local browsing.

## Views

Namespaced `marketplace::`: landing page, stays search (with filter partials,
cards, pagination, empty state), stay detail, dining search/detail, favorites
index. Uses the public layout and the bnb design system (no JS build changes).

## Tests

`tests/Feature/MarketplaceHttpTest.php` + `tests/Support/MarketplaceFixtures.php`:

- landing page renders published highlights across tenants,
- only published stays appear in search (drafts/suspended hidden),
- published detail pages open; unpublished slugs 404,
- destination / price-ceiling / party-size / keyword filters,
- wish list: authentication required, add/remove, strictly personal scope.

Run with `php artisan test`.

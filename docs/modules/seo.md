# SEO (Phase 30)

Search-engine and social-share metadata for the public site. There is no module toggle and no database table. Everything is built from listing data at request time.

## Where it lives

| Piece | File |
|---|---|
| Meta, Open Graph, Twitter, canonical, robots meta, JSON-LD, visible breadcrumbs | `resources/views/layouts/public.blade.php` + `app/View/Components/PublicLayout.php` |
| Schema.org node builders | `app/Support/Seo.php` |
| `robots.txt`, `sitemap.xml` | `app/Http/Controllers/SeoController.php`, `resources/views/seo/sitemap.blade.php` |

## `<x-public-layout>` props

| Prop | Purpose |
|---|---|
| `title`, `description` | `<title>`, meta description, `og:*` / `twitter:*` |
| `image` | `og:image` / `twitter:image`. With an image the Twitter card is `summary_large_image`, without one it is `summary`. |
| `canonical` | Defaults to the current URL without the query string |
| `breadcrumbs` | `['Label' => url, 'Current' => null]`. Renders the visible trail and a `BreadcrumbList`. "Home" is added automatically. |
| `schema` | Extra nodes from `App\Support\Seo`. The layout wraps them, plus an `Organization` node, in one `@graph`. |
| `noindex` | Adds `<meta name="robots" content="noindex, follow">` |

## Structured data

| Page | Nodes |
|---|---|
| `/` | `WebSite` + `SearchAction` (sitelinks search box to `/hotels?q=`) |
| `/property/{slug}` | Most specific `LodgingBusiness` type from the property category (`Hotel`, `Resort`, `BedAndBreakfast`, `Hostel`; `Apartment` / `House` paired with `LodgingBusiness`), address, geo, check-in/out, amenities, `makesOffer` → `Offer` (nightly base price) → `Product`, `AggregateRating`, up to 5 `Review`s |
| `/restaurant/{slug}` | `Restaurant` with cuisine, price range, reservations, `Menu` → `MenuSection` → `MenuItem` + `Offer`, ratings, reviews |
| `/pricing` | One `Product` + `Offer` per paid module, and `FAQPage` |
| Stays / restaurants / destinations / CMS pages | `BreadcrumbList` |

Rules:

- Only facts the page shows go into the JSON-LD. The pricing FAQ is one array (`PageController::PRICING_FAQ`) that renders both the visible `<details>` list and the `FAQPage`.
- Reviewer names are reduced to the first name, as on the page.
- JSON is encoded with `JSON_HEX_TAG | JSON_HEX_AMP`, so listing text cannot close the `<script>` tag.

## Indexing rules

- Filtered result pages (any query parameter except `page`) are `noindex, follow`. Canonicals point to `/hotels`, `/restaurants` or the destination URL, and `/search` canonicalises to `/hotels`.
- Unpublished CMS previews (visible only to Super Admins) are `noindex`.
- `robots.txt` is served by Laravel. The static `public/robots.txt` was removed because Apache would serve it first. Outside production it returns `Disallow: /`. In production it disallows private areas (`/dashboard`, `/admin`, `/account`, `/favorites`, `/tenants`, `/profile`, `/cart`, `/checkout`, auth, `/unsubscribe`) and points to the sitemap.
- `sitemap.xml` lists the static marketing and marketplace pages, destinations that have listings, every published property and restaurant (through `publicQuery()`), and published CMS pages, with `lastmod`. It is a single file; move to a sitemap index past about 40k URLs.

## Tests

`tests/Feature/SeoTest.php` (6 tests):

- lodging schema with offer, rating, first-name review and a 4-level breadcrumb
- restaurant schema, and a `</script>` breakout is escaped
- noindex and canonical on filtered search
- pricing FAQ + products, and the home-page `SearchAction`
- the sitemap excludes drafts
- `robots.txt` per environment

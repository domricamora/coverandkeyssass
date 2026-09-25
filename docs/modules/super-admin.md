# super-admin

STATUS: COMPLETE — Phase 28 (Super Admin). Verified 2026-09-26: full Pest suite green (265 tests / 1680 assertions).

Code: `App\Modules\PlatformAdmin`, plus `App\Http\Controllers\Admin\*` (Phase 01/02) and the admin screens of Wallet, Reviews, Messaging and Billing. Every route is behind `auth` + `super.admin`.

## Dashboard (`/admin`)

- Guest payments (all time and last 30 days).
- Platform commission, with the number pending release.
- Subscription revenue, with the number of open invoices.
- Bookings and food orders (all time and last 30 days).
- Hosts (businesses), with the number active.
- Customers (users with no business and no platform role).
- Properties and restaurants awaiting approval.
- Live and past-due subscriptions.
- Requested payouts.

Each tile links to its control screen. A nav bar reaches every admin screen.

## Controls

| Area | Screen | What it does |
|---|---|---|
| Users, hosts, customers | `/admin/users?type=hosts\|customers` | search, suspend / activate (Phase 01), host / customer filter |
| Businesses | `/admin/tenants` | create, suspend, delete, modules per business |
| Properties / restaurants | `/admin/listings/{properties\|restaurants}` | approve pending, publish, suspend (reason required), send back to draft; audited `platform.listing.*` |
| Bookings / orders | `/admin/bookings`, `/admin/orders` | cross-business search by reference / guest, status filter |
| Payments & refunds | `/admin/payments` | status filter; **Refund** puts the booking or order through its state machine (cancel if needed → refunded), so PayMongo, the wallet, commissions, the folio and accounting follow as they do for a host refund |
| Payouts / commissions | `/admin/payouts`, `/admin/commissions` | Phase 08 |
| Reviews / support | `/admin/reviews`, `/admin/support` | Phases 24 / 25 |
| Modules | `/admin/modules` | Phase 02 catalogue CRUD |
| Pricing | `/admin/pricing` | Monthly / Yearly price and active flag for every module plan (applies from each business's next invoice); audited |
| Subscriptions | `/admin/billing` | Phase 27 |
| CMS | `/admin/pages` → public `/pages/{slug}` | Markdown (raw HTML stripped, unsafe links dropped), draft / published, optional footer link; drafts are visible only to Super Admins |
| Settings | `/admin/settings` | support email / phone (footer), site announcement banner (public pages), default commission % (used by `CommissionRate::defaultRate()` when no rule matches) |
| Reports | `/admin/reports` | CSV exports by creation date for bookings, food orders, payments, commissions and subscription invoices. Streamed and chunked. Cells starting with `= + - @` are prefixed with `'`, so spreadsheets never run them as formulas |
| Logs | `/admin/logs` | audit log, filterable by area prefix, user email, business and date |

## Tables

- `cms_pages`: slug unique, title, meta_description, body (Markdown), is_published, in_footer, updated_by.
- `platform_settings`: key → value, read through one cached map (`Setting::get`); the cache is cleared on save.

## Marketplace administration (Phase 29)

Verified 2026-09-26: 269 tests / 1738 assertions.

- **Placement** (`/admin/listings/*` → Placement):
  - Featured, optionally with an end date (`featured_until`).
  - Sponsored until a date (`sponsored_until`), always labelled "Sponsored" to guests.
  - Ranking boost from −50 to 50.
  - Every change is audited as `platform.listing.placement`.
- **Search ranking** (the "recommended" order, used by search, the home page rails and restaurants) is `Promotable::scopeRanked`: live sponsored first, then live featured, then a score of `boost + rating×10 + min(reviews, 50)×0.2 + 5 if verified`.
- **Verification**: listings are verified on the Placement form (`verified_at`). Hosts (businesses) are verified from the business page (`tenants.verified_at` plus a note). Guests see "Verified property / restaurant" and "Verified host" badges.
- **Categories & locations** (`/admin/taxonomy/{locations|property-types|cuisines|amenities}`):
  - Add, rename, re-slug, order, and show / hide (or feature, for locations).
  - An entry that listings use can't be deleted; hide it instead.
- **Reported content**:
  - Signed-in guests can report a listing from its page (`POST /report/{kind}/{slug}`, throttled). Each person has at most one open report per listing.
  - `/admin/moderation`: suspend the listing (a note is required), resolve or dismiss. This closes every open report on that listing.
  - Reported reviews stay in `/admin/reviews` (Phase 24).
- Tables: listing columns `featured_until`, `sponsored_until`, `ranking_boost`, `verified_at`; tenants `verified_at`, `verification_note`; `content_reports` (reporter, morph listing, reason, details, status `open/resolved/dismissed`, resolver, note).
